<?php

namespace App\Models\V2;

use Illuminate\Database\Eloquent\Relations\HasMany;

class Position extends V2Model
{
    protected $casts = ['is_active' => 'boolean'];

    public function users(): HasMany { return $this->hasMany(User::class); }
}
