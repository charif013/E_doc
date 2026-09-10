<?php

namespace App\Models;

use App\Models\V2\LeaveType;
use App\Models\V2\NumberAllocation;
use App\Models\V2\WorkflowActionEvidence;
use App\Models\V2\WorkflowInstance;
use App\Services\V2\LeaveWorkflowSynchronizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class LeaveRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'leave_type_id', 'leave_type', 'start_date', 'end_date', 'total_days',
        'reason', 'contact_info', 'delegate_user_id', 'delegate_id',
        'status', 'reject_reason', 'workflow_status', 'delegate_status', 'delegate_decline_reason',
        'delegate_requested_at', 'delegate_responded_at', 'delegate_reminded_at', 'delegate_escalated_at',
        'leave_number', 'running_number', 'numbered_by', 'numbered_at',
        'supervisor_id', 'supervisor_signature', 'supervisor_approved_at',
        'inspector_id', 'inspector_status', 'inspector_signature', 'inspector_at',
        'head_id', 'head_status', 'head_signature', 'head_at',
        'palad_id', 'palad_status', 'palad_signature', 'palad_at', 'palad_approved_at',
        'nayok_id', 'nayok_status', 'nayok_signature', 'nayok_at', 'nayok_approved_at',
    ];

    protected $casts = [
        'start_date' => 'date', 'end_date' => 'date', 'total_days' => 'decimal:2',
        'delegate_requested_at' => 'datetime', 'delegate_responded_at' => 'datetime',
        'delegate_reminded_at' => 'datetime', 'delegate_escalated_at' => 'datetime',
        'numbered_at' => 'datetime',
    ];

    public function user(): BelongsTo { return $this->belongsTo(User::class, 'user_id'); }
    public function type(): BelongsTo { return $this->belongsTo(LeaveType::class, 'leave_type_id'); }
    public function delegate(): BelongsTo { return $this->belongsTo(User::class, 'delegate_user_id'); }
    public function workflow(): HasOne { return $this->hasOne(WorkflowInstance::class, 'leave_request_id'); }
    public function numberAllocation(): HasOne { return $this->hasOne(NumberAllocation::class, 'leave_request_id'); }

    protected static function booted(): void
    {
        if (Schema::connection((new static)->getConnectionName())->hasColumn('leave_requests', 'deleted_at')) {
            static::addGlobalScope('not_deleted', fn (Builder $query) => $query->whereNull('leave_requests.deleted_at'));
        }
        static::created(function (LeaveRequest $leave) {
            if (config('edoc.v2.enabled')
                && Schema::connection($leave->getConnectionName())->hasTable('workflow_instances')) {
                app(LeaveWorkflowSynchronizer::class)->synchronize($leave);
            }
        });
    }

    protected function leaveType(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $this->usesCanonicalWorkflow() ? $this->type?->name : $value,
            set: function (?string $name) {
                if (! $name) { return []; }
                if (! $this->usesCanonicalWorkflow()) { return $name; }
                $db = DB::connection($this->getConnectionName());
                $type = $db->table('leave_types')->where('name', $name)->first();
                $id = $type?->id ?: $db->table('leave_types')->insertGetId([
                    'code' => 'CUSTOM_'.strtoupper(substr(hash('sha256', $name), 0, 16)),
                    'name' => $name, 'requires_attachment' => false, 'is_active' => true,
                    'created_at' => now(), 'updated_at' => now(),
                ]);

                return ['leave_type_id' => $id];
            },
        );
    }

    protected function delegateId(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $this->usesCanonicalWorkflow() ? $this->delegate_user_id : $value,
            set: fn ($value) => $this->usesCanonicalWorkflow() ? ['delegate_user_id' => $value] : $value,
        );
    }

    protected function status(): Attribute
    {
        return Attribute::get(function ($value) {
            if (! $this->usesCanonicalWorkflow()) { return $value; }
            $status = strtoupper((string) $this->workflow?->status?->value);

            return in_array($status, ['APPROVED', 'REJECTED', 'CANCELED', 'COMPLETED'], true)
                ? $status : 'PENDING';
        });
    }

    protected function workflowStatus(): Attribute
    {
        return Attribute::get(function ($value) {
            if (! $this->usesCanonicalWorkflow()) { return $value; }
            $workflow = $this->workflow;
            $terminal = strtolower((string) $workflow?->status?->value);
            if (in_array($terminal, ['approved', 'rejected', 'canceled', 'completed'], true)) {
                return $terminal;
            }
            if (! $workflow) { return null; }
            if ((int) $workflow->current_step === 1) {
                $lastAction = $this->evidenceForStep(1)?->action;
                if ($lastAction === 'DECLINED') { return 'delegate_declined'; }
                if ($lastAction === 'ESCALATED') { return 'delegate_escalated'; }
            }

            return match ((int) $workflow->current_step) {
                1 => 'pending_delegate', 2 => 'pending_head', 3 => 'pending_inspector',
                4 => 'pending_numbering', 5 => 'pending_palad', 6 => 'pending_nayok',
                default => null,
            };
        });
    }

    protected function delegateStatus(): Attribute
    {
        return Attribute::get(fn ($value) => ! $this->usesCanonicalWorkflow() ? $value : match ($this->evidenceForStep(1)?->action) {
            'ACCEPTED' => 'accepted', 'DECLINED' => 'declined', default => $this->delegate_user_id ? 'pending' : null,
        });
    }

    protected function delegateDeclineReason(): Attribute { return Attribute::get(fn ($v) => $this->usesCanonicalWorkflow() ? $this->evidenceForStep(1, ['DECLINED'])?->comment : $v); }
    protected function delegateRequestedAt(): Attribute { return Attribute::get(fn ($v) => $this->usesCanonicalWorkflow() ? $this->workflowStep(1)?->created_at : $v); }
    protected function delegateRespondedAt(): Attribute { return Attribute::get(fn ($v) => $this->usesCanonicalWorkflow() ? $this->evidenceForStep(1, ['ACCEPTED', 'DECLINED'])?->acted_at : $v); }
    protected function delegateRemindedAt(): Attribute { return Attribute::get(fn ($v) => $this->usesCanonicalWorkflow() ? $this->evidenceForStep(1, ['REMINDER_SENT'])?->acted_at : $v); }
    protected function delegateEscalatedAt(): Attribute { return Attribute::get(fn ($v) => $this->usesCanonicalWorkflow() ? $this->evidenceForStep(1, ['ESCALATED'])?->acted_at : $v); }
    protected function leaveNumber(): Attribute { return Attribute::get(fn ($v) => $this->usesCanonicalWorkflow() ? $this->numberAllocation?->formatted_number : $v); }
    protected function runningNumber(): Attribute { return Attribute::get(fn ($v) => $this->usesCanonicalWorkflow() ? $this->numberAllocation?->running_number : $v); }
    protected function numberedBy(): Attribute { return Attribute::get(fn () => ($id = $this->usesCanonicalWorkflow() ? $this->numberAllocation?->allocated_by : $this->attributes['numbered_by'] ?? null) ? User::find($id) : null); }
    protected function numberedAt(): Attribute { return Attribute::get(fn ($v) => $this->usesCanonicalWorkflow() ? $this->numberAllocation?->allocated_at : $v); }
    protected function rejectReason(): Attribute { return Attribute::get(fn ($v) => $this->usesCanonicalWorkflow() ? $this->latestEvidence(['REJECTED'])?->comment : $v); }

    public function getInspectorIdAttribute(): ?int { return $this->actorIdForStep(3); }
    public function getHeadIdAttribute(): ?int { return $this->actorIdForStep(2); }
    public function getSupervisorIdAttribute(): ?int { return $this->head_id; }
    public function getPaladIdAttribute(): ?int { return $this->actorIdForStep(5); }
    public function getNayokIdAttribute(): ?int { return $this->actorIdForStep(6); }
    public function getInspectorStatusAttribute(): ?string { return $this->legacyApprovalStatus(3); }
    public function getHeadStatusAttribute(): ?string { return $this->legacyApprovalStatus(2); }
    public function getPaladStatusAttribute(): ?string { return $this->legacyApprovalStatus(5); }
    public function getNayokStatusAttribute(): ?string { return $this->legacyApprovalStatus(6); }
    public function getInspectorAtAttribute($value): mixed { return $this->usesCanonicalWorkflow() ? $this->evidenceForStep(3)?->acted_at : $value; }
    public function getHeadAtAttribute($value): mixed { return $this->usesCanonicalWorkflow() ? $this->evidenceForStep(2)?->acted_at : $value; }
    public function getSupervisorApprovedAtAttribute(): mixed { return $this->head_at; }
    public function getPaladAtAttribute($value): mixed { return $this->usesCanonicalWorkflow() ? $this->evidenceForStep(5)?->acted_at : $value; }
    public function getPaladApprovedAtAttribute(): mixed { return $this->palad_at; }
    public function getNayokAtAttribute($value): mixed { return $this->usesCanonicalWorkflow() ? $this->evidenceForStep(6)?->acted_at : $value; }
    public function getNayokApprovedAtAttribute(): mixed { return $this->nayok_at; }
    public function getInspectorSignatureAttribute(): ?string { return $this->signatureForStep(3); }
    public function getHeadSignatureAttribute(): ?string { return $this->signatureForStep(2); }
    public function getSupervisorSignatureAttribute(): ?string { return $this->head_signature; }
    public function getPaladSignatureAttribute(): ?string { return $this->signatureForStep(5); }
    public function getNayokSignatureAttribute(): ?string { return $this->signatureForStep(6); }
    public function getInspectorAttribute(): ?User { return $this->actorForStep(3); }
    public function getHeadAttribute(): ?User { return $this->actorForStep(2); }
    public function getSupervisorAttribute(): ?User { return $this->head; }
    public function getPaladAttribute(): ?User { return $this->actorForStep(5); }
    public function getNayokAttribute(): ?User { return $this->actorForStep(6); }

    public function scopeAtWorkflowStage(Builder $query, string|array $stages): Builder
    {
        if (! Schema::connection($query->getModel()->getConnectionName())->hasTable('workflow_instances')) {
            return $query->whereIn('workflow_status', (array) $stages);
        }
        $orders = collect((array) $stages)->map(fn ($stage) => match ($stage) {
            'pending_delegate', 'delegate_declined', 'delegate_escalated' => 1,
            'pending_head' => 2, 'pending_inspector' => 3, 'pending_numbering' => 4,
            'pending_palad' => 5, 'pending_nayok' => 6, default => null,
        })->filter()->unique()->values();

        return $query->whereHas('workflow', fn (Builder $workflows) => $workflows
            ->whereIn('status', ['PENDING', 'IN_PROGRESS'])->whereIn('current_step', $orders));
    }

    public function scopeAtCanonicalStatus(Builder $query, string|array $statuses): Builder
    {
        if (! Schema::connection($query->getModel()->getConnectionName())->hasTable('workflow_instances')) {
            return $query->whereIn('status', (array) $statuses);
        }
        $statuses = collect((array) $statuses)->map(fn ($status) => strtoupper($status));
        $workflowStatuses = $statuses->flatMap(fn ($status) => $status === 'PENDING'
            ? ['PENDING', 'IN_PROGRESS'] : [$status])->unique();

        return $query->whereHas('workflow', fn (Builder $workflows) => $workflows->whereIn('status', $workflowStatuses));
    }

    public function scopeAssignedToDelegate(Builder $query, int $userId): Builder
    {
        $column = Schema::connection($query->getModel()->getConnectionName())->hasColumn('leave_requests', 'delegate_user_id')
            ? 'delegate_user_id' : 'delegate_id';

        return $query->where($column, $userId);
    }

    public function scopePendingReviewFor(Builder $query, User $user): Builder
    {
        if ($user->hasRole('hr')) {
            return $query->atWorkflowStage('pending_inspector');
        }
        if ($user->hasRole('saraban')) {
            return $query->atWorkflowStage('pending_numbering');
        }
        if ($user->hasRole('head')) {
            return $query->atWorkflowStage('pending_head')
                ->whereHas('user', fn (Builder $users) => $users
                    ->where('department', $user->department));
        }
        if ($user->hasAnyRole(['palad', 'deputy-palad'])) {
            return $query->atWorkflowStage('pending_palad');
        }
        if ($user->hasRole('executive')) {
            return $query->atWorkflowStage('pending_nayok');
        }
        if ($user->hasRole('super-admin')) {
            return $query->atWorkflowStage([
                'pending_head', 'pending_inspector', 'pending_palad',
                'pending_nayok', 'pending_numbering',
            ]);
        }

        return $query->whereKey(-1);
    }

    private function workflowStep(int $order): mixed
    {
        return $this->workflow?->steps?->firstWhere('step_order', $order)
            ?: $this->workflow?->steps()->where('step_order', $order)->first();
    }

    private function evidenceForStep(int $order, ?array $actions = null): ?WorkflowActionEvidence
    {
        $query = WorkflowActionEvidence::where('resource_type', 'LEAVE')
            ->where('resource_id', $this->getKey())->where('step_order', $order);
        if ($actions) { $query->whereIn('action', $actions); }

        return $query->latest('acted_at')->latest('id')->first();
    }

    private function latestEvidence(array $actions): ?WorkflowActionEvidence
    {
        return WorkflowActionEvidence::where('resource_type', 'LEAVE')->where('resource_id', $this->getKey())
            ->whereIn('action', $actions)->latest('acted_at')->latest('id')->first();
    }

    private function actorIdForStep(int $order): ?int
    {
        if (! $this->usesCanonicalWorkflow()) {
            $column = [2 => 'head_id', 3 => 'inspector_id', 5 => 'palad_id', 6 => 'nayok_id'][$order] ?? null;
            return $column ? ($this->attributes[$column] ?? null) : null;
        }
        return $this->evidenceForStep($order)?->actor_id;
    }
    private function actorForStep(int $order): ?User { $id = $this->actorIdForStep($order); return $id ? User::find($id) : null; }
    private function legacyApprovalStatus(int $order): ?string
    {
        if (! $this->usesCanonicalWorkflow()) {
            $column = [2 => 'head_status', 3 => 'inspector_status', 5 => 'palad_status', 6 => 'nayok_status'][$order] ?? null;
            return $column ? ($this->attributes[$column] ?? null) : null;
        }
        return $this->evidenceForStep($order)?->action === 'APPROVED' ? 'approved' : null;
    }

    private function signatureForStep(int $order): ?string
    {
        if (! $this->usesCanonicalWorkflow()) {
            $column = [2 => 'head_signature', 3 => 'inspector_signature', 5 => 'palad_signature', 6 => 'nayok_signature'][$order] ?? null;
            return $column ? ($this->attributes[$column] ?? null) : null;
        }
        $evidence = $this->evidenceForStep($order);
        if (! $evidence) { return null; }
        if ($evidence->signature_snapshot) { return $evidence->signature_snapshot; }
        if ($evidence->signature_path && Storage::disk('local')->exists($evidence->signature_path)) {
            return 'data:image/png;base64,'.base64_encode(Storage::disk('local')->get($evidence->signature_path));
        }

        return null;
    }

    private function usesCanonicalWorkflow(): bool
    {
        $connection = $this->getConnectionName();

        return Schema::connection($connection)->hasTable('leave_types')
            && Schema::connection($connection)->hasTable('workflow_instances');
    }
}
