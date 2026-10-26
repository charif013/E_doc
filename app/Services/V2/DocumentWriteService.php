<?php

namespace App\Services\V2;

use App\Models\User as LegacyUser;
use App\Models\V2\Document;
use App\Models\V2\DocumentAccessRequest;
use App\Models\V2\DocumentCategory;
use App\Models\V2\DocumentConfidentiality;
use App\Models\V2\DocumentFile;
use App\Models\V2\DocumentPriority;
use App\Models\V2\DocumentType;
use App\Models\V2\OrganizationUnit;
use App\Models\V2\User;
use App\Models\V2\WorkflowDefinition;
use App\Models\V2\WorkflowInstance;
use App\Models\V2\WorkflowStep;
use App\Services\DocumentFileStorage;
use App\Services\DocumentRevisionService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DocumentWriteService
{
    public function __construct(
        private V2WriteGuard $guard,
        private WorkflowService $workflow,
        private DocumentNumberingService $numbering,
        private DocumentFileStorage $files,
        private DocumentRevisionService $revisions,
    ) {}

    public function create(array $data, array $routeUserIds, int $actorId, ?UploadedFile $file = null): Document
    {
        $this->guard->ensureEnabled();
        $routeUserIds = $this->validRouteUsers($routeUserIds, $actorId);

        return DB::connection('mysql_v2')->transaction(function () use ($data, $routeUserIds, $actorId, $file) {
            $type = strtolower((string) ($data['doc_type'] ?? 'internal'));
            $document = Document::create($this->documentColumns($data, $actorId, $type));
            if ($file) {
                $this->storeFile($document, $file, 'MAIN', $this->folderFor($type), $actorId);
            }
            if (! empty($data['external_url'])) {
                DocumentFile::create([
                    'document_id' => $document->id, 'file_type' => 'EXTERNAL',
                    'original_name' => 'external-document', 'external_url' => trim($data['external_url']),
                    'version_no' => 1, 'uploaded_by' => $actorId,
                ]);
            }
            $this->createWorkflow($document, $actorId, $routeUserIds, $data['signer_user_id'] ?? null);

            if (! empty($data['running_number'])) {
                $formatted = (string) ($type === 'incoming'
                    ? ($data['receive_number'] ?? '')
                    : ($data['doc_number'] ?? ''));
                $this->numbering->allocate($document, $actorId, (int) $data['running_number'], $formatted, $this->scope($type, $actorId));
            }

            $this->revisions->record($document->fresh(), $actorId, 'สร้างฉบับร่าง');

            return $document->fresh();
        }, 3);
    }

    public function update(Document $document, array $data, int $actorId, ?UploadedFile $file = null): Document
    {
        $this->guard->ensureEnabled();
        if ($document->created_by !== $actorId || ! in_array($document->status, ['DRAFT', 'REJECTED'], true)) {
            abort(403);
        }

        return DB::connection('mysql_v2')->transaction(function () use ($document, $data, $actorId, $file) {
            $wasRejected = $document->status === 'REJECTED';
            $locked = Document::whereKey($document->id)->lockForUpdate()->firstOrFail();
            $previousSignerId = $locked->doc_type === 'outgoing'
                ? $this->signerIdFromName((string) $locked->signer_name)
                : null;
            if ($locked->doc_type === 'outgoing' && ($data['document_source'] ?? null) === 'upload') {
                $runningNumber = (int) ($data['running_number'] ?? 0);
                $formattedNumber = trim((string) ($data['doc_number'] ?? ''));
                if ($runningNumber < 1 || $formattedNumber === '') {
                    throw ValidationException::withMessages([
                        'doc_number' => 'เลขหนังสือส่งออกต้องเชื่อมกับเลขลำดับในสมุดคุมเลข',
                    ]);
                }
                $this->numbering->allocate(
                    $locked,
                    $actorId,
                    $runningNumber,
                    $formattedNumber,
                    $this->scope('outgoing', (int) $locked->created_by)
                );
            }
            $locked->update($this->documentColumns($data, $actorId, $locked->doc_type, false) + [
                'status' => 'DRAFT', 'rejection_reason' => null,
            ]);
            if ($file) {
                $this->storeFile($locked, $file, 'MAIN', $this->folderFor($locked->doc_type), $actorId);
            }
            $locked->files()->where('file_type', 'SIGNED')->delete();
            if ($locked->workflow) {
                $locked->workflow->steps()->each(function (WorkflowStep $step) {
                    $step->evidence()->delete();
                    $step->update(['status' => 'PENDING', 'comment' => null, 'acted_at' => null]);
                });
                $locked->workflow->update(['status' => 'PENDING', 'current_step' => 1, 'completed_at' => null]);
                if ($locked->doc_type === 'outgoing' && ! empty($data['signer_user_id'])) {
                    $this->syncOutgoingSignerStep($locked->workflow, (int) $data['signer_user_id'], $previousSignerId);
                }
            }

            $this->revisions->record($locked->fresh(), $actorId, $wasRejected ? 'แก้ไขตามข้อเสนอแนะและส่งใหม่' : 'แก้ไขฉบับร่าง');

            return $locked->fresh();
        }, 3);
    }

    public function submit(Document $document, User $actor): Document
    {
        $this->guard->ensureEnabled();
        if ($document->created_by !== $actor->id || $document->status !== 'DRAFT') {
            abort(403);
        }
        $this->ensureOutgoingSignerStep($document);
        $instance = $document->workflow()->firstOrFail();
        $this->workflow->act($instance, $actor, 'APPROVED', 'ลงนามและส่งเรื่อง', null, 'CREATED');
        $instance->refresh();
        if ($instance->status === 'APPROVED') {
            $document->update(['status' => 'APPROVED']);
        } else {
            $document->update(['status' => 'IN_REVIEW']);
        }

        return $document->fresh();
    }

    public function review(Document $document, User $actor, bool $approved, ?string $comment, ?string $assignmentChoice, ?string $evidenceAction = null): Document
    {
        $this->guard->ensureEnabled();
        if ($approved && $document->doc_type === 'outgoing' && $document->source_type === 'upload') {
            $allocation = $document->numberAllocation()->first();
            if (! $allocation || trim((string) $allocation->formatted_number) !== trim((string) $document->document_number)) {
                throw ValidationException::withMessages([
                    'document_number' => 'ไม่สามารถอนุมัติได้ เนื่องจากเลขหนังสือไม่ตรงกับสมุดคุมเลข',
                ]);
            }
        }
        $noAssignment = $assignmentChoice === '__NO_ASSIGNMENT__';
        $unit = null;
        if ($document->doc_type === 'incoming' && $assignmentChoice !== null && ! $noAssignment) {
            $unit = OrganizationUnit::where('name', trim($assignmentChoice))->first();
            if (! $unit) {
                throw ValidationException::withMessages(['assignment_choice' => 'ไม่พบส่วนราชการที่เลือกในระบบ']);
            }
        }
        $action = $approved ? 'APPROVED' : 'REJECTED';
        $evidenceAction ??= $action;
        if ($approved) {
            $this->ensureOutgoingSignerStep($document);
        }
        $this->workflow->act($document->workflow()->firstOrFail(), $actor, $action, $comment, null, $evidenceAction);
        $reviewed = $document->fresh();
        if (! $approved) {
            $document->update(['rejection_reason' => $comment]);
        } elseif ($reviewed->status === 'APPROVED' && $reviewed->doc_type === 'outgoing') {
            if ($reviewed->source_type === 'system') {
                // หนังสือที่จัดทำในระบบต้องรอสารบรรณลงเลขหลังผู้ลงนามอนุมัติแล้ว
                $document->update(['number_status' => 'UNASSIGNED', 'finalized_at' => null]);
            } else {
                // PDF ที่จัดทำแล้วมีเลขอยู่ในต้นฉบับ จบงานได้หลังผู้ลงนามยืนยันตัวตน
                $document->update(['status' => 'COMPLETED', 'number_status' => 'CONFIRMED', 'finalized_at' => now()]);
            }
        } elseif ($reviewed->doc_type === 'incoming' && $assignmentChoice !== null) {
            $proposal = $document->assignments()->where('status', 'PROPOSED')->latest('id')->first();
            if ($reviewed->status === 'APPROVED') {
                if ($noAssignment) {
                    $proposal?->update(['assigned_unit_id' => null, 'status' => 'CANCELED', 'assigned_at' => null]);
                } elseif ($proposal) {
                    $proposal->update([
                        'assigned_unit_id' => $unit->id, 'assigned_by' => $actor->id,
                        'status' => 'PENDING', 'assigned_at' => now(),
                    ]);
                } else {
                    $document->assignments()->create([
                        'assigned_unit_id' => $unit->id, 'assigned_by' => $actor->id,
                        'status' => 'PENDING', 'assigned_at' => now(),
                    ]);
                }
            } elseif ($proposal) {
                $proposal->update([
                    'assigned_unit_id' => $unit?->id, 'assigned_by' => $actor->id,
                    'status' => 'PROPOSED', 'assigned_at' => null,
                ]);
            } else {
                $document->assignments()->create([
                    'assigned_unit_id' => $unit?->id, 'assigned_by' => $actor->id,
                    'status' => 'PROPOSED', 'assigned_at' => null,
                ]);
            }
        }

        return $document->fresh();
    }

    public function replaceAttachment(Document $document, UploadedFile $file, int $actorId): Document
    {
        $this->guard->ensureEnabled();
        if ($document->created_by !== $actorId || ! in_array($document->status, ['DRAFT', 'REJECTED'], true)) {
            abort(403);
        }
        $this->storeFile($document, $file, 'MAIN', $this->folderFor($document->doc_type), $actorId);

        return $document->fresh();
    }

    public function attachExternalQrCopy(Document $document, UploadedFile $file, int $actorId): Document
    {
        $this->guard->ensureEnabled();
        if ($document->created_by !== $actorId || ! in_array($document->status, ['DRAFT', 'REJECTED'], true)) {
            abort(403);
        }

        $externalFile = $document->files()->where('file_type', 'EXTERNAL')->latest('version_no')->first();
        if (! $externalFile || ! $externalFile->external_url) {
            throw ValidationException::withMessages(['file' => 'ไม่พบลิงก์ QR ต้นฉบับของเอกสารนี้']);
        }

        $path = $this->files->store($file, 'incoming_qr_docs');
        try {
            $oldPath = $externalFile->file_path;
            $externalFile->update([
                'original_name' => $file->getClientOriginalName(),
                'file_path' => $path,
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
                'sha256' => hash_file('sha256', $file->getRealPath()),
                'uploaded_by' => $actorId,
                'downloaded_at' => now(),
                'download_error' => null,
            ]);
            if ($oldPath && $oldPath !== $path) {
                $this->files->delete($oldPath);
            }
        } catch (\Throwable $error) {
            $this->files->delete($path);
            throw $error;
        }

        return $document->fresh();
    }

    public function delete(Document $document, int $actorId): void
    {
        $this->guard->ensureEnabled();
        if ($document->created_by !== $actorId || ! in_array($document->status, ['DRAFT', 'REJECTED'], true)) {
            abort(403);
        }
        $document->delete();
    }

    public function allocateNumber(Document $document, int $actorId, int $runningNumber, string $formatted, bool $confirm = true): Document
    {
        $this->guard->ensureEnabled();
        $numbered = $this->numbering->allocate(
            $document,
            $actorId,
            $runningNumber,
            $formatted,
            $this->scope($document->doc_type, (int) $document->created_by)
        );
        $numberingData = [
            'number_status' => $confirm ? 'CONFIRMED' : 'RESERVED',
            'finalized_at' => $confirm ? now() : null,
        ];
        if ($confirm && $numbered->doc_type === 'outgoing' && $numbered->source_type === 'system') {
            $numberingData['document_date'] = now()->toDateString();
        }
        $numbered->update($numberingData);
        if ($confirm && $numbered->status === 'APPROVED') {
            $numbered->update(['status' => 'COMPLETED']);
        }

        return $numbered->fresh();
    }

    public function reserveSlot(string $type, int $actorId, int $runningNumber, string $formatted): Document
    {
        $this->guard->ensureEnabled();

        return DB::connection('mysql_v2')->transaction(function () use ($type, $actorId, $runningNumber, $formatted) {
            $document = Document::create([
                'uuid' => (string) Str::uuid(),
                'document_type_id' => DocumentType::where('code', strtoupper($type))->value('id'),
                'document_date' => now()->toDateString(), 'title' => '[จองเลขเอกสารล่วงหน้า]',
                'status' => 'REGISTERED', 'created_by' => $actorId,
            ]);

            return $this->numbering->allocate($document, $actorId, $runningNumber, $formatted, $this->scope($type, $actorId));
        }, 3);
    }

    /**
     * รับงานหรือส่งต่อให้ผู้รับงานได้หลายคน
     *
     * แต่ละผู้รับมีแถว assignment ของตนเองเพื่อให้ตอบรับงานได้แยกกัน
     * การส่งต่ออนุญาตเฉพาะหัวหน้าส่วนราชการหรือหัวหน้าสำนักปลัด
     * โดยไม่กระทบผู้รับงานคนอื่นของเอกสารฉบับเดียวกัน
     */
    public function assign(Document $document, LegacyUser $actor, string $action, array|int|null $delegateIds = null): Document
    {
        $this->guard->ensureEnabled();

        return DB::connection('mysql_v2')->transaction(function () use ($document, $actor, $action, $delegateIds) {
            if ($action === 'delegate' && ! $actor->hasRole('head')) {
                abort(403, 'เฉพาะหัวหน้าส่วนราชการหรือหัวหน้าสำนักปลัดเท่านั้นที่ส่งต่องานได้');
            }

            $activeAssignments = $document->assignments()
                ->whereIn('status', ['PENDING', 'ACKNOWLEDGED', 'IN_PROGRESS']);

            // เลือกงานที่เป็นของผู้ดำเนินการก่อนเสมอ เพื่อรองรับเอกสารที่มอบหมายหลายคน
            $assignment = (clone $activeAssignments)
                ->where('assigned_user_id', $actor->id)
                ->latest('id')
                ->lockForUpdate()
                ->first();

            // หัวหน้าสามารถดำเนินการงานที่ส่งถึงหน่วยงานและยังไม่ได้ระบุผู้รับได้
            if (! $assignment && $actor->hasRole('head')) {
                $unitNames = array_filter([$actor->department, $actor->division, $actor->work_unit]);
                $assignment = (clone $activeAssignments)
                    ->whereNull('assigned_user_id')
                    ->whereHas('unit', fn ($units) => $units->whereIn('name', $unitNames))
                    ->latest('id')
                    ->lockForUpdate()
                    ->first();
            }

            abort_unless($assignment, 403);
            $assignmentStatus = $assignment->status instanceof \BackedEnum
                ? $assignment->status->value
                : (string) $assignment->status;
            if (! in_array((string) $document->status, ['APPROVED', 'COMPLETED', 'ARCHIVED'], true)
                || ! in_array($assignmentStatus, ['PENDING', 'ACKNOWLEDGED', 'IN_PROGRESS'], true)) {
                abort(403, 'เอกสารยังไม่ผ่านการอนุมัติขั้นสุดท้าย');
            }
            if ($action === 'accept') {
                $assignment->update([
                    'assigned_user_id' => $actor->id, 'status' => 'ACKNOWLEDGED',
                    'acknowledged_at' => now(),
                ]);
            } else {
                $delegateIds = collect(is_array($delegateIds) ? $delegateIds : [$delegateIds])
                    ->filter(fn ($id) => filter_var($id, FILTER_VALIDATE_INT) !== false)
                    ->map(fn ($id) => (int) $id)
                    ->unique()
                    ->values();
                if ($delegateIds->isEmpty()) {
                    throw ValidationException::withMessages(['delegate_user_ids' => 'กรุณาเลือกบุคลากรอย่างน้อย 1 คน']);
                }
                $delegates = User::with('organizationUnit.parent')->whereIn('id', $delegateIds)->get()->keyBy('id');
                if ($delegates->count() !== $delegateIds->count()
                    || $delegates->contains(fn (User $delegate) => $delegate->department !== $actor->department || $delegate->id === $actor->id)) {
                    throw ValidationException::withMessages(['delegate_user_id' => 'เลือกได้เฉพาะบุคลากรในฝ่ายเดียวกัน']);
                }

                $firstDelegate = $delegates->get($delegateIds->first());
                $assignment->update([
                    'assigned_user_id' => $firstDelegate->id, 'delegated_by' => $actor->id,
                    'status' => 'PENDING', 'assigned_at' => now(), 'acknowledged_at' => null,
                ]);

                // แถวแรกใช้แถวเดิม ส่วนคนที่เหลือมีแถวของตนเองเพื่อให้รับงานแยกกันได้
                $delegateIds->skip(1)->each(function (int $delegateId) use ($document, $assignment, $actor): void {
                    $document->assignments()->create([
                        'assigned_user_id' => $delegateId,
                        'assigned_unit_id' => $assignment->assigned_unit_id,
                        'assigned_by' => $assignment->assigned_by ?? $actor->id,
                        'delegated_by' => $actor->id,
                        'status' => 'PENDING',
                        'assigned_at' => now(),
                    ]);
                });
            }

            return $document->fresh();
        }, 3);
    }

    public function requestAccess(Document $document, int $actorId, ?string $reason = null): DocumentAccessRequest
    {
        $this->guard->ensureEnabled();

        return DocumentAccessRequest::updateOrCreate(
            ['document_id' => $document->id, 'requested_by' => $actorId],
            ['reason' => $reason, 'status' => 'PENDING', 'reviewed_by' => null, 'reviewed_at' => null, 'expires_at' => null]
        );
    }

    public function decideAccess(DocumentAccessRequest $accessRequest, LegacyUser $actor, string $status): DocumentAccessRequest
    {
        $this->guard->ensureEnabled();
        if ($accessRequest->document->created_by !== $actor->id && ! $actor->hasAnyRole(['super-admin', 'saraban'])) {
            abort(403);
        }
        $accessRequest->update([
            'status' => strtoupper($status), 'reviewed_by' => $actor->id,
            'reviewed_at' => now(), 'expires_at' => $status === 'approved' ? now()->addDays(30) : null,
        ]);

        return $accessRequest->fresh(['document', 'requester']);
    }

    private function documentColumns(array $data, int $actorId, string $type, bool $creating = true): array
    {
        $columns = [
            'document_type_id' => DocumentType::where('code', strtoupper($type))->value('id'),
            'document_category_id' => $this->categoryId($data['doc_type_category'] ?? null),
            'priority_id' => $this->lookupByName(DocumentPriority::class, $data['doc_speed'] ?? 'ปกติ'),
            'confidentiality_id' => $this->lookupByName(DocumentConfidentiality::class, $data['doc_secret'] ?? 'ปกติ'),
            'document_number' => $data['doc_number'] ?? null,
            'receive_number' => $data['receive_number'] ?? null,
            'document_date' => $data['doc_date'] ?? null,
            'receive_date' => $data['receive_date'] ?? null,
            'title' => $data['title'], 'content' => $data['content'] ?? null,
            'source_type' => $data['document_source'] ?? $data['source_type'] ?? 'upload',
            'sender_address' => $data['sender_address'] ?? null,
            'closing_text' => $data['closing_text'] ?? null,
            'contact_line' => $data['contact_line'] ?? null,
            'attachment_description' => $data['attachment'] ?? $data['attachment_description'] ?? null,
            'number_status' => ! empty($data['doc_number']) ? 'RESERVED' : 'UNASSIGNED',
            'sender_name' => $data['doc_from'] ?? null, 'recipient_name' => $data['doc_to'] ?? null,
            'signer_name' => $data['signer_name'] ?? null, 'reference_text' => $data['reference_doc'] ?? null,
            'remark' => $data['remark'] ?? null,
        ];
        if ($creating) {
            $columns += ['uuid' => (string) Str::uuid(), 'status' => 'DRAFT', 'created_by' => $actorId];
        } else {
            unset($columns['document_type_id']);
        }

        return $columns;
    }

    private function createWorkflow(Document $document, int $actorId, array $routeUserIds, ?int $signerId = null): void
    {
        $definition = WorkflowDefinition::firstOrCreate(
            ['code' => 'DOCUMENT_DYNAMIC', 'version' => 1],
            ['name' => 'Dynamic document approval', 'resource_type' => 'DOCUMENT', 'is_active' => true]
        );
        $instance = WorkflowInstance::create([
            'workflow_definition_id' => $definition->id, 'document_id' => $document->id,
            'status' => 'PENDING', 'current_step' => 1, 'started_at' => now(),
        ]);
        foreach (array_merge([$actorId], $routeUserIds) as $index => $userId) {
            $isSigner = $document->doc_type === 'outgoing' && $signerId && (int) $userId === $signerId;
            WorkflowStep::create([
                'workflow_instance_id' => $instance->id, 'step_order' => $index + 1,
                'step_name' => $index === 0 ? 'Document creator' : ($isSigner ? 'Document signer' : 'Document reviewer '.($index + 1)),
                'action_type' => $index === 0 ? 'SUBMIT' : ($isSigner ? 'SIGNATURE' : 'APPROVAL'),
                'assigned_user_id' => $userId, 'status' => 'PENDING',
            ]);
        }
    }

    private function signerIdFromName(string $signerName): ?int
    {
        $name = trim((string) (preg_split('/\s*[—–]\s*/u', $signerName, 2)[0] ?? ''));

        return $name === '' ? null : LegacyUser::where('name', $name)->value('id');
    }

    private function syncOutgoingSignerStep(WorkflowInstance $workflow, int $signerId, ?int $previousSignerId): void
    {
        $reviewerIds = $workflow->steps()->where('step_order', '>', 1)->orderBy('step_order')->get()
            ->reject(fn (WorkflowStep $step) => $step->action_type === 'SIGNATURE'
                || (int) $step->assigned_user_id === $signerId
                || ($previousSignerId && (int) $step->assigned_user_id === $previousSignerId))
            ->pluck('assigned_user_id')
            ->map(fn ($id) => (int) $id)
            ->values();

        $workflow->steps()->where('step_order', '>', 1)->delete();
        foreach ([...$reviewerIds->all(), $signerId] as $index => $userId) {
            $isSigner = (int) $userId === $signerId;
            WorkflowStep::create([
                'workflow_instance_id' => $workflow->id,
                'step_order' => $index + 2,
                'step_name' => $isSigner ? 'Document signer' : 'Document reviewer '.($index + 2),
                'action_type' => $isSigner ? 'SIGNATURE' : 'APPROVAL',
                'assigned_user_id' => $userId,
                'status' => 'PENDING',
            ]);
        }
    }

    /** ทำให้เอกสาร V2 เก่าก็จบที่ผู้ลงนามที่เลือก และจำแนกขั้นลงนามออกจากขั้นพิจารณา */
    private function ensureOutgoingSignerStep(Document $document): void
    {
        if ($document->doc_type !== 'outgoing') {
            return;
        }

        DB::connection('mysql_v2')->transaction(function () use ($document) {
            $workflow = WorkflowInstance::where('document_id', $document->id)->lockForUpdate()->firstOrFail();
            // steps() มี orderBy(step_order) เริ่มต้น จึงต้อง reorder ก่อนหาแถวสุดท้ายจริง
            $lastStep = $workflow->steps()->reorder()->orderByDesc('step_order')->lockForUpdate()->first();
            // ข้อมูล V2 รุ่นเก่าอาจมีเพียงชื่อข้อความ ให้คงผู้รับขั้นสุดท้ายเดิมไว้เป็นผู้ลงนาม
            $signerId = $this->signerIdFromName((string) $document->signer_name)
                ?: (int) $lastStep?->assigned_user_id;
            if (! $signerId) {
                throw ValidationException::withMessages([
                    'signer_name' => 'ไม่พบบัญชีผู้ลงนาม กรุณาแก้ไขและเลือกผู้ลงนามใหม่ก่อนส่งเรื่อง',
                ]);
            }

            if ($lastStep && (int) $lastStep->assigned_user_id === $signerId) {
                $lastStep->update(['step_name' => 'Document signer', 'action_type' => 'SIGNATURE']);

                return;
            }

            WorkflowStep::create([
                'workflow_instance_id' => $workflow->id,
                'step_order' => ((int) $lastStep?->step_order) + 1,
                'step_name' => 'Document signer',
                'action_type' => 'SIGNATURE',
                'assigned_user_id' => $signerId,
                'status' => 'PENDING',
            ]);
        }, 3);
    }

    private function storeFile(Document $document, UploadedFile $file, string $type, string $folder, int $actorId): void
    {
        $version = ((int) $document->files()->where('file_type', $type)->max('version_no')) + 1;
        $path = $this->files->store($file, $folder);
        try {
            DocumentFile::create([
                'document_id' => $document->id, 'file_type' => $type,
                'original_name' => $file->getClientOriginalName(), 'file_path' => $path,
                'mime_type' => $file->getMimeType(), 'file_size' => $file->getSize(),
                'sha256' => hash_file('sha256', $file->getRealPath()), 'version_no' => $version,
                'uploaded_by' => $actorId,
            ]);
        } catch (\Throwable $error) {
            $this->files->delete($path);
            throw $error;
        }
    }

    private function validRouteUsers(array $ids, int $actorId): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if ($ids === [] || in_array($actorId, $ids, true)
            || User::whereIn('id', $ids)->count() !== count($ids)) {
            throw ValidationException::withMessages(['routing_users' => 'เส้นทางผู้พิจารณาไม่ถูกต้อง']);
        }

        return $ids;
    }

    private function categoryId(?string $name): ?int
    {
        $name = trim((string) $name);
        if ($name === '') {
            return null;
        }

        return DocumentCategory::firstOrCreate(
            ['name' => $name], ['code' => 'LEGACY_'.strtoupper(substr(hash('sha256', $name), 0, 16)), 'is_active' => true]
        )->id;
    }

    private function lookupByName(string $model, string $name): ?int
    {
        return $model::where('name', $name)->value('id') ?: $model::where('code', 'NORMAL')->value('id');
    }

    private function folderFor(string $type): string
    {
        return match ($type) {
            'incoming' => 'incoming_docs', 'outgoing' => 'outgoing_docs', default => 'attachments'
        };
    }

    private function scope(string $type, int $actorId): string
    {
        return $type === 'internal' ? (User::find($actorId)?->department ?: 'organization') : 'organization';
    }
}
