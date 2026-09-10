<?php

namespace App\Services\V2;

use App\Models\User as LegacyUser;
use App\Models\V2\Document;
use App\Models\V2\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class DocumentReadService
{
    public function find(string|int $identifier): Document
    {
        return Document::with([
            'creator.organizationUnit.parent',
            'creator.positionRecord',
            'type', 'category', 'priority', 'confidentiality',
            'files', 'assignments.assignee', 'assignments.unit',
            'accessRequests.requester.organizationUnit.parent',
            'numberAllocation', 'workflow',
            'routes.user.organizationUnit.parent',
            'routes.user.positionRecord',
            'routes.user.signatures',
            'routes.evidence.actor.organizationUnit.parent',
            'routes.evidence.actor.positionRecord',
        ])->where(function ($query) use ($identifier) {
            $query->where('uuid', $identifier);
            if (is_numeric($identifier)) {
                $query->orWhere('id', (int) $identifier);
            }
        })->firstOrFail();
    }

    public function approvalQueue(LegacyUser $user): Collection
    {
        $query = Document::query();
        if ($user->hasRole('super-admin')) {
            $query->whereNotIn('status', ['DRAFT', 'APPROVED', 'REJECTED', 'CANCELED', 'COMPLETED', 'ARCHIVED']);
        } else {
            $roleIds = $user->roles()->pluck('roles.id');
            $query->where(function (Builder $documents) use ($user, $roleIds) {
                $documents->where(fn (Builder $own) => $own
                    ->where('created_by', $user->id)->whereIn('status', ['DRAFT', 'REJECTED']))
                    ->orWhereHas('workflow', fn (Builder $workflow) => $workflow
                        ->whereHas('steps', fn (Builder $steps) => $steps
                            ->where('status', 'PENDING')
                            ->whereColumn('workflow_steps.step_order', 'workflow_instances.current_step')
                            ->where(fn (Builder $assignee) => $assignee
                                ->where('assigned_user_id', $user->id)
                                ->orWhereIn('assigned_role_id', $roleIds))))
                    ->orWhereHas('assignments', fn (Builder $assignments) => $assignments
                        ->where('assigned_user_id', $user->id)
                        ->whereIn('status', ['PENDING', 'DELEGATED']));

                if ($user->hasRole('head')) {
                    $names = array_values(array_filter([$user->department, $user->division, $user->work_unit]));
                    $documents->orWhereHas('assignments', fn (Builder $assignments) => $assignments
                        // คิวของหัวหน้ามีเฉพาะงานที่ส่งถึงหน่วยและยังไม่มีผู้รับ
                        // เมื่อรับงานแล้ว รายการยังอยู่ในหน้าประวัติงานแต่ไม่ใช่ action item อีก
                        ->where('status', 'PENDING')
                        ->whereNull('assigned_user_id')
                        ->whereHas('unit', fn (Builder $units) => $units->whereIn('name', $names)));
                }
            });
        }

        return $query->with(['creator.organizationUnit.parent', 'creator.positionRecord', 'type', 'numberAllocation', 'workflow', 'routes'])
            ->latest()->get();
    }

    public function assignedDocuments(LegacyUser $user): Collection
    {
        $names = array_values(array_filter([$user->department, $user->division, $user->work_unit]));
        return Document::whereHas('assignments', function (Builder $assignments) use ($user, $names) {
            $assignments->whereIn('status', ['PENDING', 'ACKNOWLEDGED', 'IN_PROGRESS', 'COMPLETED'])
                ->where(function (Builder $visibleAssignments) use ($user, $names) {
                    $visibleAssignments->where('assigned_user_id', $user->id);
                    if ($user->hasRole('head')) {
                        $visibleAssignments->orWhere('assigned_by', $user->id)
                            ->orWhere('delegated_by', $user->id)
                            ->orWhereHas('unit', fn (Builder $units) => $units->whereIn('name', $names));
                    }
                });
        })->with(['assignments.assignee', 'assignments.unit', 'type', 'numberAllocation'])->latest()->get();
    }

    public function subordinates(LegacyUser $user): Collection
    {
        return User::with(['organizationUnit.parent', 'positionRecord'])->where('id', '!=', $user->id)
            ->orderBy('name')->get()->filter(fn (User $candidate) => $candidate->department === $user->department)->values();
    }
}
