<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RoomBooking extends Model
{
    use HasFactory;

    protected $fillable = [
        'title', 'booking_type', 'description', 
        'room_id', 'created_by', 'start_time', 'end_time'
    ];

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function attendees()
    {
        return $this->belongsToMany(User::class, 'booking_user')
                    ->withPivot('status')
                    ->withTimestamps();
    }

    // ความสัมพันธ์: 1 การจอง มีคนถูกเชิญได้หลายคน
    public function invitees()
    {
        return $this->belongsToMany(User::class, 'room_booking_user');
    }
}