<?php

namespace App\Models\V2;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OutgoingDelivery extends V2Model
{
    protected $casts = [
        'sent_at' => 'datetime',
        'received_at' => 'datetime',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }
}
