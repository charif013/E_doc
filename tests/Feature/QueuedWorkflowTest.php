<?php

namespace Tests\Feature;

use App\Jobs\ArchiveExternalDocument;
use App\Jobs\SendLineNotification;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class QueuedWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_meeting_notification_is_dispatched_to_queue(): void
    {
        Queue::fake();
        $creator = User::factory()->create();
        $invitee = User::factory()->create(['line_id' => 'U-line-test']);
        $room = Room::create(['name' => 'ห้องคิว', 'status' => 'active']);

        $this->actingAs($creator)->post(route('bookings.store'), [
            'title' => 'ประชุมทดสอบ', 'booking_type' => 'meeting', 'room_id' => $room->id,
            'start_time' => '2026-09-02 09:00:00', 'end_time' => '2026-09-02 10:00:00',
            'invitees' => [$invitee->id],
        ])->assertRedirect(route('bookings.index'));

        Queue::assertPushed(SendLineNotification::class, fn ($job) => $job->userIds === [$invitee->id]);
    }

    public function test_external_document_archive_is_dispatched_to_queue(): void
    {
        Queue::fake();
        Role::create(['name' => 'officer']);
        $creator = User::factory()->create()->assignRole('officer');
        $reviewer = User::factory()->create();

        $this->actingAs($creator)->post(route('documents.store_incoming'), [
            'receive_number' => 'รับ/1', 'running_number' => 1, 'receive_date' => '2026-08-27',
            'doc_number' => 'ทดสอบ/1', 'doc_date' => '2026-08-27', 'title' => 'เอกสาร QR',
            'doc_from' => 'หน่วยงานทดสอบ', 'doc_type_category' => 'หนังสือทั่วไป',
            'external_url' => 'https://example.com/document.pdf', 'routing_users' => [$reviewer->id],
        ])->assertRedirect();

        Queue::assertPushed(ArchiveExternalDocument::class);
    }
}
