<?php

namespace App\Services;

use App\Models\User;

class AssignmentNotificationService
{
    public function __construct(private NotificationDispatcher $notifications)
    {
    }

    public function toUser(?User $assignee, string $title, string $assignedBy, ?string $documentNumber, string $url): void
    {
        if (!$assignee) {
            return;
        }

        $this->notifications->toUser(
            $assignee,
            "📨 มีงานใหม่มอบหมายถึงคุณ\nเรื่อง: {$title}\nเลขที่: ".($documentNumber ?: '-')
                ."\nผู้มอบหมาย: {$assignedBy}\n\nเปิดดูงาน:\n{$url}"
        );
    }

    public function toUnitHeads(string $unitName, string $title, string $assignedBy, ?string $documentNumber, string $url): void
    {
        $unitName = trim($unitName);
        if ($unitName === '') {
            return;
        }

        $recipients = User::role('head')
            ->where(function ($query) use ($unitName) {
                $query->where('department', $unitName)
                    ->orWhere('division', $unitName)
                    ->orWhere('work_unit', $unitName);
            })
            ->get();

        $this->notifications->toUsers(
            $recipients,
            "📨 มีงานใหม่มอบหมายถึงหน่วยงานของคุณ\nหน่วยงาน: {$unitName}\nเรื่อง: {$title}"
                ."\nเลขที่: ".($documentNumber ?: '-')."\nผู้มอบหมาย: {$assignedBy}\n\nเปิดดูงาน:\n{$url}"
        );
    }
}
