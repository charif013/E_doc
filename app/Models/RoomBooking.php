<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RoomBooking extends Model
{
    use HasFactory;

    protected $fillable = [
        'title', 'booking_type', 'description', 'document_id',
        'room_id', 'room_name_snapshot', 'created_by', 'start_time', 'end_time',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
    ];

    public function room()
    {
        return $this->belongsTo(Room::class)->withTrashed();
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function document()
    {
        return $this->belongsTo(Document::class);
    }

    public function getRoomDisplayNameAttribute(): string
    {
        return $this->room_name_snapshot ?: ($this->room?->name ?: '-');
    }

    public function invitees()
    {
        return $this->belongsToMany(User::class, 'room_booking_user')
            ->withPivot('status')
            ->withTimestamps();
    }
}
