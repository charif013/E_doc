<?php

namespace App\Providers;

use App\Models\Document;
use App\Models\DocumentAccessRequest;
use App\Models\LeaveRequest;
use App\Models\Room;
use App\Models\RoomBooking;
use App\Models\User;
use App\Observers\AuditObserver;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use App\Models\V2\Document as V2Document;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Document::observe(AuditObserver::class);
        DocumentAccessRequest::observe(AuditObserver::class);
        LeaveRequest::observe(AuditObserver::class);
        RoomBooking::observe(AuditObserver::class);
        Room::observe(AuditObserver::class);
        User::observe(AuditObserver::class);

        View::composer('layouts.app', function ($view) {
            $user = auth()->user();
            $assignedPendingCount = 0;
            $reviewPendingCount = 0;
            $leaveReviewPendingCount = 0;
            $numberingPendingCount = 0;

            if ($user) {
                $leaveDelegatePendingCount = LeaveRequest::assignedToDelegate($user->id)
                    ->atWorkflowStage('pending_delegate')
                    ->count();

                if (config('edoc.v2.document_reads')) {
                    $roleIds = $user->roles()->pluck('roles.id');
                    $unitNames = array_values(array_filter([$user->department, $user->division, $user->work_unit]));
                    $assignedPendingCount = V2Document::whereIn('status', ['APPROVED', 'COMPLETED', 'ARCHIVED'])
                        ->whereHas('assignments', function ($assignments) use ($user, $unitNames) {
                            $assignments->whereIn('status', ['PENDING', 'IN_PROGRESS', 'DELEGATED'])
                                ->where(function ($recipient) use ($user, $unitNames) {
                                    $recipient->where('assigned_user_id', $user->id);
                                    if ($user->hasRole('head') && $unitNames !== []) {
                                        $recipient->orWhere(function ($unitAssignment) use ($unitNames) {
                                            $unitAssignment->whereNull('assigned_user_id')
                                                ->whereHas('unit', fn ($units) => $units->whereIn('name', $unitNames));
                                        });
                                    }
                                });
                        })->count();
                    $reviewPendingCount = $user->hasRole('super-admin')
                        ? V2Document::whereNotIn('status', ['DRAFT', 'APPROVED', 'REJECTED', 'CANCELED', 'COMPLETED', 'ARCHIVED'])->count()
                        : V2Document::whereHas('workflow', fn ($workflow) => $workflow
                            ->whereHas('steps', fn ($steps) => $steps
                                ->where('status', 'PENDING')
                                ->whereColumn('workflow_steps.step_order', 'workflow_instances.current_step')
                                ->where(fn ($assignee) => $assignee
                                    ->where('assigned_user_id', $user->id)
                                    ->orWhereIn('assigned_role_id', $roleIds))))->count();
                    if ($user->hasAnyRole(['super-admin', 'saraban'])) {
                        $numberingPendingCount = V2Document::where('status', 'APPROVED')
                            ->whereHas('type', fn ($types) => $types->where('code', 'INTERNAL'))
                            ->whereDoesntHave('numberAllocation')
                            ->count();
                    }
                } else {
                    $assignedPendingCount = Document::whereIn('status', ['APPROVED', 'COMPLETED', 'ARCHIVED'])
                        ->where(function ($documents) use ($user) {
                        $documents->where(fn ($assigned) => $assigned
                            ->where('assigned_user_id', $user->id)
                            ->whereIn('assignment_status', ['pending', 'delegated', 'in_progress']));
                        if ($user->hasRole('head')) {
                            $documents->orWhere(fn ($unit) => $unit
                                ->where('assigned_to', $user->department)
                                ->whereNull('assigned_user_id')
                                ->where('assignment_status', 'pending'));
                        }
                    })->count();
                    $reviewPendingCount = Document::whereHas('routes', fn ($routes) => $routes
                        ->where('user_id', $user->id)
                        ->where('status', 'pending')
                        ->whereColumn('document_routes.step_order', 'documents.current_step'))->count();
                    if ($user->hasAnyRole(['super-admin', 'saraban'])) {
                        $numberingPendingCount = Document::where('status', 'WAITING_NUMBERING')->count();
                    }
                }

                $assignedPendingCount += $leaveDelegatePendingCount;
                $leaveReviewPendingCount = LeaveRequest::pendingReviewFor($user)->count();
            }

            $view->with(compact(
                'assignedPendingCount', 'reviewPendingCount', 'leaveReviewPendingCount', 'numberingPendingCount'
            ));
        });
    }
}
