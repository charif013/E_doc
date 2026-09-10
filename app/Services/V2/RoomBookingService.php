<?php

namespace App\Services\V2;

use App\Models\V2\Room;
use App\Models\V2\RoomBooking;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RoomBookingService
{
    public function __construct(private V2WriteGuard $guard) {}

    public function create(array $data, int $creatorId): RoomBooking
    {
        $this->guard->ensureEnabled();

        return DB::connection('mysql_v2')->transaction(function () use ($data, $creatorId) {
            $room = Room::whereKey($data['room_id'])->lockForUpdate()->firstOrFail();
            $overlap = RoomBooking::where('room_id', $room->id)
                ->whereIn('status', ['PENDING', 'APPROVED'])
                ->where('start_time', '<', $data['end_time'])
                ->where('end_time', '>', $data['start_time'])
                ->exists();
            if ($overlap) {
                throw ValidationException::withMessages(['start_time' => 'ห้องประชุมนี้ถูกจองแล้วในช่วงเวลาดังกล่าว']);
            }

            $booking = RoomBooking::create([
                'room_id' => $room->id, 'room_name_snapshot' => $room->name,
                'created_by' => $creatorId, 'document_id' => $data['document_id'] ?? null,
                'title' => $data['title'], 'description' => $data['description'] ?? null,
                'booking_type' => strtoupper($data['booking_type'] ?? 'MEETING'),
                'start_time' => $data['start_time'], 'end_time' => $data['end_time'],
                'status' => 'APPROVED', 'approved_by' => $creatorId, 'approved_at' => now(),
            ]);
            $participants = collect($data['participants'] ?? [])->unique()->mapWithKeys(
                fn ($id) => [(int) $id => ['participant_role' => 'ATTENDEE', 'status' => 'PENDING']]
            )->all();
            $booking->participants()->sync($participants);

            return $booking->load(['room', 'participants']);
        }, 3);
    }
}
