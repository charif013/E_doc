<?php

namespace App\Policies\V2;

use App\Models\User;
use App\Models\V2\LeaveRequest;

class LeaveRequestPolicy
{
    public function before(User $user): ?bool
    {
        return $user->hasRole('super-admin') ? true : null;
    }

    public function view(User $user, LeaveRequest $leave): bool
    {
        return $leave->user_id === $user->id
            || $leave->delegate_user_id === $user->id
            || $leave->workflow?->steps()->where('assigned_user_id', $user->id)->exists();
    }

    public function cancel(User $user, LeaveRequest $leave): bool
    {
        return $leave->user_id === $user->id
            && ! in_array($leave->status, ['APPROVED', 'REJECTED', 'CANCELED', 'COMPLETED'], true);
    }

    public function review(User $user, LeaveRequest $leave): bool
    {
        $workflow = $leave->workflow;
        if (! $workflow || ! $workflow->current_step) {
            return false;
        }
        $step = $workflow->steps()->where('step_order', $workflow->current_step)->where('status', 'PENDING')->first();

        return $step && ($step->assigned_user_id === $user->id
            || ($step->assigned_role_id && $user->roles()->whereKey($step->assigned_role_id)->exists()));
    }
}
