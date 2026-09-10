<?php

namespace App\Models\V2;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Room extends V2Model
{
    use SoftDeletes;

    public function bookings(): HasMany { return $this->hasMany(RoomBooking::class); }
}
