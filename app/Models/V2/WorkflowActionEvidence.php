<?php

namespace App\Models\V2;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class WorkflowActionEvidence extends V2Model
{
    public const UPDATED_AT = null;

    protected $table = 'workflow_action_evidence';

    protected $casts = ['acted_at' => 'datetime'];

    public function step(): BelongsTo
    {
        return $this->belongsTo(WorkflowStep::class, 'workflow_step_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Workflow evidence is append-only.'));
        static::deleting(fn () => throw new LogicException('Workflow evidence is append-only.'));
    }
}
