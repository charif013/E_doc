<?php

namespace App\Models\V2;

use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaveType extends V2Model
{
    protected $casts = [
        'max_days' => 'decimal:2',
        'requires_attachment' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function requests(): HasMany { return $this->hasMany(LeaveRequest::class); }
}
