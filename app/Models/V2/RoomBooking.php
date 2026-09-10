<?php

namespace App\Models\V2;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use LogicException;

class RoomBooking extends V2Model
{
    protected $casts = [
        'start_time' => 'datetime', 'end_time' => 'datetime',
        'approved_at' => 'datetime', 'cancelled_at' => 'datetime',
    ];

    public function room(): BelongsTo { return $this->belongsTo(Room::class)->withTrashed(); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function document(): BelongsTo { return $this->belongsTo(Document::class); }
    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'room_booking_participants')
            ->withPivot(['participant_role', 'status', 'responded_at'])->withTimestamps();
    }

    protected static function booted(): void
    {
        static::deleting(fn () => throw new LogicException('Room bookings must be canceled, not deleted.'));
    }
}
