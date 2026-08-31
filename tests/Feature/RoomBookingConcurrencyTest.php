<?php

namespace Tests\Feature;

use App\Models\Room;
use App\Models\RoomBooking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RoomBookingConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_overlapping_room_booking_is_rejected(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $room = Room::create(['name' => 'ห้องทดสอบ', 'status' => 'active', 'capacity' => 10]);
        RoomBooking::create([
            'title' => 'รายการแรก', 'booking_type' => 'meeting', 'room_id' => $room->id,
            'created_by' => $user->id, 'start_time' => '2026-09-01 09:00:00', 'end_time' => '2026-09-01 10:00:00',
        ]);

        $response = $this->actingAs($user)->from(route('bookings.create'))->post(route('bookings.store'), [
            'title' => 'รายการชน', 'booking_type' => 'meeting', 'room_id' => $room->id,
            'start_time' => '2026-09-01 09:30:00', 'end_time' => '2026-09-01 10:30:00',
        ]);

        $response->assertRedirect(route('bookings.create'))->assertSessionHasErrors('start_time');
        $this->assertDatabaseCount('room_bookings', 1);
        Queue::assertNothingPushed();
    }
}
