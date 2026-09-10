<?php

namespace App\Policies\V2;

use App\Models\User;
use App\Models\V2\Document;
use App\Models\V2\RoomBooking;

class DocumentPolicy
{
    public function before(User $user): ?bool
    {
        return $user->hasRole('super-admin') ? true : null;
    }

    public function view(User $user, Document $document): bool
    {
        if ($document->created_by === $user->id) {
            return true;
        }
        if ($user->hasRole('saraban') && $document->status !== 'DRAFT') {
            return true;
        }
        $activeAssignmentStatuses = ['PENDING', 'ACKNOWLEDGED', 'IN_PROGRESS', 'COMPLETED'];
        if ($document->assignments()->where('assigned_user_id', $user->id)
            ->whereIn('status', $activeAssignmentStatuses)->exists()) {
            return true;
        }
        if ($user->hasRole('head') && $document->assignments()->whereHas('unit', fn ($units) =>
            $units->whereIn('name', array_filter([$user->department, $user->division, $user->work_unit]))
        )->whereIn('status', $activeAssignmentStatuses)->exists()) {
            return true;
        }
        if ($document->workflow?->steps()->where('assigned_user_id', $user->id)
            ->where('step_order', '<=', $document->workflow->current_step)->exists()) {
            return true;
        }

        return RoomBooking::where('document_id', $document->id)
            ->where(fn ($bookings) => $bookings->where('created_by', $user->id)
                ->orWhereHas('participants', fn ($participants) => $participants->where('users.id', $user->id)))
            ->exists();
    }

    public function update(User $user, Document $document): bool
    {
        return $document->created_by === $user->id
            && in_array($document->status, ['DRAFT', 'REJECTED'], true);
    }

    public function delete(User $user, Document $document): bool
    {
        return $this->update($user, $document);
    }

    public function review(User $user, Document $document): bool
    {
        $workflow = $document->workflow;
        if (! $workflow || ! $workflow->current_step) {
            return false;
        }
        $step = $workflow->steps()->where('step_order', $workflow->current_step)->where('status', 'PENDING')->first();
        if (! $step) {
            return false;
        }

        return $step->assigned_user_id === $user->id
            || ($step->assigned_role_id && $user->roles()->whereKey($step->assigned_role_id)->exists());
    }

    public function number(User $user): bool
    {
        return $user->hasAnyRole(['super-admin', 'palad', 'saraban']);
    }

    public function accessConfidential(User $user, Document $document): bool
    {
        $isConfidential = $document->confidentiality_id
            && $document->getConnection()->table('document_confidentialities')
                ->where('id', $document->confidentiality_id)->where('level_no', '>', 1)->exists();
        if (! $isConfidential) {
            return $this->view($user, $document);
        }
        $approved = $document->getConnection()->table('document_access_requests')
            ->where('document_id', $document->id)->where('requested_by', $user->id)
            ->where('status', 'APPROVED')->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->exists();

        return $this->view($user, $document)
            && ($approved || $document->created_by === $user->id
                || $user->hasAnyRole(['saraban', 'head', 'palad', 'deputy-palad', 'executive']));
    }
}
