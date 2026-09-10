<?php

namespace App\Services;

use App\Models\DocumentNumberAllocation;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Models\V2\User as V2User;
use App\Models\V2\WorkflowActionEvidence;
use App\Models\V2\WorkflowInstance;
use App\Models\V2\WorkflowStep;
use App\Services\V2\NumberAllocationService;
use App\Services\V2\WorkflowService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class LeaveWorkflowService
{
    public function __construct(
        private NotificationDispatcher $notifications,
        private LineMessagingService $line,
        private NumberAllocationService $numberAllocations,
        private WorkflowService $workflows,
    ) {}

    public function canReview(User $user, LeaveRequest $leave): bool
    {
        return $user->can('review', $leave);
    }

    public function respondToDelegation(LeaveRequest $leave, User $delegate, string $action, ?string $reason = null): LeaveRequest
    {
        if ($leave->workflow_status !== 'pending_delegate' || $leave->delegate_id !== $delegate->id) {
            abort(403, 'คำขอมอบหมายนี้ถูกดำเนินการแล้วหรือไม่ได้มอบหมายให้คุณ');
        }
        if (! $this->usesCanonicalWorkflow($leave)) {
            return $this->legacyDelegationResponse($leave, $delegate, $action, $reason);
        }
        if ($action === 'decline' && blank($reason)) {
            throw ValidationException::withMessages(['decline_reason' => 'กรุณาระบุเหตุผลที่ไม่สามารถรับมอบงาน']);
        }

        if ($action === 'accept') {
            $workflow = $this->lockedWorkflow($leave, $delegate, 1);
            $this->workflows->act($workflow, V2User::findOrFail($delegate->id), 'approved', null, null, 'ACCEPTED');
        } else {
            DB::connection($leave->getConnectionName())->transaction(function () use ($leave, $delegate, $reason) {
                $workflow = $this->lockedWorkflow($leave, $delegate, 1);
                $step = $this->currentStep($workflow);
                $this->appendEvidence($workflow, $step, $delegate, 'DECLINED', $reason);
                $step->update(['status' => 'REJECTED', 'comment' => $reason, 'acted_at' => now()]);
                $workflow->update(['status' => 'PENDING']);
            }, 3);
        }

        $leave = $leave->fresh(['user', 'delegate', 'workflow.steps']);
        if ($action === 'accept') {
            $this->notifyNext($leave);
        } else {
            $this->notifications->toUser($leave->user,
                "❌ ผู้รับมอบงานปฏิเสธคำขอ\nผู้รับมอบ: ".($leave->delegate?->name ?: '-')
                ."\nเหตุผล: {$reason}\nกรุณาเลือกผู้รับมอบงานคนใหม่\n\n".route('leaves.show', $leave->id)
            );
        }

        return $leave;
    }

    public function reassignDelegate(LeaveRequest $leave, User $owner, User $newDelegate): LeaveRequest
    {
        if ($leave->user_id !== $owner->id || ! in_array($leave->workflow_status, [
            'pending_delegate', 'delegate_declined', 'delegate_escalated',
        ], true)) {
            abort(403, 'ไม่สามารถเปลี่ยนผู้รับมอบงานในขั้นตอนนี้ได้');
        }
        if ($newDelegate->id === $owner->id) {
            throw ValidationException::withMessages(['delegate_id' => 'ไม่สามารถเลือกตนเองเป็นผู้รับมอบงาน']);
        }
        if (! $this->usesCanonicalWorkflow($leave)) {
            $leave->update([
                'delegate_id' => $newDelegate->id, 'delegate_status' => 'pending',
                'delegate_decline_reason' => null, 'delegate_requested_at' => now(),
                'delegate_responded_at' => null, 'delegate_reminded_at' => null,
                'delegate_escalated_at' => null, 'workflow_status' => 'pending_delegate',
            ]);
            return $leave->fresh(['user', 'delegate']);
        }

        $oldDelegate = $leave->delegate;
        DB::connection($leave->getConnectionName())->transaction(function () use ($leave, $newDelegate) {
            $locked = LeaveRequest::whereKey($leave->id)->lockForUpdate()->firstOrFail();
            $workflow = WorkflowInstance::withoutGlobalScopes()->where('leave_request_id', $locked->id)
                ->lockForUpdate()->firstOrFail();
            $step = WorkflowStep::withoutGlobalScopes()->where('workflow_instance_id', $workflow->id)
                ->where('step_order', 1)->lockForUpdate()->firstOrFail();
            $locked->update(['delegate_user_id' => $newDelegate->id]);
            $step->update([
                'assigned_user_id' => $newDelegate->id, 'status' => 'PENDING',
                'comment' => null, 'acted_at' => null,
            ]);
            $workflow->update(['status' => 'IN_PROGRESS', 'current_step' => 1, 'completed_at' => null]);
        }, 3);

        $leave = $leave->fresh(['user', 'delegate', 'workflow.steps']);
        if ($oldDelegate && $oldDelegate->id !== $newDelegate->id) {
            $this->notifications->toUser($oldDelegate, "ℹ️ คำขอปฏิบัติงานแทนของ {$owner->name} ถูกเปลี่ยนไปยังผู้รับมอบคนใหม่แล้ว");
        }
        $this->notifyNext($leave);

        return $leave;
    }

    public function cancel(LeaveRequest $leave, User $actor): LeaveRequest
    {
        if (! $this->usesCanonicalWorkflow($leave)) {
            $leave->update(['status' => 'CANCELED', 'workflow_status' => 'canceled']);
            return $leave->fresh();
        }
        DB::connection($leave->getConnectionName())->transaction(function () use ($leave, $actor) {
            $workflow = WorkflowInstance::withoutGlobalScopes()->where('leave_request_id', $leave->id)
                ->lockForUpdate()->firstOrFail();
            if (in_array($workflow->status->value, ['APPROVED', 'REJECTED', 'CANCELED', 'COMPLETED'], true)) {
                throw ValidationException::withMessages(['status' => 'ไม่สามารถยกเลิกใบลาที่สิ้นสุดแล้ว']);
            }
            $step = $this->currentStep($workflow);
            $this->appendEvidence($workflow, $step, $actor, 'CANCELED');
            $step->update(['status' => 'CANCELED', 'acted_at' => now()]);
            $workflow->update(['status' => 'CANCELED', 'current_step' => null, 'completed_at' => now()]);
        }, 3);

        return $leave->fresh(['workflow.steps']);
    }

    public function reject(LeaveRequest $leave, User $actor, string $reason): LeaveRequest
    {
        if (! $this->usesCanonicalWorkflow($leave)) {
            $leave->update([
                'status' => 'REJECTED', 'workflow_status' => 'rejected',
                'reject_reason' => "[{$actor->position}] : {$reason}",
            ]);
            return $leave->fresh(['user', 'delegate']);
        }
        if ($leave->workflow_status === 'pending_numbering') {
            throw ValidationException::withMessages(['reject_reason' => 'ขั้นตอนลงเลขไม่สามารถตีกลับใบลาได้']);
        }
        $workflow = $this->lockedWorkflow($leave, $actor);
        $this->workflows->act($workflow, V2User::findOrFail($actor->id), 'rejected', $reason);
        $leave = $leave->fresh(['user', 'delegate', 'workflow.steps']);
        $this->notifications->toUser($leave->user,
            "❌ ใบลาของคุณไม่ผ่านการพิจารณา\nประเภท: {$leave->leave_type}\nเหตุผล: {$reason}\n\nดูประวัติใบลา:\n".route('leaves.index')
        );

        return $leave;
    }

    public function approve(LeaveRequest $leave, User $actor, ?int $runningNumber = null): LeaveRequest
    {
        if (! $this->usesCanonicalWorkflow($leave)) {
            return $this->legacyApprove($leave, $actor, $runningNumber);
        }
        $workflow = $this->lockedWorkflow($leave, $actor);
        $stepOrder = (int) $workflow->current_step;
        $evidenceAction = null;
        if ($stepOrder === 4) {
            $this->allocateLeaveNumber($leave, $actor, $runningNumber);
            $evidenceAction = 'NUMBER_ALLOCATED';
        }
        $this->workflows->act(
            $workflow, V2User::findOrFail($actor->id), 'approved', null, null, $evidenceAction
        );

        $leave = $leave->fresh(['user', 'delegate', 'workflow.steps', 'numberAllocation']);
        if ($leave->workflow_status === 'approved') {
            $this->notifications->toUser($leave->user,
                "✅ ใบลาของคุณได้รับอนุมัติเรียบร้อยแล้ว\nเลขที่: ".($leave->leave_number ?: '-')
                ."\nประเภท: {$leave->leave_type}\nวันที่: ".Carbon::parse($leave->start_date)->format('d/m/Y')
                .' - '.Carbon::parse($leave->end_date)->format('d/m/Y')
            );
        } else {
            $this->notifyNext($leave);
        }

        return $leave;
    }

    public function recordOperationalAction(LeaveRequest $leave, string $action): bool
    {
        if (! $this->usesCanonicalWorkflow($leave)) {
            $updates = $action === 'ESCALATED'
                ? ['workflow_status' => 'delegate_escalated', 'delegate_escalated_at' => now()]
                : ['delegate_reminded_at' => now()];
            return (bool) LeaveRequest::whereKey($leave->id)
                ->where('workflow_status', 'pending_delegate')->update($updates);
        }
        return DB::connection($leave->getConnectionName())->transaction(function () use ($leave, $action) {
            $workflow = WorkflowInstance::withoutGlobalScopes()->where('leave_request_id', $leave->id)
                ->lockForUpdate()->firstOrFail();
            if ((int) $workflow->current_step !== 1 || $workflow->status->value !== 'IN_PROGRESS') { return false; }
            $step = $this->currentStep($workflow);
            $this->appendEvidence($workflow, $step, null, $action);
            if ($action === 'ESCALATED') {
                $step->update(['status' => 'CANCELED', 'acted_at' => now()]);
                $workflow->update(['status' => 'PENDING']);
            }

            return true;
        }, 3);
    }

    public function notifyNext(LeaveRequest $leave): void
    {
        $leave->loadMissing(['user', 'delegate', 'workflow.steps']);
        $recipients = match ($leave->workflow_status) {
            'pending_delegate' => collect([$leave->delegate]),
            'pending_head' => $this->line->usersWithRoles('head', $leave->user?->department),
            'pending_inspector' => $this->line->usersWithRoles('hr'),
            'pending_palad' => $this->line->usersWithRoles(['palad', 'deputy-palad']),
            'pending_nayok' => $this->line->usersWithRoles('executive'),
            'pending_numbering' => $this->line->usersWithRoles('saraban'),
            default => collect(),
        };
        $action = $leave->workflow_status === 'pending_delegate'
            ? 'มีคำขอให้คุณรับมอบหมายงานแทนผู้ลา' : 'มีใบลารอการตรวจสอบ/อนุมัติ';
        $url = $leave->workflow_status === 'pending_delegate'
            ? route('leaves.show', $leave->id) : route('leaves.approve_list');
        $message = "📝 {$action}\nผู้ลา: ".($leave->user?->name ?: '-')
            ."\nหน่วยงาน: ".($leave->user?->department ?: '-')
            ."\nประเภท: {$leave->leave_type}\nวันที่: ".Carbon::parse($leave->start_date)->format('d/m/Y')
            .' - '.Carbon::parse($leave->end_date)->format('d/m/Y')."\n\nเปิดรายการใบลา:\n{$url}";
        $this->notifications->toUsers($recipients, $message);
    }

    private function lockedWorkflow(LeaveRequest $leave, User $actor, ?int $requiredStep = null): WorkflowInstance
    {
        $fresh = $leave->fresh(['user', 'workflow.steps']);
        if (! $this->canReview($actor, $fresh)) {
            abort(403, 'ใบลานี้ถูกดำเนินการไปแล้วหรือไม่อยู่ในคิวของคุณ');
        }
        $workflow = WorkflowInstance::withoutGlobalScopes()->where('leave_request_id', $leave->id)->firstOrFail();
        if ($requiredStep && (int) $workflow->current_step !== $requiredStep) { abort(403); }

        return $workflow;
    }

    private function currentStep(WorkflowInstance $workflow): WorkflowStep
    {
        return WorkflowStep::withoutGlobalScopes()->where('workflow_instance_id', $workflow->id)
            ->where('step_order', $workflow->current_step)->firstOrFail();
    }

    private function appendEvidence(
        WorkflowInstance $workflow, WorkflowStep $step, ?User $actor, string $action, ?string $comment = null
    ): void {
        $v2Actor = $actor ? V2User::find($actor->id) : null;
        $signature = $v2Actor?->signatures()->where('is_active', true)->whereNull('revoked_at')->latest('id')->first();
        WorkflowActionEvidence::create([
            'workflow_step_id' => $step->id, 'workflow_instance_id' => $workflow->id,
            'resource_type' => 'LEAVE', 'resource_id' => $workflow->leave_request_id,
            'workflow_code' => $workflow->definition?->code, 'workflow_version' => $workflow->definition?->version,
            'step_order' => $step->step_order, 'step_name' => $step->step_name,
            'actor_id' => $actor?->id, 'action' => $action, 'actor_name' => $actor?->name ?: 'System',
            'actor_position' => $actor?->position, 'signature_path' => $signature?->file_path,
            'signature_snapshot' => null, 'signature_sha256' => $signature?->sha256,
            'comment' => $comment, 'ip_address' => request()?->ip(),
            'request_id' => request()?->headers->get('X-Request-ID'), 'acted_at' => now(), 'created_at' => now(),
        ]);
    }

    private function allocateLeaveNumber(LeaveRequest $leave, User $actor, ?int $number): void
    {
        if (! $number || $number < 1) {
            throw ValidationException::withMessages(['running_number' => 'กรุณารันเลขก่อนยืนยัน']);
        }
        $now = now();
        $fiscalYear = $now->month >= 10 ? $now->year + 1 : $now->year;
        $scope = $leave->user?->department ?: 'ไม่ระบุสังกัด';
        $formatted = "ลา/{$scope}/{$number}/".($fiscalYear + 543);
        $this->numberAllocations->allocate(
            $leave, 'leave_request_id', 'leave', $scope, $fiscalYear,
            $number, $formatted, $actor->id, $now, 'running_number'
        );
    }

    private function legacyDelegationResponse(
        LeaveRequest $leave, User $delegate, string $action, ?string $reason
    ): LeaveRequest {
        if ($action === 'decline' && blank($reason)) {
            throw ValidationException::withMessages(['decline_reason' => 'กรุณาระบุเหตุผลที่ไม่สามารถรับมอบงาน']);
        }
        $leave->update($action === 'accept' ? [
            'delegate_status' => 'accepted', 'delegate_decline_reason' => null,
            'delegate_responded_at' => now(), 'workflow_status' => 'pending_head',
        ] : [
            'delegate_status' => 'declined', 'delegate_decline_reason' => $reason,
            'delegate_responded_at' => now(), 'workflow_status' => 'delegate_declined',
        ]);

        return $leave->fresh(['user', 'delegate']);
    }

    private function legacyApprove(LeaveRequest $leave, User $actor, ?int $runningNumber): LeaveRequest
    {
        if (! $this->canReview($actor, $leave)) {
            abort(403, 'ใบลานี้ถูกดำเนินการไปแล้วหรือไม่อยู่ในคิวของคุณ');
        }
        $updates = match ($leave->workflow_status) {
            'pending_head' => [
                'workflow_status' => 'pending_inspector', 'head_id' => $actor->id,
                'head_status' => 'approved', 'head_signature' => $actor->signature, 'head_at' => now(),
            ],
            'pending_inspector' => [
                'workflow_status' => 'pending_numbering', 'inspector_id' => $actor->id,
                'inspector_status' => 'approved', 'inspector_signature' => $actor->signature, 'inspector_at' => now(),
            ],
            'pending_numbering' => $this->legacyNumberingUpdates($leave, $actor, $runningNumber),
            'pending_palad' => [
                'workflow_status' => 'pending_nayok', 'palad_id' => $actor->id,
                'palad_status' => 'approved', 'palad_signature' => $actor->signature, 'palad_at' => now(),
            ],
            'pending_nayok' => [
                'status' => 'APPROVED', 'workflow_status' => 'approved', 'nayok_id' => $actor->id,
                'nayok_status' => 'approved', 'nayok_signature' => $actor->signature, 'nayok_at' => now(),
            ],
            default => throw ValidationException::withMessages(['is_approved' => 'สถานะใบลาไม่รองรับการอนุมัติ']),
        };
        $leave->update($updates);

        return $leave->fresh(['user', 'delegate']);
    }

    private function legacyNumberingUpdates(LeaveRequest $leave, User $actor, ?int $number): array
    {
        if (! $number || $number < 1) {
            throw ValidationException::withMessages(['running_number' => 'กรุณารันเลขก่อนยืนยัน']);
        }
        $now = now();
        $fiscalYear = $now->month >= 10 ? $now->year + 1 : $now->year;
        $scope = $leave->user?->department ?: 'ไม่ระบุสังกัด';
        $formatted = "ลา/{$scope}/{$number}/".($fiscalYear + 543);
        DocumentNumberAllocation::updateOrCreate(
            ['leave_request_id' => $leave->id],
            [
                'number_type' => 'leave', 'scope' => $scope, 'fiscal_year' => $fiscalYear,
                'running_number' => $number, 'formatted_number' => $formatted,
                'allocated_by' => $actor->id,
            ]
        );

        return [
            'workflow_status' => 'pending_palad', 'leave_number' => $formatted,
            'running_number' => $number, 'numbered_by' => $actor->id, 'numbered_at' => $now,
        ];
    }

    private function usesCanonicalWorkflow(LeaveRequest $leave): bool
    {
        return Schema::connection($leave->getConnectionName())->hasTable('workflow_instances');
    }
}
