<?php

namespace App\Console\Commands;

use App\Models\LeaveRequest;
use App\Services\LeaveWorkflowService;
use App\Services\LineMessagingService;
use App\Services\NotificationDispatcher;
use Illuminate\Console\Command;
use Carbon\Carbon;

class ProcessLeaveDelegateTimeouts extends Command
{
    protected $signature = 'leaves:process-delegate-timeouts';

    protected $description = 'แจ้งเตือนและยกระดับคำขอผู้รับมอบงานใบลาที่ค้างเกินกำหนด';

    public function handle(
        NotificationDispatcher $notifications,
        LineMessagingService $line,
        LeaveWorkflowService $workflow,
    ): int
    {
        $remindHours = (int) config('services.leave_delegate.remind_after_hours', 24);
        $escalateHours = max($remindHours, (int) config('services.leave_delegate.escalate_after_hours', 48));

        LeaveRequest::with(['user', 'delegate'])
            ->atWorkflowStage('pending_delegate')
            ->orderBy('id')
            ->chunkById(100, function ($leaves) use ($notifications, $line, $workflow, $remindHours, $escalateHours) {
                foreach ($leaves as $leave) {
                    $ageHours = Carbon::parse($leave->delegate_requested_at)->diffInHours(now());
                    $url = route('leaves.show', $leave->id);

                    if ($ageHours >= $escalateHours && ! $leave->delegate_escalated_at) {
                        $updated = $workflow->recordOperationalAction($leave, 'ESCALATED');
                        if ($updated) {
                            $message = "⚠️ คำขอผู้รับมอบงานใบลาค้างเกิน {$escalateHours} ชั่วโมง\nผู้ลา: ".($leave->user?->name ?: '-')
                                ."\nผู้รับมอบเดิม: ".($leave->delegate?->name ?: '-')."\nกรุณาเลือกผู้รับมอบคนใหม่\n\n{$url}";
                            $notifications->toUser($leave->user, $message);
                            $notifications->toUsers($line->usersWithRoles('hr'), $message);
                        }
                    } elseif ($ageHours >= $remindHours && ! $leave->delegate_reminded_at) {
                        $updated = $workflow->recordOperationalAction($leave, 'REMINDER_SENT');
                        if ($updated) {
                            $notifications->toUser(
                                $leave->delegate,
                                "⏰ แจ้งเตือนคำขอปฏิบัติงานแทนที่ยังไม่ได้ตอบ\nผู้ลา: ".($leave->user?->name ?: '-')."\n\n{$url}"
                            );
                        }
                    }
                }
            });

        return self::SUCCESS;
    }
}
