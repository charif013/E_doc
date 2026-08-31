<?php

namespace App\Console\Commands;

use App\Models\LeaveRequest;
use App\Services\LineMessagingService;
use App\Services\NotificationDispatcher;
use Illuminate\Console\Command;

class ProcessLeaveDelegateTimeouts extends Command
{
    protected $signature = 'leaves:process-delegate-timeouts';

    protected $description = 'แจ้งเตือนและยกระดับคำขอผู้รับมอบงานใบลาที่ค้างเกินกำหนด';

    public function handle(NotificationDispatcher $notifications, LineMessagingService $line): int
    {
        $remindHours = (int) config('services.leave_delegate.remind_after_hours', 24);
        $escalateHours = max($remindHours, (int) config('services.leave_delegate.escalate_after_hours', 48));

        LeaveRequest::with(['user', 'delegate'])
            ->where('workflow_status', 'pending_delegate')
            ->whereNotNull('delegate_requested_at')
            ->orderBy('id')
            ->chunkById(100, function ($leaves) use ($notifications, $line, $remindHours, $escalateHours) {
                foreach ($leaves as $leave) {
                    $ageHours = $leave->delegate_requested_at->diffInHours(now());
                    $url = route('leaves.show', $leave->id);

                    if ($ageHours >= $escalateHours && ! $leave->delegate_escalated_at) {
                        $updated = LeaveRequest::whereKey($leave->id)
                            ->where('workflow_status', 'pending_delegate')
                            ->whereNull('delegate_escalated_at')
                            ->update([
                                'workflow_status' => 'delegate_escalated',
                                'delegate_escalated_at' => now(),
                            ]);
                        if ($updated) {
                            $message = "⚠️ คำขอผู้รับมอบงานใบลาค้างเกิน {$escalateHours} ชั่วโมง\nผู้ลา: ".($leave->user?->name ?: '-')
                                ."\nผู้รับมอบเดิม: ".($leave->delegate?->name ?: '-')."\nกรุณาเลือกผู้รับมอบคนใหม่\n\n{$url}";
                            $notifications->toUser($leave->user, $message);
                            $notifications->toUsers($line->usersWithRoles('hr'), $message);
                        }
                    } elseif ($ageHours >= $remindHours && ! $leave->delegate_reminded_at) {
                        $updated = LeaveRequest::whereKey($leave->id)
                            ->where('workflow_status', 'pending_delegate')
                            ->whereNull('delegate_reminded_at')
                            ->update(['delegate_reminded_at' => now()]);
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
