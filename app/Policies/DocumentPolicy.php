<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\DocumentAccessRequest;
use App\Models\RoomBooking;
use App\Models\User;

class DocumentPolicy
{
    public function view(User $user, Document $document): bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }
        $document->loadMissing(['routes', 'creator']);

        if ($document->created_by === $user->id) {
            return true;
        }

        $hasAssignment = $document->assigned_user_id === $user->id
            || ($user->hasRole('head') && $document->assigned_to === $user->department);
        $hasBooking = RoomBooking::where('document_id', $document->id)
            ->where(function ($query) use ($user) {
                $query->where('created_by', $user->id)
                    ->orWhereHas('invitees', fn ($invitees) => $invitees->where('users.id', $user->id));
            })->exists();

        if ($hasAssignment || $hasBooking) {
            return true;
        }

        if ($document->routes->isNotEmpty()) {
            return $this->hasReachedRoute($user, $document);
        }

        return $this->canViewLegacyDocument($user, $document);
    }

    public function update(User $user, Document $document): bool
    {
        return ($document->created_by === $user->id || $user->hasRole('super-admin'))
            && in_array($document->status, ['DRAFT', 'REJECTED'], true);
    }

    public function delete(User $user, Document $document): bool
    {
        return $this->update($user, $document);
    }

    public function review(User $user, Document $document): bool
    {
        // เมื่อผู้พิจารณาทุกลำดับอนุมัติแล้ว จะไม่มี route ที่เป็น pending เหลืออยู่
        // แต่เจ้าหน้าที่สารบรรณยังต้องผ่านสิทธิ์นี้เพื่อยืนยันการออกเลขเอกสาร
        if ($user->hasRole('saraban') && $document->status === 'WAITING_NUMBERING') {
            return true;
        }

        $current = $document->routes()
            ->where('step_order', $document->current_step)
            ->where('status', 'pending')
            ->first();

        return $current !== null && $current->user_id === $user->id && $document->current_step > 1;
    }

    public function number(User $user, Document $document): bool
    {
        return $user->hasAnyRole(['super-admin', 'palad', 'saraban']);
    }

    public function manageAccess(User $user, Document $document): bool
    {
        return $document->created_by === $user->id || $user->hasAnyRole(['super-admin', 'saraban']);
    }

    public function accessConfidential(User $user, Document $document): bool
    {
        if (! in_array($document->doc_secret, ['ลับ', 'ลับเฉพาะ', 'ลับที่สุด'], true)) {
            return $this->view($user, $document);
        }

        $hasRoleAccess = $document->created_by === $user->id
            || $user->hasAnyRole(['super-admin', 'saraban', 'head', 'palad', 'deputy-palad', 'executive']);
        $approved = DocumentAccessRequest::where('document_id', $document->id)
            ->where('user_id', $user->id)
            ->where('status', 'approved')
            ->exists();
        $hasBooking = RoomBooking::where('document_id', $document->id)
            ->where(function ($query) use ($user) {
                $query->where('created_by', $user->id)
                    ->orWhereHas('invitees', fn ($invitees) => $invitees->where('users.id', $user->id));
            })->exists();

        return $this->view($user, $document)
            && ($hasRoleAccess || $approved || $hasBooking || $this->hasReachedRoute($user, $document));
    }

    private function hasReachedRoute(User $user, Document $document): bool
    {
        $document->loadMissing('routes');
        $route = $document->routes->firstWhere('user_id', $user->id);

        return $route && $document->current_step !== null && $route->step_order <= $document->current_step;
    }

    private function canViewLegacyDocument(User $user, Document $document): bool
    {
        // ผู้ที่เคยลงนาม/พิจารณาเอกสารฉบับนี้ยังเปิดดูประวัติของตนได้
        if (in_array($user->id, array_filter([
            $document->supervisor_id,
            $document->palad_id,
            $document->nayok_id,
            $document->acknowledged_by,
        ]), true)) {
            return true;
        }

        $status = (string) $document->status;
        $creatorDepartment = $document->creator?->department;

        if ($user->hasRole('saraban')) {
            return in_array($status, [
                'WAITING_ADMIN', 'WAITING_NUMBERING', 'APPROVED', 'REJECTED', 'CANCELED', 'RESERVED',
            ], true);
        }

        if ($user->hasRole('head')) {
            return $creatorDepartment !== null
                && $creatorDepartment === $user->department
                && in_array($status, ['WAITING_SUPERVISOR', 'WAITING_PALAD', 'WAITING_NAYOK', 'WAITING_NUMBERING', 'APPROVED'], true);
        }

        if ($user->hasAnyRole(['palad', 'deputy-palad'])) {
            return in_array($status, ['WAITING_PALAD', 'WAITING_NAYOK', 'WAITING_NUMBERING', 'APPROVED'], true);
        }

        if ($user->hasRole('executive')) {
            return in_array($status, ['WAITING_NAYOK', 'WAITING_NUMBERING', 'APPROVED'], true);
        }

        return false;
    }
}
