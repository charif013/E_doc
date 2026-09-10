<?php

namespace App\Models\V2;

use Illuminate\Database\Eloquent\Relations\HasMany;

class NumberSequence extends V2Model
{
    public function allocations(): HasMany { return $this->hasMany(NumberAllocation::class, 'sequence_id'); }
}
