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
                ->whereIn('status', ['PENDING', 'APPROVED'])
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
                'status' => 'APPROVED',
                'approved_by' => $creator->id,
                'approved_at' => now(),
            ]);

            if ($booking->booking_type === 'meeting') {
                $invitees = collect($data['invitees'] ?? [])
                    ->reject(fn ($id) => (int) $id === $creator->id)
                    ->unique()
                    ->mapWithKeys(fn ($id) => [(int) $id => [
                        'participant_role' => 'ATTENDEE',
                        'status' => 'PENDING',
                    ]])
                    ->all();

                $booking->invitees()->sync($invitees);
            }

            return $booking;
        }, 3);
    }

    public function cancel(RoomBooking $booking, User $actor): RoomBooking
    {
        return DB::transaction(function () use ($booking, $actor) {
            $locked = RoomBooking::whereKey($booking->id)->lockForUpdate()->firstOrFail();
            if ($locked->created_by !== $actor->id && ! $actor->hasRole('super-admin')) {
                abort(403, 'คุณไม่มีสิทธิ์ยกเลิกการจองนี้');
            }
            if ($locked->status === 'CANCELED') {
                throw ValidationException::withMessages(['booking' => 'รายการนี้ถูกยกเลิกไปแล้ว']);
            }
            if (! $locked->canBeCanceledNow()) {
                throw ValidationException::withMessages([
                    'booking' => 'ไม่สามารถยกเลิกได้ เนื่องจากถึงเวลาเริ่มใช้งานห้องแล้ว',
                ]);
            }

            $locked->update(['status' => 'CANCELED', 'cancelled_at' => now()]);

            return $locked->fresh();
        }, 3);
    }
}
