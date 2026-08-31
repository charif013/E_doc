<?php

namespace App\Services;

use App\Models\Room;
use App\Models\RoomBooking;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RoomBookingService
{
    public function create(array $data, User $creator): RoomBooking
    {
        return DB::transaction(function () use ($data, $creator) {
            // Serialise bookings for the same room. This closes the gap between
            // the overlap check and INSERT when two requests arrive together.
            $room = Room::whereKey($data['room_id'])->lockForUpdate()->firstOrFail();

            $overlap = RoomBooking::where('room_id', $data['room_id'])
                ->where('start_time', '<', $data['end_time'])
                ->where('end_time', '>', $data['start_time'])
                ->exists();

            if ($overlap) {
                throw ValidationException::withMessages([
                    'start_time' => 'ห้องประชุมนี้ถูกจองแล้วในช่วงเวลาดังกล่าว',
                ]);
            }

            $booking = RoomBooking::create([
                'title' => $data['title'],
                'booking_type' => $data['booking_type'],
                'description' => $data['description'] ?? null,
                'document_id' => $data['document_id'] ?? null,
                'room_id' => $data['room_id'],
                'room_name_snapshot' => $room->name,
                'start_time' => $data['start_time'],
                'end_time' => $data['end_time'],
                'created_by' => $creator->id,
            ]);

            if ($booking->booking_type === 'meeting') {
                $booking->invitees()->sync($data['invitees'] ?? []);
            }

            return $booking;
        }, 3);
    }
}
