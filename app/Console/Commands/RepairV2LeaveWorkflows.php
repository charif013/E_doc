<?php

namespace App\Console\Commands;

use App\Models\LeaveRequest;
use App\Services\V2\LeaveWorkflowSynchronizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RepairV2LeaveWorkflows extends Command
{
    protected $signature = 'edoc:v2-repair-leave-workflows';

    protected $description = 'Create canonical workflow steps and immutable evidence for existing V2 leave requests';

    public function handle(LeaveWorkflowSynchronizer $synchronizer): int
    {
        if (! config('edoc.v2.enabled')) {
            $this->components->error('V2 must be enabled before repairing leave workflows.');

            return self::FAILURE;
        }

        $connection = (string) config('edoc.v2.connection', 'mysql_v2');
        $processed = 0;
        LeaveRequest::on($connection)->orderBy('id')->chunkById(100, function ($leaves) use ($synchronizer, &$processed) {
            foreach ($leaves as $leave) {
                DB::connection($leave->getConnectionName())->transaction(
                    fn () => $synchronizer->synchronize($leave),
                    3
                );
                $processed++;
            }
        });

        $db = DB::connection($connection);
        $emptyCompletedSteps = $db->table('workflow_steps as steps')
            ->join('workflow_instances as instances', 'instances.id', '=', 'steps.workflow_instance_id')
            ->leftJoin('workflow_action_evidence as evidence', 'evidence.workflow_step_id', '=', 'steps.id')
            ->whereNotNull('instances.leave_request_id')
            ->whereIn('steps.status', ['APPROVED', 'REJECTED'])
            ->whereNull('steps.assigned_user_id')->whereNull('steps.acted_at')->whereNull('evidence.id')
            ->pluck('steps.id');
        if ($emptyCompletedSteps->isNotEmpty()) {
            $db->table('workflow_steps')->whereIn('id', $emptyCompletedSteps)->update([
                'status' => 'SKIPPED', 'updated_at' => now(),
            ]);
        }
        $invalid = $db->table('workflow_instances as instances')
            ->leftJoin('workflow_steps as steps', function ($join) {
                $join->on('steps.workflow_instance_id', '=', 'instances.id')
                    ->on('steps.step_order', '=', 'instances.current_step')
                    ->where('steps.status', 'PENDING');
            })
            ->whereNotNull('instances.leave_request_id')
            ->where('instances.status', 'IN_PROGRESS')
            ->whereNull('steps.id')
            ->count();

        $missingEvidence = $db->table('workflow_steps as steps')
            ->join('workflow_instances as instances', 'instances.id', '=', 'steps.workflow_instance_id')
            ->leftJoin('workflow_action_evidence as evidence', 'evidence.workflow_step_id', '=', 'steps.id')
            ->whereNotNull('instances.leave_request_id')->whereIn('steps.status', ['APPROVED', 'REJECTED'])
            ->whereNull('evidence.id')->count();

        $this->table(['Processed leaves', 'Normalized empty steps', 'Evidence rows', 'Invalid active workflows', 'Missing evidence'], [[
            $processed,
            $emptyCompletedSteps->count(),
            $db->table('workflow_action_evidence')->where('resource_type', 'LEAVE')->count(),
            $invalid,
            $missingEvidence,
        ]]);

        if ($invalid > 0 || $missingEvidence > 0) {
            $this->components->error('Some leave workflows remain inconsistent or have actions without evidence.');

            return self::FAILURE;
        }

        $this->components->info('V2 leave workflows repaired successfully.');

        return self::SUCCESS;
    }
}
