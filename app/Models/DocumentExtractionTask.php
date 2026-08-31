<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class DocumentExtractionTask extends Model
{
    use HasUuids;

    protected $fillable = [
        'id',
        'user_id',
        'file_path',
        'original_name',
        'status',
        'result',
        'error_message',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'result' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];
}
