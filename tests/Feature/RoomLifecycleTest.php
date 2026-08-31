<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Room;
use App\Models\RoomBooking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoomLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_soft_deletes_room_but_preserves_booking_history_and_audit(): void
    {
        Role::create(['name' => 'super-admin']);
        $admin = User::factory()->create();
        $admin->assignRole('super-admin');
        $room = Room::create(['name' => 'ห้องเดิม', 'status' => 'active']);
        $booking = RoomBooking::create([
            'title' => 'ประชุม', 'booking_type' => 'meeting', 'room_id' => $room->id,
            'created_by' => $admin->id, 'start_time' => now(), 'end_time' => now()->addHour(),
        ]);

        $this->actingAs($admin)->delete(route('admin.rooms.destroy', $room))->assertSessionHas('success');

        $this->assertSoftDeleted('rooms', ['id' => $room->id]);
        $this->assertSame('ห้องเดิม', $booking->fresh()->room->name);
        $this->assertTrue(AuditLog::where('auditable_type', Room::class)->where('event', 'deleted')->exists());
    }

    public function test_invitee_pair_is_unique(): void
    {
        $creator = User::factory()->create();
        $invitee = User::factory()->create();
        $room = Room::create(['name' => 'ห้องหนึ่ง', 'status' => 'active']);
        $booking = RoomBooking::create([
            'title' => 'ประชุม', 'booking_type' => 'meeting', 'room_id' => $room->id,
            'created_by' => $creator->id, 'start_time' => now(), 'end_time' => now()->addHour(),
        ]);
        $booking->invitees()->attach($invitee->id);

        $this->expectException(\Illuminate\Database\QueryException::class);
        $booking->invitees()->attach($invitee->id);
    }
}
