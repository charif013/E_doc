<?php

namespace App\Services\V2;

use App\Models\LeaveRequest;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;

/**
 * Creates missing workflow structure only. Runtime state always flows from the
 * workflow tables; this service never copies approval state back from a leave.
 */
class LeaveWorkflowSynchronizer
{
    private const DEFINITION_CODE = 'APPLICATION_LEAVE';
    private const DEFINITION_VERSION = 2;

    private const STEPS = [
        1 => ['name' => 'Delegate response', 'action' => 'ACCEPTANCE', 'role' => null, 'required' => false],
        2 => ['name' => 'Department head approval', 'action' => 'APPROVAL', 'role' => 'head', 'required' => true],
        3 => ['name' => 'HR inspection', 'action' => 'APPROVAL', 'role' => 'hr', 'required' => true],
        5 => ['name' => 'Palad approval', 'action' => 'APPROVAL', 'role' => 'palad', 'required' => true],
        6 => ['name' => 'Executive approval', 'action' => 'APPROVAL', 'role' => 'executive', 'required' => true],
    ];

    public function synchronize(LeaveRequest $leave): void
    {
        $db = DB::connection($leave->getConnectionName());
        $definitionId = $this->ensureDefinition($db);
        $instance = $db->table('workflow_instances')->where('leave_request_id', $leave->id)->first();

        $owner = $leave->user ?? \App\Models\User::find($leave->user_id);
        $isHead = $owner?->hasRole('head') ?? false;
        $isPalad = $owner?->hasRole('palad') ?? false;

        if (! $instance) {
            $defaultFirstStep = ($isHead || $isPalad) ? 3 : 2;
            $currentStep = $leave->delegate_user_id ? 1 : $defaultFirstStep;
            $instanceId = $db->table('workflow_instances')->insertGetId([
                'workflow_definition_id' => $definitionId,
                'document_id' => null,
                'leave_request_id' => $leave->id,
                'status' => 'IN_PROGRESS',
                'current_step' => $currentStep,
                'started_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $instanceId = (int) $instance->id;
        }

        foreach (self::STEPS as $order => $definition) {
            $definitionStepId = $db->table('workflow_definition_steps')
                ->where('workflow_definition_id', $definitionId)->where('step_order', $order)->value('id');
            if ($db->table('workflow_steps')->where('workflow_instance_id', $instanceId)->where('step_order', $order)->exists()) {
                continue;
            }
            $roleId = $definition['role']
                ? $db->table('roles')->where('name', $definition['role'])->value('id')
                : null;

            $status = 'PENDING';
            if ($order === 1 && ! $leave->delegate_user_id) {
                $status = 'SKIPPED';
            } elseif ($order === 2 && ($isHead || $isPalad)) {
                $status = 'SKIPPED';
            } elseif ($order === 5 && $isPalad) {
                $status = 'SKIPPED';
            }

            $db->table('workflow_steps')->insert([
                'workflow_instance_id' => $instanceId,
                'workflow_definition_step_id' => $definitionStepId,
                'step_order' => $order,
                'step_name' => $definition['name'],
                'action_type' => $definition['action'],
                'assigned_role_id' => $roleId,
                'assigned_user_id' => $order === 1 ? $leave->delegate_user_id : null,
                'status' => $status,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function ensureDefinition(ConnectionInterface $db): int
    {
        $definition = $db->table('workflow_definitions')
            ->where('code', self::DEFINITION_CODE)->where('version', self::DEFINITION_VERSION)->first();
        $now = now();
        $definitionId = $definition?->id ?: $db->table('workflow_definitions')->insertGetId([
            'code' => self::DEFINITION_CODE,
            'name' => 'Application leave workflow',
            'resource_type' => 'LEAVE',
            'version' => self::DEFINITION_VERSION,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        foreach (self::STEPS as $order => $step) {
            $roleId = $step['role'] ? $db->table('roles')->where('name', $step['role'])->value('id') : null;
            $db->table('workflow_definition_steps')->updateOrInsert(
                ['workflow_definition_id' => $definitionId, 'step_order' => $order],
                [
                    'name' => $step['name'], 'required_role_id' => $roleId,
                    'required_user_id' => null, 'action_type' => $step['action'],
                    'is_required' => $step['required'], 'required_approvals' => 1,
                    'created_at' => $now, 'updated_at' => $now,
                ]
            );
        }

        return (int) $definitionId;
    }
}
