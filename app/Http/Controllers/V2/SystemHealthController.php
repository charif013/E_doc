<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class SystemHealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $db = DB::connection(config('edoc.v2.connection'));
        $counts = [];
        foreach (['users', 'documents', 'leave_requests', 'workflow_instances', 'workflow_steps', 'number_allocations', 'rooms', 'room_bookings', 'audit_logs'] as $table) {
            $counts[$table] = $db->table($table)->count();
        }

        $invalidAllocations = $db->table('number_allocations')
            ->whereRaw('(document_id IS NULL) = (leave_request_id IS NULL)')->count();
        $invalidWorkflows = $db->table('workflow_instances')
            ->whereRaw('(document_id IS NULL) = (leave_request_id IS NULL)')->count();
        $missingCurrentSteps = $db->table('workflow_instances as instances')
            ->leftJoin('workflow_steps as steps', function ($join) {
                $join->on('steps.workflow_instance_id', '=', 'instances.id')
                    ->on('steps.step_order', '=', 'instances.current_step')
                    ->where('steps.status', 'PENDING');
            })
            ->where('instances.status', 'IN_PROGRESS')
            ->whereNull('steps.id')
            ->count();
        $staleBefore = now()->subMinutes((int) config('edoc.queue.stale_after_minutes', 10))->timestamp;
        $staleJobs = $db->table('jobs')->where('available_at', '<=', $staleBefore)->count();
        $healthy = $invalidAllocations === 0 && $invalidWorkflows === 0
            && $missingCurrentSteps === 0 && $staleJobs === 0;

        return response()->json([
            'status' => $healthy ? 'ok' : 'degraded',
            'write_enabled' => (bool) config('edoc.v2.write_enabled'),
            'database' => $db->getDatabaseName(),
            'counts' => $counts,
            'checks' => [
                'invalid_allocations' => $invalidAllocations,
                'invalid_workflows' => $invalidWorkflows,
                'active_workflows_without_current_step' => $missingCurrentSteps,
                'stale_jobs' => $staleJobs,
            ],
        ]);
    }
}
