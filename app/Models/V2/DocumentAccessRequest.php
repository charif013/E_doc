<?php

namespace App\Models\V2;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class DocumentAccessRequest extends V2Model
{
    protected $casts = [
        'reviewed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function document(): BelongsTo { return $this->belongsTo(Document::class); }
    public function requester(): BelongsTo { return $this->belongsTo(User::class, 'requested_by'); }
    public function user(): BelongsTo { return $this->requester(); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'reviewed_by'); }

    protected static function booted(): void
    {
        static::addGlobalScope('active_document', fn (Builder $query) => $query->whereHas('document'));
    }
}
