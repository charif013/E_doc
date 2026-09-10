<?php

namespace App\Models\V2;

use App\Enums\AssignmentStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class DocumentAssignment extends V2Model
{
    protected $casts = [
        'status' => AssignmentStatus::class,
        'assigned_at' => 'datetime',
        'acknowledged_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function document(): BelongsTo { return $this->belongsTo(Document::class); }
    public function assignee(): BelongsTo { return $this->belongsTo(User::class, 'assigned_user_id'); }
    public function unit(): BelongsTo { return $this->belongsTo(OrganizationUnit::class, 'assigned_unit_id'); }

    protected static function booted(): void
    {
        static::addGlobalScope('active_document', fn (Builder $query) => $query->whereHas('document'));
    }
}
