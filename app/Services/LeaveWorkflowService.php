<?php

namespace App\Services;

use App\Models\DocumentNumberAllocation;
use App\Models\LeaveRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LeaveWorkflowService
{
    public function __construct(
        private NotificationDispatcher $notifications,
        private LineMessagingService $line
    ) {}

    public function canReview(User $user, LeaveRequest $leave): bool
    {
        return $user->can('review', $leave);
    }

    public function respondToDelegation(LeaveRequest $leave, User $delegate, string $action, ?string $reason = null): LeaveRequest
    {
        $leave = DB::transaction(function () use ($leave, $delegate, $action, $reason) {
            $locked = LeaveRequest::whereKey($leave->id)->lockForUpdate()->firstOrFail();
            if ($locked->workflow_status !== 'pending_delegate' || $locked->delegate_id !== $delegate->id) {
                abort(403, 'คำขอมอบหมายนี้ถูกดำเนินการแล้วหรือไม่ได้มอบหมายให้คุณ');
            }

            if ($action === 'decline' && blank($reason)) {
                throw ValidationException::withMessages(['decline_reason' => 'กรุณาระบุเหตุผลที่ไม่สามารถรับมอบงาน']);
            }

            $locked->update($action === 'accept' ? [
                'delegate_status' => 'accepted',
                'delegate_decline_reason' => null,
                'delegate_responded_at' => now(),
                'workflow_status' => 'pending_inspector',
            ] : [
                'delegate_status' => 'declined',
                'delegate_decline_reason' => $reason,
                'delegate_responded_at' => now(),
                'workflow_status' => 'delegate_declined',
            ]);

            return $locked->fresh(['user', 'delegate']);
        }, 3);

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
        $oldDelegate = $leave->delegate;
        $leave = DB::transaction(function () use ($leave, $owner, $newDelegate) {
            $locked = LeaveRequest::whereKey($leave->id)->lockForUpdate()->firstOrFail();
            if ($locked->user_id !== $owner->id || ! in_array($locked->workflow_status, [
                'pending_delegate', 'delegate_declined', 'delegate_escalated',
            ], true)) {
                abort(403, 'ไม่สามารถเปลี่ยนผู้รับมอบงานในขั้นตอนนี้ได้');
            }
            if ($newDelegate->id === $owner->id) {
                throw ValidationException::withMessages(['delegate_id' => 'ไม่สามารถเลือกตนเองเป็นผู้รับมอบงาน']);
            }

            $locked->update([
                'delegate_id' => $newDelegate->id,
                'delegate_status' => 'pending',
                'delegate_decline_reason' => null,
                'delegate_requested_at' => now(),
                'delegate_responded_at' => null,
                'delegate_reminded_at' => null,
                'delegate_escalated_at' => null,
                'workflow_status' => 'pending_delegate',
            ]);

            return $locked->fresh(['user', 'delegate']);
        }, 3);

        if ($oldDelegate && $oldDelegate->id !== $newDelegate->id) {
            $this->notifications->toUser($oldDelegate, "ℹ️ คำขอปฏิบัติงานแทนของ {$owner->name} ถูกเปลี่ยนไปยังผู้รับมอบคนใหม่แล้ว");
        }
        $this->notifyNext($leave);

        return $leave;
    }

    public function reject(LeaveRequest $leave, User $actor, string $reason): LeaveRequest
    {
        if ($leave->workflow_status === 'pending_numbering') {
            throw ValidationException::withMessages([
                'reject_reason' => 'นายกอนุมัติแล้ว ขั้นตอนลงเลขไม่สามารถตีกลับใบลาได้',
            ]);
        }

        $leave->update([
            'status' => 'REJECTED',
            'workflow_status' => 'rejected',
            'reject_reason' => "[{$actor->position}] : {$reason}",
        ]);
        $this->notifications->toUser($leave->user,
            "❌ ใบลาของคุณไม่ผ่านการพิจารณา\nประเภท: {$leave->leave_type}\nเหตุผล: {$reason}\n\nดูประวัติใบลา:\n".route('leaves.index')
        );

        return $leave;
    }

    public function approve(LeaveRequest $leave, User $actor, ?int $runningNumber = null): LeaveRequest
    {
        try {
            $leave = DB::transaction(function () use ($leave, $actor, $runningNumber) {
                $locked = LeaveRequest::with('user')->whereKey($leave->id)->lockForUpdate()->firstOrFail();
                if (! $this->canReview($actor, $locked)) {
                    abort(403, 'ใบลานี้ถูกดำเนินการไปแล้วหรือไม่อยู่ในคิวของคุณ');
                }

                $updates = match ($locked->workflow_status) {
                    'pending_inspector' => [
                        'workflow_status' => 'pending_head', 'inspector_id' => $actor->id,
                        'inspector_status' => 'approved', 'inspector_signature' => $actor->signature, 'inspector_at' => now(),
                    ],
                    'pending_head' => [
                        'workflow_status' => 'pending_palad', 'head_id' => $actor->id,
                        'head_status' => 'approved', 'head_signature' => $actor->signature, 'head_at' => now(),
                    ],
                    'pending_palad' => [
                        'workflow_status' => 'pending_nayok', 'palad_id' => $actor->id,
                        'palad_status' => 'approved', 'palad_signature' => $actor->signature, 'palad_at' => now(),
                    ],
                    'pending_nayok' => [
                        'workflow_status' => 'pending_numbering', 'nayok_id' => $actor->id,
                        'nayok_status' => 'approved', 'nayok_signature' => $actor->signature, 'nayok_at' => now(),
                    ],
                    'pending_numbering' => $this->numberingUpdates($locked, $actor, $runningNumber),
                    default => throw ValidationException::withMessages(['is_approved' => 'สถานะใบลาไม่รองรับการอนุมัติ']),
                };

                $locked->update($updates);

                return $locked->fresh(['user', 'delegate']);
            }, 3);
        } catch (QueryException $error) {
            if (in_array((string) $error->getCode(), ['23000', '23505'], true)) {
                throw ValidationException::withMessages([
                    'leave_number' => 'เลขใบลานี้เพิ่งถูกใช้งาน กรุณารันเลขใหม่',
                ]);
            }
            throw $error;
        }

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

    public function notifyNext(LeaveRequest $leave): void
    {
        $leave->loadMissing(['user', 'delegate']);
        $recipients = match ($leave->workflow_status) {
            'pending_delegate' => collect([$leave->delegate]),
            'pending_inspector' => $this->line->usersWithRoles('hr'),
            'pending_head' => $this->line->usersWithRoles('head', $leave->user?->department),
            'pending_palad' => $this->line->usersWithRoles(['palad', 'deputy-palad']),
            'pending_nayok' => $this->line->usersWithRoles('executive'),
            'pending_numbering' => $this->line->usersWithRoles('saraban'),
            default => collect(),
        };

        $action = $leave->workflow_status === 'pending_delegate'
            ? 'มีคำขอให้คุณรับมอบหมายงานแทนผู้ลา'
            : 'มีใบลารอการตรวจสอบ/อนุมัติ';
        $url = $leave->workflow_status === 'pending_delegate'
            ? route('leaves.show', $leave->id)
            : route('leaves.approve_list');
        $message = "📝 {$action}\nผู้ลา: ".($leave->user?->name ?: '-')
            ."\nหน่วยงาน: ".($leave->user?->department ?: '-')
            ."\nประเภท: {$leave->leave_type}\nวันที่: ".Carbon::parse($leave->start_date)->format('d/m/Y')
            .' - '.Carbon::parse($leave->end_date)->format('d/m/Y')."\n\nเปิดรายการใบลา:\n{$url}";

        $this->notifications->toUsers($recipients, $message);
    }

    private function numberingUpdates(LeaveRequest $leave, User $actor, ?int $number): array
    {
        if (! $number || $number < 1) {
            throw ValidationException::withMessages(['running_number' => 'กรุณารันเลขก่อนยืนยัน']);
        }

        $now = now();
        $fiscalYear = $now->month >= 10 ? $now->year + 1 : $now->year;
        $thaiYear = $fiscalYear + 543;
        $formatted = "ลา/{$number}/{$thaiYear}";

        $alreadyUsed = LeaveRequest::whereKeyNot($leave->id)
            ->whereBetween('numbered_at', [($fiscalYear - 1).'-10-01', $fiscalYear.'-09-30 23:59:59'])
            ->where('running_number', $number)->lockForUpdate()->exists();
        if ($alreadyUsed) {
            throw ValidationException::withMessages(['leave_number' => 'เลขนี้ถูกใช้งานแล้ว กรุณารันเลขใหม่']);
        }

        DocumentNumberAllocation::where('leave_request_id', $leave->id)->delete();
        DocumentNumberAllocation::create([
            'number_type' => 'leave', 'scope' => 'organization', 'fiscal_year' => $fiscalYear,
            'running_number' => $number, 'formatted_number' => $formatted,
            'leave_request_id' => $leave->id, 'allocated_by' => $actor->id,
        ]);

        return [
            'status' => 'APPROVED', 'workflow_status' => 'approved',
            'leave_number' => $formatted, 'running_number' => $number,
            'numbered_by' => $actor->id, 'numbered_at' => $now,
        ];
    }
}
