<?php

namespace App\Models\V2;

use App\Enums\WorkflowStepStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Builder;

class WorkflowStep extends V2Model
{
    protected $casts = ['status' => WorkflowStepStatus::class, 'acted_at' => 'datetime'];

    public function workflow(): BelongsTo { return $this->belongsTo(WorkflowInstance::class, 'workflow_instance_id'); }
    public function assignee(): BelongsTo { return $this->belongsTo(User::class, 'assigned_user_id'); }
    public function user(): BelongsTo { return $this->assignee(); }
    public function evidence(): HasMany { return $this->hasMany(WorkflowActionEvidence::class); }
    protected function actionedAt(): Attribute { return Attribute::get(fn () => $this->acted_at); }

    protected static function booted(): void
    {
        static::addGlobalScope('active_workflow', fn (Builder $query) => $query->whereHas('workflow'));
    }
}
