<?php

namespace App\Models\V2;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class NumberAllocation extends V2Model
{
    public $timestamps = false;
    protected $casts = ['allocated_at' => 'datetime'];

    public function sequence(): BelongsTo { return $this->belongsTo(NumberSequence::class); }
    public function document(): BelongsTo { return $this->belongsTo(Document::class); }
    public function leaveRequest(): BelongsTo { return $this->belongsTo(LeaveRequest::class); }

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
