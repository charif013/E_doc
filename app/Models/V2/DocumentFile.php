<?php

namespace App\Models\V2;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class DocumentFile extends V2Model
{
    protected $casts = ['downloaded_at' => 'datetime'];

    public function document(): BelongsTo { return $this->belongsTo(Document::class); }
    public function uploader(): BelongsTo { return $this->belongsTo(User::class, 'uploaded_by'); }

    protected static function booted(): void
    {
        static::addGlobalScope('active_document', fn (Builder $query) => $query->whereHas('document'));
    }
}
