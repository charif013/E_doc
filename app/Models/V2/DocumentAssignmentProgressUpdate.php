<?php

namespace App\Models\V2;

use App\Enums\AssignmentStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentAssignmentProgressUpdate extends V2Model
{
    public const UPDATED_AT = null;

    protected $casts = [
        'status' => AssignmentStatus::class,
        'progress_percent' => 'integer',
        'created_at' => 'datetime',
    ];

    public function assignment(): BelongsTo { return $this->belongsTo(DocumentAssignment::class, 'document_assignment_id'); }
    public function updater(): BelongsTo { return $this->belongsTo(User::class, 'updated_by'); }
}
