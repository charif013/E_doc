<?php

namespace App\Models\V2;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentReference extends V2Model
{
    public const UPDATED_AT = null;

    public function document(): BelongsTo { return $this->belongsTo(Document::class); }
    public function referencedDocument(): BelongsTo { return $this->belongsTo(Document::class, 'referenced_document_id'); }

    protected static function booted(): void
    {
        static::addGlobalScope('active_documents', fn (Builder $query) => $query
            ->whereHas('document')->whereHas('referencedDocument'));
    }
}
