<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Room extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['name', 'capacity', 'status'];

    protected function status(): Attribute
    {
        return Attribute::get(fn ($value) => strtolower((string) $value));
    }

    public function bookings()
    {
        return $this->hasMany(RoomBooking::class);
    }
}
