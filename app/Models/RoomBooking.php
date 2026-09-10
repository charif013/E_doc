<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use LogicException;

class RoomBooking extends Model
{
    use HasFactory;

    protected $fillable = [
        'title', 'booking_type', 'description', 'document_id',
        'room_id', 'room_name_snapshot', 'created_by', 'start_time', 'end_time',
        'status', 'approved_by', 'approved_at', 'cancelled_at',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'approved_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    protected function bookingType(): Attribute
    {
        return Attribute::get(fn ($value) => strtolower((string) $value));
    }

    protected function status(): Attribute
    {
        return Attribute::get(fn ($value) => strtoupper((string) ($value ?: 'APPROVED')));
    }

    /** สถานะที่ผู้ใช้เห็น คำนวณจากเวลาจริงโดยไม่ต้องรอ Cron อัปเดตฐานข้อมูล */
    protected function lifecycleStatus(): Attribute
    {
        return Attribute::get(function () {
            if ($this->status === 'CANCELED') {
                return 'CANCELED';
            }
            if ($this->end_time && now()->greaterThanOrEqualTo($this->end_time)) {
                return 'COMPLETED';
            }
            if ($this->start_time && now()->greaterThanOrEqualTo($this->start_time)) {
                return 'IN_PROGRESS';
            }

            return $this->status === 'PENDING' ? 'PENDING' : 'UPCOMING';
        });
    }

    public function canBeCanceledNow(): bool
    {
        return $this->lifecycle_status !== 'CANCELED'
            && $this->start_time
            && now()->lessThan($this->start_time);
    }

    protected static function booted(): void
    {
        static::deleting(fn () => throw new LogicException('Room bookings must be canceled, not deleted.'));
    }

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
        return $this->belongsTo(config('edoc.v2.enabled') ? \App\Models\V2\Document::class : Document::class);
    }

    public function getRoomDisplayNameAttribute(): string
    {
        return $this->room_name_snapshot ?: ($this->room?->name ?: '-');
    }

    public function invitees()
    {
        return $this->belongsToMany(User::class, config('edoc.v2.enabled') ? 'room_booking_participants' : 'room_booking_user')
            ->withPivot(['participant_role', 'status', 'responded_at'])
            ->withTimestamps();
    }
}
