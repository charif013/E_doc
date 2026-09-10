<?php

namespace App\Models\V2;

use App\Enums\WorkflowStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkflowInstance extends V2Model
{
    protected $casts = [
        'status' => WorkflowStatus::class,
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function leaveRequest(): BelongsTo
    {
        return $this->belongsTo(LeaveRequest::class);
    }

    public function definition(): BelongsTo
    {
        return $this->belongsTo(WorkflowDefinition::class, 'workflow_definition_id');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(WorkflowStep::class)->orderBy('step_order');
    }

    protected static function booted(): void
    {
        static::addGlobalScope('active_resource', function (Builder $query) {
            $query->where(function (Builder $resources) {
                $resources->where(function (Builder $documents) {
                    $documents->whereNotNull('document_id')->whereHas('document');
                })->orWhere(function (Builder $leaves) {
                    $leaves->whereNotNull('leave_request_id')->whereHas('leaveRequest');
                });
            });
        });
    }
}
