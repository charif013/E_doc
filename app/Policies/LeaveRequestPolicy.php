<?php

namespace App\Policies;

use App\Models\LeaveRequest;
use App\Models\User;

class LeaveRequestPolicy
{
    public function view(User $user, LeaveRequest $leave): bool
    {
        return $user->hasRole('super-admin')
            || $leave->user_id === $user->id
            || $this->review($user, $leave)
            || in_array($user->id, array_filter([
                $leave->delegate_id, $leave->inspector_id, $leave->head_id,
                $leave->palad_id, $leave->nayok_id, $leave->numbered_by,
            ]), true);
    }

    public function cancel(User $user, LeaveRequest $leave): bool
    {
        return $leave->user_id === $user->id
            && ! in_array($leave->workflow_status, ['approved', 'rejected'], true);
    }

    public function review(User $user, LeaveRequest $leave): bool
    {
        return match ($leave->workflow_status) {
            'pending_delegate' => $leave->delegate_id === $user->id,
            'pending_inspector' => $user->hasRole('hr'),
            'pending_head' => $user->hasRole('head')
                && $user->department
                && $user->department === $leave->user?->department,
            'pending_palad' => $user->hasAnyRole(['palad', 'deputy-palad']),
            'pending_nayok' => $user->hasRole('executive'),
            'pending_numbering' => $user->hasRole('saraban'),
            default => false,
        };
    }
}
