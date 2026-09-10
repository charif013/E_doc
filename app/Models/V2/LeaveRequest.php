<?php

namespace App\Models\V2;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Casts\Attribute;

class LeaveRequest extends V2Model
{
    use SoftDeletes;

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'total_days' => 'decimal:2',
    ];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function type(): BelongsTo { return $this->belongsTo(LeaveType::class, 'leave_type_id'); }
    public function delegate(): BelongsTo { return $this->belongsTo(User::class, 'delegate_user_id'); }
    public function workflow(): HasOne { return $this->hasOne(WorkflowInstance::class); }
    public function numberAllocation(): HasOne { return $this->hasOne(NumberAllocation::class); }

    protected function leaveType(): Attribute { return Attribute::get(fn () => $this->type?->name); }
    protected function status(): Attribute { return Attribute::get(fn () => $this->workflow?->status?->value ?? 'PENDING'); }
    protected function workflowStatus(): Attribute { return Attribute::get(fn () => $this->workflow?->status?->value); }
    protected function runningNumber(): Attribute { return Attribute::get(fn () => $this->numberAllocation?->running_number); }
    protected function numberedBy(): Attribute { return Attribute::get(fn () => $this->numberAllocation?->allocated_by); }
    protected function numberedAt(): Attribute { return Attribute::get(fn () => $this->numberAllocation?->allocated_at); }
}
