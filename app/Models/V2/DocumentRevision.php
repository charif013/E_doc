<?php

namespace App\Models\V2;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentRevision extends V2Model
{
    public $timestamps = false;

    protected $casts = [
        'snapshot' => 'array',
        'created_at' => 'datetime',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
