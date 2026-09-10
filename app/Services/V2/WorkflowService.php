<?php

namespace App\Services\V2;

use App\Models\V2\User;
use App\Models\V2\WorkflowActionEvidence;
use App\Models\V2\WorkflowInstance;
use App\Models\V2\WorkflowStep;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WorkflowService
{
    public function __construct(private V2WriteGuard $guard) {}

    public function act(WorkflowInstance $workflow, User $actor, string $action, ?string $comment = null, ?string $requestId = null, ?string $evidenceAction = null): WorkflowInstance
    {
        $this->guard->ensureEnabled();
        $action = strtoupper($action);
        if (! in_array($action, ['APPROVED', 'REJECTED'], true)) {
            throw ValidationException::withMessages(['action' => 'การดำเนินการไม่ถูกต้อง']);
        }

        return DB::connection($workflow->getConnectionName())->transaction(function () use ($workflow, $actor, $action, $comment, $requestId, $evidenceAction) {
            $locked = WorkflowInstance::whereKey($workflow->id)->lockForUpdate()->firstOrFail();
            $step = WorkflowStep::where('workflow_instance_id', $locked->id)
                ->where('step_order', $locked->current_step)
                ->where('status', 'PENDING')->lockForUpdate()->firstOrFail();
            if ($step->assigned_user_id && $step->assigned_user_id !== $actor->id) {
                abort(403, 'ขั้นตอนนี้ไม่ได้มอบหมายให้ผู้ใช้ปัจจุบัน');
            }

            $signature = $actor->signatures()->where('is_active', true)
                ->whereNull('revoked_at')->latest('id')->first();
            $definition = $locked->definition()->first();
            $actedAt = now();
            $step->update(['status' => $action, 'comment' => $comment, 'acted_at' => $actedAt]);
            WorkflowActionEvidence::create([
                'workflow_step_id' => $step->id,
                'workflow_instance_id' => $locked->id,
                'resource_type' => $locked->document_id ? 'DOCUMENT' : 'LEAVE',
                'resource_id' => $locked->document_id ?: $locked->leave_request_id,
                'workflow_code' => $definition?->code,
                'workflow_version' => $definition?->version,
                'step_order' => $step->step_order,
                'step_name' => $step->step_name,
                'actor_id' => $actor->id,
                'action' => $evidenceAction ?: $action, 'actor_name' => $actor->name,
                'actor_position' => $actor->position,
                'signature_path' => $signature?->file_path,
                'signature_snapshot' => null,
                'signature_sha256' => $signature?->sha256,
                'comment' => $comment, 'ip_address' => request()?->ip(),
                'request_id' => $requestId, 'acted_at' => $actedAt, 'created_at' => $actedAt,
            ]);

            if ($action === 'REJECTED') {
                $locked->update(['status' => 'REJECTED', 'completed_at' => $actedAt]);
                $this->syncResourceStatus($locked, 'REJECTED');

                return $locked->fresh('steps');
            }

            $next = WorkflowStep::where('workflow_instance_id', $locked->id)
                ->where('step_order', '>', $step->step_order)->orderBy('step_order')->first();
            if ($next) {
                $locked->update(['status' => 'IN_PROGRESS', 'current_step' => $next->step_order]);
            } else {
                $locked->update(['status' => 'APPROVED', 'completed_at' => $actedAt]);
                $this->syncResourceStatus($locked, 'APPROVED');
            }

            return $locked->fresh('steps');
        }, 3);
    }

    private function syncResourceStatus(WorkflowInstance $workflow, string $status): void
    {
        if ($workflow->document_id) {
            $workflow->document()->update(['status' => $status]);
        }
        // A leave's lifecycle is the workflow instance itself. Unlike documents,
        // leave_requests deliberately has no mirrored status column.
    }
}
