<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveRequestAttachment extends Model
{
    protected $fillable = [
        'stored_path', 'original_name', 'mime_type', 'size_bytes',
    ];

    protected $casts = [
        'size_bytes' => 'integer',
    ];

    public function leaveRequest(): BelongsTo
    {
        return $this->belongsTo(LeaveRequest::class);
    }
}
