<?php

namespace Tests\Feature;

use App\Models\LeaveRequest;
use App\Models\LeaveRequestAttachment;
use App\Models\User;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LeaveAttachmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_submit_leave_with_multiple_private_attachments(): void
    {
        /** @var FilesystemAdapter $documents */
        $documents = Storage::fake('documents');
        /** @var User $user */
        $signature = 'data:image/png;base64,'.base64_encode('applicant-signature');
        $user = User::factory()->create([
            'pin' => Hash::make('123456'),
            'signature' => $signature,
        ]);

        $response = $this->actingAs($user)->post(route('leaves.store'), [
            'leave_type' => 'ลาป่วย',
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'total_days' => 1,
            'reason' => 'เข้ารับการรักษา',
            'contact_info' => '0800000000',
            'attachments' => [
                UploadedFile::fake()->create('ใบรับรองแพทย์.pdf', 100, 'application/pdf'),
                UploadedFile::fake()->image('เอกสารประกอบ.jpg'),
            ],
        ]);

        $leave = LeaveRequest::firstOrFail();
        $response->assertRedirect(route('leaves.draft', $leave));
        $this->assertTrue($leave->is_draft);
        $this->assertDatabaseCount('leave_request_attachments', 2);

        $this->actingAs($user)->get(route('leaves.draft', $leave))
            ->assertOk()
            ->assertSee('เอกสารแนบ')
            ->assertSee('ใบรับรองแพทย์.pdf')
            ->assertSee('เอกสารประกอบ.jpg')
            ->assertSee('attachment-preview-pdf', false)
            ->assertSee('attachment-preview-image', false);

        LeaveRequestAttachment::all()->each(function (LeaveRequestAttachment $attachment) use ($documents) {
            $documents->assertExists($attachment->stored_path);
            $this->assertStringStartsWith('leave_attachments/', $attachment->stored_path);
        });
    }

    public function test_only_users_authorized_to_view_leave_can_open_its_attachment(): void
    {
        Storage::fake('documents');
        /** @var User $owner */
        $owner = User::factory()->create();
        /** @var User $unrelated */
        $unrelated = User::factory()->create();
        $this->actingAs($owner)->post(route('leaves.store'), [
            'leave_type' => 'ลาป่วย',
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'total_days' => 1,
            'reason' => 'เข้ารับการรักษา',
            'contact_info' => '0800000000',
            'attachments' => [
                UploadedFile::fake()->create('medical-certificate.pdf', 100, 'application/pdf'),
            ],
        ])->assertRedirect();

        $attachment = LeaveRequestAttachment::firstOrFail();
        $url = route('leaves.attachments.download', [$attachment->leave_request_id, $attachment->id]);

        $this->actingAs($owner)->get($url)
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->actingAs($unrelated)->get($url)->assertForbidden();
    }

    public function test_executable_file_cannot_be_attached_to_leave(): void
    {
        Storage::fake('documents');
        /** @var User $user */
        $user = User::factory()->create();

        $this->actingAs($user)->from(route('leaves.create'))->post(route('leaves.store'), [
            'leave_type' => 'ลาป่วย',
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'total_days' => 1,
            'reason' => 'เข้ารับการรักษา',
            'contact_info' => '0800000000',
            'attachments' => [
                UploadedFile::fake()->create('malware.exe', 10, 'application/x-msdownload'),
            ],
        ])->assertRedirect(route('leaves.create'))
            ->assertSessionHasErrors('attachments.0');

        $this->assertDatabaseCount('leave_requests', 0);
        $this->assertDatabaseCount('leave_request_attachments', 0);
    }

    public function test_contact_information_is_optional_when_submitting_leave(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('leaves.store'), [
            'leave_type' => 'ลากิจส่วนตัว',
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'total_days' => 1,
            'reason' => 'ติดต่อราชการ',
        ]);
        $leave = LeaveRequest::firstOrFail();
        $response->assertRedirect(route('leaves.draft', $leave))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('leave_requests', [
            'user_id' => $user->id,
            'contact_info' => null,
        ]);
    }

    public function test_user_reviews_official_draft_before_submitting_leave_to_workflow(): void
    {
        Role::findOrCreate('head');
        Role::findOrCreate('officer');
        $signature = 'data:image/png;base64,'.base64_encode('applicant-signature');
        $user = User::factory()->create([
            'pin' => Hash::make('123456'),
            'signature' => $signature,
        ])->assignRole('officer');

        $this->actingAs($user)->post(route('leaves.store'), [
            'leave_type' => 'ลาพักผ่อน',
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'total_days' => 1,
            'reason' => 'ทดสอบฉบับร่างก่อนส่ง',
        ]);

        $leave = LeaveRequest::firstOrFail();
        $this->assertTrue($leave->is_draft);
        $this->actingAs($user)->get(route('leaves.draft', $leave))
            ->assertOk()
            ->assertSee('ฉบับร่าง')
            ->assertSee('ยืนยันและส่งใบลา');

        $this->actingAs($user)->from(route('leaves.draft', $leave))
            ->post(route('leaves.draft.submit', $leave), ['pin' => '999999'])
            ->assertRedirect(route('leaves.draft', $leave))
            ->assertSessionHas('error', 'รหัส PIN ไม่ถูกต้อง กรุณาลองอีกครั้ง');
        $leave->refresh();
        $this->assertTrue($leave->is_draft);
        $this->assertNull($leave->applicant_signature);

        $this->actingAs($user)->post(route('leaves.draft.submit', $leave), ['pin' => '123456'])
            ->assertRedirect(route('leaves.approve_list'))
            ->assertSessionHas('success');

        $leave->refresh();
        $this->assertFalse($leave->is_draft);
        $this->assertSame('pending_head', $leave->workflow_status);
        $this->assertSame($signature, $leave->applicant_signature);
        $this->assertNotNull($leave->submitted_at);
        $this->actingAs($user)->get(route('leaves.show', $leave))
            ->assertOk()
            ->assertSee('ลายเซ็นผู้ยื่นใบลา')
            ->assertSee($signature, false);
    }

    public function test_canceling_a_draft_returns_user_to_the_original_leave_form(): void
    {
        $user = User::factory()->create();
        $leave = LeaveRequest::create([
            'user_id' => $user->id,
            'leave_type' => 'ลาป่วย',
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'total_days' => 1,
            'reason' => 'ยกเลิกฉบับร่าง',
            'status' => 'DRAFT',
            'workflow_status' => 'draft',
            'is_draft' => true,
        ]);

        $this->actingAs($user)->put(route('leaves.cancel', $leave))
            ->assertRedirect(route('leaves.create'))
            ->assertSessionHas('success', 'ยกเลิกฉบับร่างเรียบร้อยแล้ว');

        $leave->refresh();
        $this->assertFalse($leave->is_draft);
        $this->assertSame('CANCELED', $leave->status);
    }
}
