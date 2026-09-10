<?php

namespace Tests\Feature;

use App\Models\Room;
use App\Models\RoomBooking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RoomBookingConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_booking_form_exposes_guided_inputs_and_live_summary(): void
    {
        $user = User::factory()->create();
        Room::create(['name' => 'ห้องใช้งานง่าย', 'status' => 'active', 'capacity' => 12]);

        $this->actingAs($user)->get(route('bookings.create'))
            ->assertOk()
            ->assertSee('รายละเอียดการใช้งาน')
            ->assertSee('กำหนดระยะเวลาเร็ว')
            ->assertSee('ค้นหาชื่อหรือตำแหน่ง')
            ->assertSee('สรุปการจอง')
            ->assertSee('ยืนยันอัตโนมัติ');
    }

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

    public function test_booking_status_is_confirmed_and_cancellation_releases_the_room(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $room = Room::create(['name' => 'ห้องสถานะ', 'status' => 'active', 'capacity' => 10]);
        $bookingData = [
            'title' => 'รายการตรวจสถานะ', 'booking_type' => 'meeting', 'room_id' => $room->id,
            'start_time' => now()->addDay()->setTime(9, 0)->format('Y-m-d H:i:s'),
            'end_time' => now()->addDay()->setTime(10, 0)->format('Y-m-d H:i:s'),
        ];

        $this->actingAs($user)->post(route('bookings.store'), $bookingData)
            ->assertRedirect(route('bookings.index'));

        $booking = RoomBooking::where('title', 'รายการตรวจสถานะ')->firstOrFail();
        $this->assertSame('APPROVED', $booking->status);
        $this->assertNotNull($booking->approved_at);
        $this->actingAs($user)->get(route('bookings.index'))
            ->assertOk()
            ->assertSee('ยืนยันแล้ว');

        $this->actingAs($user)->delete(route('bookings.destroy', $booking->id))
            ->assertSessionHas('success');
        $this->assertDatabaseHas('room_bookings', [
            'id' => $booking->id,
            'status' => 'CANCELED',
        ]);
        $this->actingAs($user)->get(route('bookings.index'))
            ->assertOk()
            ->assertSee('ยกเลิกแล้ว');

        $this->actingAs($user)->post(route('bookings.store'), array_merge($bookingData, ['title' => 'รายการใหม่หลังยกเลิก']))
            ->assertRedirect(route('bookings.index'))
            ->assertSessionDoesntHaveErrors();
        $this->assertDatabaseHas('room_bookings', [
            'title' => 'รายการใหม่หลังยกเลิก',
            'status' => 'APPROVED',
        ]);
    }

    public function test_booking_changes_to_in_progress_and_completed_by_time_and_cannot_be_canceled_after_start(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $room = Room::create(['name' => 'ห้องตามเวลา', 'status' => 'active', 'capacity' => 10]);
        $booking = RoomBooking::create([
            'title' => 'ประชุมติดตามสถานะเวลา',
            'booking_type' => 'meeting',
            'room_id' => $room->id,
            'created_by' => $user->id,
            'start_time' => '2026-09-09 12:00:00',
            'end_time' => '2026-09-09 13:00:00',
            'status' => 'APPROVED',
        ]);

        $this->travelTo('2026-09-09 12:30:00');
        $this->actingAs($user)->get(route('bookings.index'))
            ->assertOk()
            ->assertSee('กำลังดำเนินการ')
            ->assertDontSee('action="'.route('bookings.destroy', $booking).'"', false);
        $this->actingAs($user)->delete(route('bookings.destroy', $booking))
            ->assertSessionHasErrors('booking');
        $this->assertSame('APPROVED', $booking->fresh()->status);

        $this->travelTo('2026-09-09 13:01:00');
        $this->actingAs($user)->get(route('bookings.index'))
            ->assertOk()
            ->assertSee('เสร็จสิ้น');
        $this->travelBack();
    }

    public function test_invitee_can_respond_and_creator_can_see_the_response(): void
    {
        Queue::fake();
        $creator = User::factory()->create(['name' => 'ผู้เชิญทดสอบ']);
        $invitee = User::factory()->create(['name' => 'ผู้เข้าร่วมทดสอบ']);
        $room = Room::create(['name' => 'ห้องตอบรับ', 'status' => 'active', 'capacity' => 10]);
        $booking = RoomBooking::create([
            'title' => 'ประชุมตรวจระบบตอบรับ',
            'booking_type' => 'meeting',
            'room_id' => $room->id,
            'created_by' => $creator->id,
            'start_time' => '2026-09-10 09:00:00',
            'end_time' => '2026-09-10 10:00:00',
            'status' => 'APPROVED',
        ]);
        $booking->invitees()->attach($invitee->id, [
            'participant_role' => 'ATTENDEE',
            'status' => 'PENDING',
        ]);

        $this->actingAs($invitee)->get(route('bookings.show', $booking))
            ->assertOk()
            ->assertSee('ตอบรับคำเชิญ')
            ->assertSee('รอการตอบรับ')
            ->assertDontSee('ผลตอบรับผู้เข้าร่วม');

        $this->actingAs($invitee)->post(route('bookings.respond', $booking), [
            'response' => 'accepted',
        ])->assertRedirect(route('bookings.show', $booking))
            ->assertSessionHas('success');

        $participant = DB::table('room_booking_user')
            ->where('room_booking_id', $booking->id)
            ->where('user_id', $invitee->id)
            ->first();
        $this->assertSame('ACCEPTED', $participant->status);
        $this->assertNotNull($participant->responded_at);

        $this->actingAs($invitee)->get(route('bookings.show', $booking))
            ->assertOk()
            ->assertSee('คุณยืนยันว่าจะเข้าร่วมการประชุมนี้แล้ว')
            ->assertSee('ตอบรับแล้ว')
            ->assertSee('เปลี่ยนเป็นไม่สะดวก')
            ->assertSee('data-testid="response-accepted"', false)
            ->assertSee('aria-pressed="true"', false)
            ->assertSee('disabled', false);

        $this->actingAs($creator)->get(route('bookings.show', $booking))
            ->assertOk()
            ->assertSee('ผลตอบรับผู้เข้าร่วม')
            ->assertSee('ผู้เข้าร่วมทดสอบ')
            ->assertSee('ตอบรับแล้ว');
    }

    public function test_outsider_cannot_view_or_respond_to_a_meeting_invitation(): void
    {
        Queue::fake();
        $creator = User::factory()->create();
        $outsider = User::factory()->create();
        $room = Room::create(['name' => 'ห้องสิทธิ์', 'status' => 'active', 'capacity' => 10]);
        $booking = RoomBooking::create([
            'title' => 'ประชุมเฉพาะผู้ได้รับเชิญ',
            'booking_type' => 'meeting',
            'room_id' => $room->id,
            'created_by' => $creator->id,
            'start_time' => '2026-09-11 09:00:00',
            'end_time' => '2026-09-11 10:00:00',
            'status' => 'APPROVED',
        ]);

        $this->actingAs($outsider)->get(route('bookings.show', $booking))->assertForbidden();
        $this->actingAs($outsider)->post(route('bookings.respond', $booking), [
            'response' => 'accepted',
        ])->assertForbidden();
    }

    public function test_room_booking_cannot_be_deleted_instead_of_canceled(): void
    {
        $user = User::factory()->create();
        $room = Room::create(['name' => 'ห้องเก็บประวัติ', 'status' => 'active', 'capacity' => 10]);
        $booking = RoomBooking::create([
            'title' => 'ประชุมที่ต้องเก็บประวัติ', 'booking_type' => 'meeting',
            'room_id' => $room->id, 'created_by' => $user->id,
            'start_time' => '2026-09-12 09:00:00', 'end_time' => '2026-09-12 10:00:00',
            'status' => 'APPROVED',
        ]);

        $this->expectException(\LogicException::class);
        $booking->delete();
    }
}
