<?php

namespace App\Services\V2;

use App\Services\DocumentFileStorage;
use App\Models\User as LegacyUser;
use App\Models\V2\Document;
use App\Models\V2\DocumentCategory;
use App\Models\V2\DocumentAccessRequest;
use App\Models\V2\DocumentConfidentiality;
use App\Models\V2\DocumentFile;
use App\Models\V2\DocumentPriority;
use App\Models\V2\DocumentType;
use App\Models\V2\OrganizationUnit;
use App\Models\V2\User;
use App\Models\V2\WorkflowDefinition;
use App\Models\V2\WorkflowInstance;
use App\Models\V2\WorkflowStep;
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
            $this->createWorkflow($document, $actorId, $routeUserIds);

            if (! empty($data['running_number'])) {
                $formatted = (string) ($type === 'incoming'
                    ? ($data['receive_number'] ?? '')
                    : ($data['doc_number'] ?? ''));
                $this->numbering->allocate($document, $actorId, (int) $data['running_number'], $formatted, $this->scope($type, $actorId));
            }

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
            $locked = Document::whereKey($document->id)->lockForUpdate()->firstOrFail();
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
            }

            return $locked->fresh();
        }, 3);
    }

    public function submit(Document $document, User $actor): Document
    {
        $this->guard->ensureEnabled();
        if ($document->created_by !== $actor->id || $document->status !== 'DRAFT') { abort(403); }
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
        $this->workflow->act($document->workflow()->firstOrFail(), $actor, $action, $comment, null, $evidenceAction);
        $reviewed = $document->fresh();
        if (! $approved) {
            $document->update(['rejection_reason' => $comment]);
        } elseif ($reviewed->status === 'APPROVED' && $reviewed->doc_type === 'outgoing') {
            // หนังสือส่งออกมีเลขตั้งแต่สร้างเอกสาร การอนุมัติขั้นสุดท้ายจึงจบกระบวนการทันที
            $document->update(['status' => 'COMPLETED']);
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
        if ($document->created_by !== $actorId || ! in_array($document->status, ['DRAFT', 'REJECTED'], true)) { abort(403); }
        $this->storeFile($document, $file, 'MAIN', $this->folderFor($document->doc_type), $actorId);
        return $document->fresh();
    }

    public function attachExternalQrCopy(Document $document, UploadedFile $file, int $actorId): Document
    {
        $this->guard->ensureEnabled();
        if ($document->created_by !== $actorId || ! in_array($document->status, ['DRAFT', 'REJECTED'], true)) { abort(403); }

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
        if ($document->created_by !== $actorId || ! in_array($document->status, ['DRAFT', 'REJECTED'], true)) { abort(403); }
        $document->delete();
    }

    public function allocateNumber(Document $document, int $actorId, int $runningNumber, string $formatted): Document
    {
        $this->guard->ensureEnabled();
        $numbered = $this->numbering->allocate(
            $document,
            $actorId,
            $runningNumber,
            $formatted,
            $this->scope($document->doc_type, (int) $document->created_by)
        );
        if ($numbered->status === 'APPROVED') { $numbered->update(['status' => 'COMPLETED']); }
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

    public function assign(Document $document, LegacyUser $actor, string $action, ?int $delegateId = null): Document
    {
        $this->guard->ensureEnabled();
        return DB::connection('mysql_v2')->transaction(function () use ($document, $actor, $action, $delegateId) {
            $assignment = $document->assignments()->latest('id')->lockForUpdate()->firstOrFail();
            $assignmentStatus = $assignment->status instanceof \BackedEnum
                ? $assignment->status->value
                : (string) $assignment->status;
            if (! in_array((string) $document->status, ['APPROVED', 'COMPLETED', 'ARCHIVED'], true)
                || ! in_array($assignmentStatus, ['PENDING', 'ACKNOWLEDGED', 'IN_PROGRESS'], true)) {
                abort(403, 'เอกสารยังไม่ผ่านการอนุมัติขั้นสุดท้าย');
            }
            $unitNames = array_filter([$actor->department, $actor->division, $actor->work_unit]);
            $isUnitHead = $actor->hasRole('head') && $assignment->unit && in_array($assignment->unit->name, $unitNames, true);
            if ($assignment->assigned_user_id !== $actor->id && ! $isUnitHead) { abort(403); }
            if ($action === 'accept') {
                $assignment->update([
                    'assigned_user_id' => $actor->id, 'status' => 'ACKNOWLEDGED',
                    'acknowledged_at' => now(),
                ]);
            } else {
                $delegate = User::findOrFail($delegateId);
                if ($delegate->department !== $actor->department || $delegate->id === $actor->id) {
                    throw ValidationException::withMessages(['delegate_user_id' => 'เลือกได้เฉพาะบุคลากรในฝ่ายเดียวกัน']);
                }
                $assignment->update([
                    'assigned_user_id' => $delegate->id, 'delegated_by' => $actor->id,
                    'status' => 'PENDING', 'assigned_at' => now(), 'acknowledged_at' => null,
                ]);
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
        if ($accessRequest->document->created_by !== $actor->id && ! $actor->hasAnyRole(['super-admin', 'saraban'])) { abort(403); }
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

    private function createWorkflow(Document $document, int $actorId, array $routeUserIds): void
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
            WorkflowStep::create([
                'workflow_instance_id' => $instance->id, 'step_order' => $index + 1,
                'step_name' => $index === 0 ? 'Document creator' : 'Document reviewer '.($index + 1),
                'action_type' => $index === 0 ? 'SUBMIT' : 'APPROVAL',
                'assigned_user_id' => $userId, 'status' => 'PENDING',
            ]);
        }
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
        if ($name === '') { return null; }
        return DocumentCategory::firstOrCreate(
            ['name' => $name], ['code' => 'LEGACY_'.strtoupper(substr(hash('sha256', $name), 0, 16)), 'is_active' => true]
        )->id;
    }

    private function lookupByName(string $model, string $name): ?int
    {
        return $model::where('name', $name)->value('id') ?: $model::where('code', 'NORMAL')->value('id');
    }

    private function folderFor(string $type): string { return match ($type) { 'incoming' => 'incoming_docs', 'outgoing' => 'outgoing_docs', default => 'attachments' }; }
    private function scope(string $type, int $actorId): string { return $type === 'internal' ? (User::find($actorId)?->department ?: 'organization') : 'organization'; }
}
