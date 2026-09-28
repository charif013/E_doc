<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentRoutePreset extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'route_user_ids',
    ];

    protected $casts = [
        'route_user_ids' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
