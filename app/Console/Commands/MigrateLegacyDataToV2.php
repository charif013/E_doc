<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MigrateLegacyDataToV2 extends Command
{
    protected $signature = 'edoc:v2-migrate
        {--source=mysql_legacy_readonly : Legacy database connection}
        {--target=mysql_v2 : V2 database connection}
        {--commit : Write to V2; without this flag the command is read-only}';

    protected $description = 'Idempotently copy legacy eDOC data to the isolated V2 database';

    private ConnectionInterface $source;

    private ConnectionInterface $target;

    public function handle(): int
    {
        $this->source = DB::connection((string) $this->option('source'));
        $this->target = DB::connection((string) $this->option('target'));

        $summary = [
            ['users', $this->source->table('users')->count()],
            ['documents', $this->source->table('documents')->count()],
            ['document routes', $this->source->table('document_routes')->count()],
            ['leave requests', $this->source->table('leave_requests')->count()],
            ['rooms', $this->source->table('rooms')->count()],
            ['room bookings', $this->source->table('room_bookings')->count()],
            ['audit logs', $this->source->table('audit_logs')->count()],
        ];
        $this->table(['Resource', 'Source rows'], $summary);

        if (! $this->option('commit')) {
            $this->warn('Dry run only. Re-run with --commit to write exclusively to the V2 connection.');

            return self::SUCCESS;
        }

        $this->target->transaction(function () {
            $this->copyIdentityAndAccess();
            $this->seedDocumentMasterData();
            $this->copyDocuments();
            $this->copyLeaves();
            $this->copyWorkflow();
            $this->copyNumbers();
            $this->copyBookings();
            $this->copyAuditLogs();
        }, 3);

        $this->info('Legacy data copied to V2. The source database was not modified.');

        return self::SUCCESS;
    }

    private function copyIdentityAndAccess(): void
    {
        $unitCache = [];
        $positionCache = [];
        foreach ($this->source->table('users')->orderBy('id')->get() as $user) {
            $parentId = null;
            foreach ([['DEPARTMENT', $user->department], ['DIVISION', $user->division], ['WORK_UNIT', $user->work_unit]] as [$type, $name]) {
                $name = trim((string) $name);
                if ($name === '') {
                    continue;
                }
                $key = $parentId.'|'.$type.'|'.$name;
                if (! isset($unitCache[$key])) {
                    $this->target->table('organization_units')->updateOrInsert(
                        ['parent_id' => $parentId, 'unit_type' => $type, 'name' => $name],
                        ['code' => null, 'is_active' => true, 'updated_at' => now(), 'created_at' => now()]
                    );
                    $unitCache[$key] = $this->target->table('organization_units')
                        ->where(['parent_id' => $parentId, 'unit_type' => $type, 'name' => $name])->value('id');
                }
                $parentId = $unitCache[$key];
            }

            $positionId = null;
            $positionName = trim((string) $user->position);
            if ($positionName !== '') {
                if (! isset($positionCache[$positionName])) {
                    $this->target->table('positions')->updateOrInsert(
                        ['name' => $positionName],
                        ['code' => null, 'is_active' => true, 'updated_at' => now(), 'created_at' => now()]
                    );
                    $positionCache[$positionName] = $this->target->table('positions')->where('name', $positionName)->value('id');
                }
                $positionId = $positionCache[$positionName];
            }

            $this->target->table('users')->updateOrInsert(['id' => $user->id], [
                'name_prefix' => $user->name_prefix,
                'name' => $user->name,
                'gender' => $user->gender,
                'email' => $user->email,
                'line_id' => $user->line_id,
                'line_friend_status' => (bool) data_get($user, 'line_friend_status', !empty($user->line_id)),
                'line_connected_at' => data_get($user, 'line_connected_at'),
                'line_followed_at' => data_get($user, 'line_followed_at'),
                'department' => $user->department,
                'division' => $user->division,
                'work_unit' => $user->work_unit,
                'position' => $user->position,
                'signature' => $user->signature,
                'organization_unit_id' => $parentId,
                'position_id' => $positionId,
                'email_verified_at' => $user->email_verified_at,
                'password' => $user->password,
                'pin' => $user->pin,
                'pin_reset_requested' => (bool) $user->pin_reset_requested,
                'remember_token' => $user->remember_token,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
                'deleted_at' => $user->deleted_at,
            ]);
            $this->copyUserSignature($user);
        }

        foreach (['roles', 'permissions'] as $table) {
            foreach ($this->source->table($table)->get() as $row) {
                $this->target->table($table)->updateOrInsert(['id' => $row->id], (array) $row);
            }
        }
        foreach (['role_has_permissions', 'model_has_permissions', 'model_has_roles'] as $table) {
            $rows = $this->source->table($table)->get()->map(fn ($row) => (array) $row)->all();
            if ($rows !== []) {
                $this->target->table($table)->insertOrIgnore($rows);
            }
        }
    }

    private function copyUserSignature(object $user): void
    {
        if (! $user->signature) {
            return;
        }
        $encoded = preg_replace('/^data:image\/[a-zA-Z0-9.+-]+;base64,/', '', (string) $user->signature);
        $binary = base64_decode((string) $encoded, true);
        if ($binary === false) {
            $this->warn("Skipped invalid signature for user {$user->id}");

            return;
        }
        $hash = hash('sha256', $binary);
        $path = "private/v2-signatures/{$user->id}-{$hash}.png";
        Storage::disk('local')->put($path, $binary);
        $this->target->table('user_signatures')->updateOrInsert(
            ['user_id' => $user->id, 'sha256' => $hash],
            ['file_path' => $path, 'is_active' => true, 'valid_from' => $user->created_at,
                'valid_until' => null, 'revoked_at' => null, 'created_at' => now(), 'updated_at' => now()]
        );
    }

    private function seedDocumentMasterData(): void
    {
        $this->upsertMaster('document_types', [
            ['code' => 'INCOMING', 'name' => 'หนังสือรับเข้า', 'direction' => 'INCOMING'],
            ['code' => 'OUTGOING', 'name' => 'หนังสือส่งออก', 'direction' => 'OUTGOING'],
            ['code' => 'INTERNAL', 'name' => 'หนังสือภายใน', 'direction' => 'INTERNAL'],
        ]);
        $this->upsertMaster('document_priorities', [
            ['code' => 'NORMAL', 'name' => 'ปกติ', 'level_no' => 1],
            ['code' => 'URGENT', 'name' => 'ด่วน', 'level_no' => 2],
            ['code' => 'VERY_URGENT', 'name' => 'ด่วนมาก', 'level_no' => 3],
            ['code' => 'HIGHEST', 'name' => 'ด่วนที่สุด', 'level_no' => 4],
        ]);
        $this->upsertMaster('document_confidentialities', [
            ['code' => 'NORMAL', 'name' => 'ปกติ', 'level_no' => 1],
            ['code' => 'CONFIDENTIAL', 'name' => 'ลับ', 'level_no' => 2],
            ['code' => 'SECRET', 'name' => 'ลับมาก', 'level_no' => 3],
            ['code' => 'TOP_SECRET', 'name' => 'ลับที่สุด', 'level_no' => 4],
        ]);
    }

    private function upsertMaster(string $table, array $rows): void
    {
        foreach ($rows as $row) {
            $this->target->table($table)->updateOrInsert(['code' => $row['code']], $row + [
                'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    private function copyDocuments(): void
    {
        foreach ($this->source->table('documents')->orderBy('id')->get() as $document) {
            $typeCode = strtoupper((string) $document->doc_type);
            $typeId = $this->target->table('document_types')->where('code', $typeCode)->value('id');
            $categoryId = $this->categoryId($document->doc_type_category);
            $priorityId = $this->masterId('document_priorities', $this->priorityCode($document->doc_speed));
            $confidentialityId = $this->masterId('document_confidentialities', $this->confidentialityCode($document->doc_secret));

            $this->target->table('documents')->updateOrInsert(['id' => $document->id], [
                'uuid' => $document->uuid ?: (string) Str::uuid(),
                'document_type_id' => $typeId,
                'document_category_id' => $categoryId,
                'priority_id' => $priorityId,
                'confidentiality_id' => $confidentialityId,
                'document_number' => $document->doc_number,
                'receive_number' => $document->receive_number,
                'document_date' => $document->doc_date,
                'receive_date' => $document->receive_date,
                'title' => $document->title,
                'content' => $document->content,
                'sender_name' => $document->doc_from,
                'recipient_name' => $document->doc_to,
                'signer_name' => $document->signer_name,
                'reference_text' => $document->reference_doc,
                'remark' => $document->remark,
                'rejection_reason' => $document->reject_reason,
                'status' => $this->documentStatus($document->status),
                'created_by' => $document->created_by,
                'created_at' => $document->created_at,
                'updated_at' => $document->updated_at,
                'deleted_at' => null,
            ]);
            $this->copyDocumentFiles($document);

            if ($document->assigned_user_id || trim((string) $document->assigned_to) !== '') {
                $unitId = $document->assigned_user_id ? null : $this->unitByName($document->assigned_to);
                $this->target->table('document_assignments')->updateOrInsert(
                    ['document_id' => $document->id, 'assigned_at' => $document->assigned_at],
                    ['assigned_user_id' => $document->assigned_user_id, 'assigned_unit_id' => $unitId,
                        'assigned_by' => $document->delegated_by ?: $document->created_by,
                        'delegated_by' => $document->delegated_by,
                        'status' => $this->assignmentStatus($document->assignment_status, $document->acknowledged_at),
                        'note' => $document->remark, 'acknowledged_at' => $document->acknowledged_at,
                        'completed_at' => null, 'created_at' => $document->assigned_at ?: $document->created_at,
                        'updated_at' => $document->updated_at]
                );
            }
        }

        foreach ($this->source->table('document_access_requests')->get() as $request) {
            $this->target->table('document_access_requests')->updateOrInsert(
                ['document_id' => $request->document_id, 'requested_by' => $request->user_id],
                ['status' => strtoupper($request->status), 'reason' => null, 'reviewed_by' => null,
                    'reviewed_at' => null, 'expires_at' => null, 'created_at' => $request->created_at,
                    'updated_at' => $request->updated_at]
            );
        }
    }

    private function copyDocumentFiles(object $document): void
    {
        $files = [
            ['MAIN', $document->attachment_path, null, basename((string) $document->attachment_path), null, null, null],
            ['SIGNED', $document->signed_path, null, basename((string) $document->signed_path), null, null, null],
            ['EXTERNAL', $document->external_attachment_path, $document->external_url,
                $document->external_original_name ?: basename((string) $document->external_attachment_path),
                $document->external_mime_type, $document->external_file_size, $document->external_sha256],
        ];
        foreach ($files as [$type, $path, $url, $name, $mime, $size, $hash]) {
            if (! $path && ! $url) {
                continue;
            }
            $this->target->table('document_files')->updateOrInsert(
                ['document_id' => $document->id, 'file_type' => $type, 'version_no' => 1],
                ['original_name' => $name ?: 'document', 'file_path' => $path, 'external_url' => $url,
                    'mime_type' => $mime, 'file_size' => $size, 'sha256' => $hash,
                    'uploaded_by' => $document->created_by, 'downloaded_at' => $document->external_downloaded_at,
                    'download_error' => $document->external_download_error,
                    'created_at' => $document->created_at, 'updated_at' => $document->updated_at]
            );
        }
    }

    private function copyLeaves(): void
    {
        foreach ($this->source->table('leave_requests')->orderBy('id')->get() as $leave) {
            $typeId = $this->leaveTypeId($leave->leave_type);
            $this->target->table('leave_requests')->updateOrInsert(['id' => $leave->id], [
                'user_id' => $leave->user_id, 'leave_type_id' => $typeId,
                'start_date' => $leave->start_date, 'end_date' => $leave->end_date,
                'total_days' => number_format((float) $leave->total_days, 2, '.', ''),
                'reason' => $leave->reason, 'contact_info' => $leave->contact_info,
                'delegate_user_id' => $leave->delegate_id,
                'created_at' => $leave->created_at,
                'updated_at' => $leave->updated_at, 'deleted_at' => null,
            ]);
        }
        foreach ($this->source->table('holidays')->get() as $holiday) {
            $this->target->table('holidays')->updateOrInsert(['id' => $holiday->id], [
                'holiday_date' => $holiday->holiday_date, 'name' => $holiday->name,
                'holiday_type' => 'PUBLIC', 'source' => $holiday->source,
                'created_at' => $holiday->created_at, 'updated_at' => $holiday->updated_at,
            ]);
        }
    }

    private function copyWorkflow(): void
    {
        $documentDefinition = $this->definitionId('LEGACY_DOCUMENT', 'Legacy document workflow', 'DOCUMENT');
        $leaveDefinition = $this->definitionId('APPLICATION_LEAVE', 'Application leave workflow', 'LEAVE');

        foreach ($this->source->table('documents')->get() as $document) {
            $instanceId = $this->instanceId($documentDefinition, $document->id, null,
                $this->workflowStatus($document->status), $document->current_step, $document->created_at, $document->updated_at);
            foreach ($this->source->table('document_routes')->where('document_id', $document->id)->orderBy('step_order')->get() as $route) {
                $this->target->table('workflow_steps')->updateOrInsert(
                    ['workflow_instance_id' => $instanceId, 'step_order' => $route->step_order],
                    ['workflow_definition_step_id' => null, 'step_name' => 'Document route '.$route->step_order,
                        'action_type' => 'APPROVAL', 'assigned_role_id' => null, 'assigned_user_id' => $route->user_id,
                        'status' => $this->stepStatus($route->status), 'comment' => $route->comment,
                        'acted_at' => $route->actioned_at, 'created_at' => $route->created_at, 'updated_at' => $route->updated_at]
                );
            }
            $this->copyDocumentEvidence($document, $instanceId);
        }

        foreach ($this->source->table('leave_requests')->get() as $leave) {
            $currentStep = $this->legacyLeaveCurrentStep($leave->workflow_status);
            $instanceId = $this->instanceId($leaveDefinition, null, $leave->id,
                $this->workflowStatus($leave->status), $currentStep, $leave->created_at, $leave->updated_at);
            $steps = [
                [1, 'Delegate response', 'ACCEPTANCE', $leave->delegate_id, $leave->delegate_status, $leave->delegate_responded_at],
                [2, 'Department head approval', 'APPROVAL', $leave->head_id ?: $leave->supervisor_id, $leave->head_status, $leave->head_at ?: $leave->supervisor_approved_at],
                [3, 'HR inspection', 'APPROVAL', $leave->inspector_id, $leave->inspector_status, $leave->inspector_at],
                [4, 'Leave number allocation', 'NUMBERING', $leave->numbered_by, $leave->numbered_at ? 'approved' : null, $leave->numbered_at],
                [5, 'Palad approval', 'APPROVAL', $leave->palad_id, $leave->palad_status, $leave->palad_at ?: $leave->palad_approved_at],
                [6, 'Executive approval', 'APPROVAL', $leave->nayok_id, $leave->nayok_status, $leave->nayok_at ?: $leave->nayok_approved_at],
            ];
            foreach ($steps as [$order, $name, $actionType, $userId, $status, $actedAt]) {
                $canonicalStatus = $this->stepStatus($status);
                if (in_array($canonicalStatus, ['APPROVED', 'REJECTED'], true) && (! $userId || ! $actedAt)) {
                    $canonicalStatus = $currentStep === $order ? 'PENDING' : 'SKIPPED';
                }
                $this->target->table('workflow_steps')->updateOrInsert(
                    ['workflow_instance_id' => $instanceId, 'step_order' => $order],
                    ['workflow_definition_step_id' => null, 'step_name' => $name, 'action_type' => $actionType,
                        'assigned_role_id' => null, 'assigned_user_id' => $userId,
                        'status' => $order === 1 && ! $userId ? 'SKIPPED' : $canonicalStatus,
                        'comment' => null, 'acted_at' => $actedAt,
                        'created_at' => $leave->created_at, 'updated_at' => $leave->updated_at]
                );
            }
            $this->copyLeaveEvidence($leave, $instanceId);
        }
    }

    private function copyLeaveEvidence(object $leave, int $instanceId): void
    {
        $actions = [
            [1, strtolower((string) $leave->delegate_status) === 'declined' ? 'DECLINED' : 'ACCEPTED',
                $leave->delegate_id, null, $leave->delegate_responded_at],
            [2, strtolower((string) $leave->head_status) === 'rejected' ? 'REJECTED' : 'APPROVED',
                $leave->head_id ?: $leave->supervisor_id, $leave->head_signature ?: $leave->supervisor_signature,
                $leave->head_at ?: $leave->supervisor_approved_at],
            [3, strtolower((string) $leave->inspector_status) === 'rejected' ? 'REJECTED' : 'APPROVED',
                $leave->inspector_id, $leave->inspector_signature, $leave->inspector_at],
            [4, 'NUMBER_ALLOCATED', $leave->numbered_by, null, $leave->numbered_at],
            [5, strtolower((string) $leave->palad_status) === 'rejected' ? 'REJECTED' : 'APPROVED',
                $leave->palad_id, $leave->palad_signature, $leave->palad_at ?: $leave->palad_approved_at],
            [6, strtolower((string) $leave->nayok_status) === 'rejected' ? 'REJECTED' : 'APPROVED',
                $leave->nayok_id, $leave->nayok_signature, $leave->nayok_at ?: $leave->nayok_approved_at],
        ];

        foreach ($actions as [$order, $action, $actorId, $signature, $actedAt]) {
            if (! $actorId || ! $actedAt) {
                continue;
            }

            $step = $this->target->table('workflow_steps')
                ->where('workflow_instance_id', $instanceId)->where('step_order', $order)->first();
            if (! $step || ! in_array($step->status, ['APPROVED', 'REJECTED'], true)) {
                continue;
            }

            $exists = $this->target->table('workflow_action_evidence')
                ->where('workflow_step_id', $step->id)->where('actor_id', $actorId)
                ->where('action', $action)->exists();
            if ($exists) {
                continue;
            }

            [$signaturePath, $signatureHash] = $signature
                ? $this->storeSignatureSnapshot((string) $signature, "leave-{$leave->id}-step-{$order}")
                : [null, null];
            $actor = $this->source->table('users')->where('id', $actorId)->first();
            $this->target->table('workflow_action_evidence')->insert([
                'workflow_step_id' => $step->id,
                'workflow_instance_id' => $instanceId,
                'resource_type' => 'LEAVE',
                'resource_id' => $leave->id,
                'workflow_code' => 'APPLICATION_LEAVE',
                'workflow_version' => 1,
                'step_order' => $order,
                'step_name' => $step->step_name,
                'actor_id' => $actorId,
                'action' => $action,
                'actor_name' => $actor?->name ?: 'Unknown user',
                'actor_position' => $actor?->position,
                'signature_path' => $signaturePath,
                'signature_snapshot' => null,
                'signature_sha256' => $signatureHash,
                'comment' => null,
                'ip_address' => null,
                'request_id' => "legacy-leave-{$leave->id}-step-{$order}",
                'acted_at' => $actedAt,
                'created_at' => $leave->created_at,
            ]);
        }
    }

    private function copyDocumentEvidence(object $document, int $instanceId): void
    {
        $approvals = [
            ['CREATED', $document->created_by, $document->creator_signature, $document->created_at, null],
            ['SUPERVISOR_APPROVED', $document->supervisor_id, $document->supervisor_signature,
                $document->supervisor_approved_at, $document->supervisor_comment],
            ['PALAD_APPROVED', $document->palad_id, $document->palad_signature,
                $document->palad_approved_at, $document->palad_comment],
            ['EXECUTIVE_APPROVED', $document->nayok_id, $document->nayok_signature,
                $document->nayok_approved_at, $document->nayok_comment],
        ];

        foreach ($approvals as [$action, $actorId, $signature, $actedAt, $comment]) {
            if (! $actorId || ! $signature) {
                continue;
            }

            $stepId = $this->target->table('workflow_steps')
                ->where('workflow_instance_id', $instanceId)
                ->where('assigned_user_id', $actorId)
                ->orderBy('step_order')
                ->value('id');
            if (! $stepId) {
                $nextOrder = ((int) $this->target->table('workflow_steps')
                    ->where('workflow_instance_id', $instanceId)->max('step_order')) + 1;
                $stepId = $this->target->table('workflow_steps')->insertGetId([
                    'workflow_instance_id' => $instanceId,
                    'workflow_definition_step_id' => null,
                    'step_order' => $nextOrder,
                    'step_name' => str_replace('_', ' ', ucfirst(strtolower($action))),
                    'action_type' => 'APPROVAL',
                    'assigned_role_id' => null,
                    'assigned_user_id' => $actorId,
                    'status' => 'APPROVED',
                    'comment' => $comment,
                    'acted_at' => $actedAt ?: $document->updated_at,
                    'created_at' => $document->created_at,
                    'updated_at' => $document->updated_at,
                ]);
            }

            [$signaturePath, $signatureHash] = $this->storeSignatureSnapshot(
                (string) $signature,
                "document-{$document->id}-".strtolower($action)
            );
            if (! $signaturePath) {
                continue;
            }

            $actor = $this->source->table('users')->where('id', $actorId)->first();
            $evidenceKey = ['workflow_step_id' => $stepId, 'actor_id' => $actorId, 'action' => $action];
            if ($this->target->table('workflow_action_evidence')->where($evidenceKey)->exists()) {
                continue;
            }
            $this->target->table('workflow_action_evidence')->insert($evidenceKey + [
                'actor_name' => $actor?->name ?: 'Unknown user',
                'actor_position' => $actor?->position,
                'signature_path' => $signaturePath,
                'signature_sha256' => $signatureHash,
                'comment' => $comment,
                'ip_address' => null,
                'request_id' => 'legacy-document-'.$document->id.'-'.strtolower($action),
                'acted_at' => $actedAt ?: $document->updated_at,
                'created_at' => $document->created_at,
            ]);
        }
    }

    private function storeSignatureSnapshot(string $signature, string $name): array
    {
        $encoded = preg_replace('/^data:image\/[a-zA-Z0-9.+-]+;base64,/', '', $signature);
        $binary = base64_decode((string) $encoded, true);
        if ($binary === false) {
            $this->warn("Skipped invalid signature snapshot {$name}");

            return [null, null];
        }

        $hash = hash('sha256', $binary);
        $path = "private/v2-workflow-evidence/{$name}-{$hash}.png";
        Storage::disk('local')->put($path, $binary);

        return [$path, $hash];
    }

    private function copyNumbers(): void
    {
        foreach ($this->source->table('documents')->whereNotNull('running_number')->orderBy('id')->get() as $document) {
            $scope = strtolower($document->doc_type) === 'internal'
                ? ($this->source->table('users')->where('id', $document->created_by)->value('department') ?: 'organization')
                : 'organization';
            $this->allocateNumber(strtolower($document->doc_type), $scope, $document->created_at,
                (int) $document->running_number, 'ยล 77301/'.$document->running_number,
                $document->id, null, $document->created_by, $document->created_at);
        }
        foreach ($this->source->table('leave_requests')->whereNotNull('running_number')->get() as $leave) {
            $scope = $this->source->table('users')->where('id', $leave->user_id)->value('department')
                ?: 'ไม่ระบุสังกัด';
            $this->allocateNumber('leave', $scope, $leave->numbered_at ?: $leave->created_at,
                (int) $leave->running_number, $leave->leave_number ?: 'ลา/'.$leave->running_number,
                null, $leave->id, $leave->numbered_by, $leave->numbered_at ?: $leave->created_at);
        }
    }

    private function copyBookings(): void
    {
        foreach ($this->source->table('rooms')->get() as $room) {
            $this->target->table('rooms')->updateOrInsert(['id' => $room->id], [
                'code' => null, 'name' => $room->name, 'description' => null, 'location' => null,
                'capacity' => $room->capacity, 'status' => strtoupper($room->status),
                'created_at' => $room->created_at, 'updated_at' => $room->updated_at, 'deleted_at' => $room->deleted_at,
            ]);
        }
        foreach ($this->source->table('room_bookings')->get() as $booking) {
            $roomName = $booking->room_name_snapshot ?: $this->source->table('rooms')->where('id', $booking->room_id)->value('name');
            $this->target->table('room_bookings')->updateOrInsert(['id' => $booking->id], [
                'room_id' => $booking->room_id, 'room_name_snapshot' => $roomName ?: '-',
                'created_by' => $booking->created_by, 'document_id' => $booking->document_id,
                'title' => $booking->title, 'description' => $booking->description,
                'booking_type' => strtoupper($booking->booking_type), 'start_time' => $booking->start_time,
                'end_time' => $booking->end_time, 'status' => 'APPROVED', 'approved_by' => null,
                'approved_at' => null, 'cancelled_at' => null, 'created_at' => $booking->created_at,
                'updated_at' => $booking->updated_at,
            ]);
        }
        foreach ($this->source->table('room_booking_user')->get() as $participant) {
            $this->target->table('room_booking_participants')->updateOrInsert(
                ['room_booking_id' => $participant->room_booking_id, 'user_id' => $participant->user_id],
                ['participant_role' => 'ATTENDEE', 'status' => strtoupper($participant->status),
                    'responded_at' => null, 'created_at' => $participant->created_at, 'updated_at' => $participant->updated_at]
            );
        }
    }

    private function copyAuditLogs(): void
    {
        foreach ($this->source->table('audit_logs')->orderBy('id')->get() as $log) {
            $this->target->table('audit_logs')->updateOrInsert(['id' => $log->id], [
                'user_id' => $log->user_id, 'event' => $log->event,
                'auditable_type' => $log->auditable_type, 'auditable_id' => $log->auditable_id,
                'old_values' => $log->old_values, 'new_values' => $log->new_values,
                'ip_address' => $log->ip_address, 'user_agent' => $log->user_agent,
                'request_id' => $log->request_id, 'created_at' => $log->created_at,
            ]);
        }
    }

    private function categoryId(?string $name): ?int
    {
        $name = trim((string) $name);
        if ($name === '') {
            return null;
        }
        $code = 'LEGACY_'.strtoupper(substr(hash('sha256', $name), 0, 12));
        $this->target->table('document_categories')->updateOrInsert(['code' => $code], [
            'name' => $name, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $this->masterId('document_categories', $code);
    }

    private function leaveTypeId(string $name): int
    {
        $map = ['ลาป่วย' => 'SICK', 'ลากิจ' => 'PERSONAL', 'ลากิจส่วนตัว' => 'PERSONAL', 'ลาพักผ่อน' => 'VACATION', 'ลาคลอดบุตร' => 'MATERNITY'];
        $code = $map[trim($name)] ?? 'LEGACY_'.strtoupper(substr(hash('sha256', $name), 0, 12));
        $this->target->table('leave_types')->updateOrInsert(['code' => $code], [
            'name' => $name, 'max_days' => null, 'requires_attachment' => false,
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return (int) $this->masterId('leave_types', $code);
    }

    private function unitByName(?string $name): ?int
    {
        $name = trim((string) $name);
        if ($name === '') {
            return null;
        }
        $existing = $this->target->table('organization_units')->where('name', $name)->value('id');
        if ($existing) {
            return (int) $existing;
        }
        $this->target->table('organization_units')->insert([
            'parent_id' => null, 'unit_type' => 'OTHER', 'code' => null, 'name' => $name,
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return (int) $this->target->table('organization_units')->where('name', $name)->value('id');
    }

    private function definitionId(string $code, string $name, string $resource): int
    {
        $this->target->table('workflow_definitions')->updateOrInsert(['code' => $code, 'version' => 1], [
            'name' => $name, 'resource_type' => $resource, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return (int) $this->target->table('workflow_definitions')->where(['code' => $code, 'version' => 1])->value('id');
    }

    private function instanceId(int $definitionId, ?int $documentId, ?int $leaveId, string $status, ?int $step, $created, $updated): int
    {
        $key = $documentId ? ['document_id' => $documentId] : ['leave_request_id' => $leaveId];
        $this->target->table('workflow_instances')->updateOrInsert($key, [
            'workflow_definition_id' => $definitionId, 'status' => $status, 'current_step' => $step,
            'started_at' => $created, 'completed_at' => in_array($status, ['APPROVED', 'REJECTED', 'CANCELED', 'COMPLETED'], true) ? $updated : null,
            'created_at' => $created, 'updated_at' => $updated,
        ]);

        return (int) $this->target->table('workflow_instances')->where($key)->value('id');
    }

    private function allocateNumber(string $type, string $scope, $date, int $number, string $formatted, ?int $documentId, ?int $leaveId, ?int $userId, $allocatedAt): void
    {
        $date = Carbon::parse($date);
        $year = $date->month >= 10 ? $date->year + 1 : $date->year;
        $key = ['number_type' => $type, 'scope' => $scope, 'fiscal_year' => $year];
        $sequence = $this->target->table('number_sequences')->where($key)->first();
        if ($sequence) {
            $this->target->table('number_sequences')->where('id', $sequence->id)->update([
                'last_number' => max((int) $sequence->last_number, $number),
                'updated_at' => now(),
            ]);
        } else {
            $this->target->table('number_sequences')->insert($key + [
                'prefix' => null, 'last_number' => $number, 'padding' => 0,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $sequenceId = $this->target->table('number_sequences')->where($key)->value('id');
        $resourceKey = $documentId ? ['document_id' => $documentId] : ['leave_request_id' => $leaveId];
        $this->target->table('number_allocations')->updateOrInsert($resourceKey, [
            'sequence_id' => $sequenceId, 'running_number' => $number, 'formatted_number' => $formatted,
            'allocated_by' => $userId, 'allocated_at' => $allocatedAt,
        ]);
    }

    private function masterId(string $table, string $code): ?int
    {
        return $this->target->table($table)->where('code', $code)->value('id');
    }

    private function priorityCode(?string $value): string
    {
        return ['ด่วน' => 'URGENT', 'ด่วนมาก' => 'VERY_URGENT', 'ด่วนที่สุด' => 'HIGHEST'][$value] ?? 'NORMAL';
    }

    private function confidentialityCode(?string $value): string
    {
        return ['ลับ' => 'CONFIDENTIAL', 'ลับมาก' => 'SECRET', 'ลับที่สุด' => 'TOP_SECRET'][$value] ?? 'NORMAL';
    }

    private function documentStatus(?string $status): string
    {
        return match (strtoupper((string) $status)) {
            'DRAFT' => 'DRAFT', 'APPROVED' => 'APPROVED', 'REJECTED' => 'REJECTED', 'CANCELED', 'CANCELLED' => 'CANCELED', default => 'IN_REVIEW'
        };
    }

    private function leaveStatus(?string $status): string
    {
        return match (strtoupper((string) $status)) {
            'DRAFT' => 'DRAFT', 'APPROVED' => 'APPROVED', 'REJECTED' => 'REJECTED', 'CANCELED', 'CANCELLED' => 'CANCELED', default => 'IN_REVIEW'
        };
    }

    private function workflowStatus(?string $status): string
    {
        return match ($this->documentStatus($status)) {
            'APPROVED' => 'APPROVED', 'REJECTED' => 'REJECTED', 'CANCELED' => 'CANCELED', default => 'IN_PROGRESS'
        };
    }

    private function legacyLeaveCurrentStep(?string $status): ?int
    {
        return match (strtolower((string) $status)) {
            'pending_delegate', 'delegate_declined', 'delegate_escalated' => 1,
            'pending_head' => 2, 'pending_inspector' => 3, 'pending_numbering' => 4,
            'pending_palad' => 5, 'pending_nayok' => 6, default => null,
        };
    }

    private function stepStatus(?string $status): string
    {
        return match (strtolower((string) $status)) {
            'approved', 'accepted' => 'APPROVED', 'rejected', 'declined' => 'REJECTED', 'canceled', 'cancelled' => 'CANCELED', default => 'PENDING'
        };
    }

    private function assignmentStatus(?string $status, $acknowledgedAt): string
    {
        return $acknowledgedAt ? 'ACKNOWLEDGED' : match (strtolower((string) $status)) {
            'accepted' => 'ACKNOWLEDGED', 'completed' => 'COMPLETED', 'delegated' => 'IN_PROGRESS', default => 'PENDING'
        };
    }
}
