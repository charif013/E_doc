<?php

namespace App\Services\V2;

use App\Models\User as LegacyUser;
use App\Models\V2\Document;
use Illuminate\Database\Eloquent\Builder;

class DashboardDocumentService
{
    public function data(LegacyUser $user, string $search = '', string $filter = 'all'): array
    {
        $visible = $this->visibleQuery($user);
        $listQuery = clone $visible;
        $this->applyFilter($listQuery, $filter);
        $searchResults = collect();
        if ($search !== '') {
            $searchResults = (clone $listQuery)->with(['creator', 'type', 'category', 'priority', 'confidentiality', 'numberAllocation', 'workflow', 'routes'])
                ->where(function (Builder $query) use ($search) {
                    $query->where('document_number', 'like', "%{$search}%")
                        ->orWhere('receive_number', 'like', "%{$search}%")
                        ->orWhere('title', 'like', "%{$search}%")
                        ->orWhere('sender_name', 'like', "%{$search}%")
                        ->orWhere('recipient_name', 'like', "%{$search}%");
                })->latest()->limit(30)->get();
        }

        $waiting = Document::whereHas('workflow', function ($workflow) use ($user) {
            $workflow->whereHas('steps', fn ($steps) => $steps
                ->where('assigned_user_id', $user->id)
                ->where('status', 'PENDING')
                ->whereColumn('workflow_steps.step_order', 'workflow_instances.current_step'));
        })->count();
        $waiting += Document::whereHas('assignments', fn ($assignments) => $assignments
            ->where('assigned_user_id', $user->id)->whereIn('status', ['PENDING', 'IN_PROGRESS']))->count();
        $waiting += Document::where('created_by', $user->id)->whereIn('status', ['DRAFT', 'REJECTED'])->count();

        return [
            'stats' => [
                'total' => (clone $visible)->count(),
                'waiting' => $waiting,
                'approved' => (clone $visible)->whereIn('status', ['APPROVED', 'COMPLETED', 'ARCHIVED'])->count(),
                'rejected' => (clone $visible)->where('status', 'REJECTED')->count(),
            ],
            'recentDocs' => $listQuery->with(['creator', 'type', 'priority', 'confidentiality', 'numberAllocation', 'workflow', 'routes'])
                ->latest()->limit(10)->get(),
            'searchResults' => $searchResults,
        ];
    }

    private function applyFilter(Builder $query, string $filter): void
    {
        if ($filter === 'waiting') {
            $query->whereIn('status', ['DRAFT', 'REGISTERED', 'IN_REVIEW']);
        } elseif ($filter === 'approved') {
            $query->whereIn('status', ['APPROVED', 'COMPLETED', 'ARCHIVED']);
        } elseif ($filter === 'rejected') {
            $query->where('status', 'REJECTED');
        }
    }

    private function visibleQuery(LegacyUser $user): Builder
    {
        $query = Document::query();
        if ($user->hasRole('super-admin')) { return $query; }

        return $query->where(function (Builder $documents) use ($user) {
            $documents->where('created_by', $user->id)
                ->orWhereHas('workflow', fn (Builder $workflow) => $workflow
                    ->whereHas('steps', fn (Builder $steps) => $steps
                        ->where('assigned_user_id', $user->id)
                        ->whereColumn('workflow_steps.step_order', '<=', 'workflow_instances.current_step')))
                ->orWhereHas('assignments', fn ($assignments) => $assignments
                    ->where('assigned_user_id', $user->id)
                    ->whereIn('status', ['PENDING', 'ACKNOWLEDGED', 'IN_PROGRESS', 'COMPLETED']));
            if ($user->hasRole('head')) {
                $names = array_values(array_filter([$user->department, $user->division, $user->work_unit]));
                $documents->orWhereHas('assignments', fn ($assignments) => $assignments
                    ->whereIn('status', ['PENDING', 'ACKNOWLEDGED', 'IN_PROGRESS', 'COMPLETED'])
                    ->whereHas('unit', fn ($units) => $units->whereIn('name', $names)));
            }
            if ($user->hasRole('saraban')) {
                $documents->orWhereIn('status', ['REGISTERED', 'IN_REVIEW', 'APPROVED', 'REJECTED', 'COMPLETED', 'ARCHIVED']);
            }
        });
    }
}
