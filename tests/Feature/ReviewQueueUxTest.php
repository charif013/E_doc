<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\DocumentRoute;
use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ReviewQueueUxTest extends TestCase
{
    use RefreshDatabase;

    public function test_head_can_see_consistent_document_and_leave_review_queues(): void
    {
        Role::create(['name' => 'head']);
        Role::create(['name' => 'officer']);

        $head = User::factory()->create(['department' => 'สำนักงานปลัด'])->assignRole('head');
        $officer = User::factory()->create([
            'department' => 'สำนักงานปลัด',
            'position' => 'นักจัดการงานทั่วไป',
        ])->assignRole('officer');

        $document = Document::create([
            'doc_type' => 'internal',
            'title' => 'บันทึกข้อความรอหัวหน้าพิจารณา',
            'status' => 'WAITING_SUPERVISOR',
            'created_by' => $officer->id,
        ]);
        $leave = LeaveRequest::create([
            'user_id' => $officer->id,
            'leave_type' => 'ลาป่วย',
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
            'total_days' => 1,
            'reason' => 'ทดสอบหน้าคิวพิจารณา',
            'status' => 'PENDING',
            'workflow_status' => 'pending_head',
        ]);

        $this->actingAs($head)->get(route('documents.approve_list'))
            ->assertOk()
            ->assertSee('งานเอกสารที่ต้องดำเนินการ')
            ->assertSee('บันทึกข้อความรอหัวหน้าพิจารณา')
            ->assertSee('รอหัวหน้าส่วนราชการ')
            ->assertSee(route('documents.show', $document->uuid), false);

        $this->actingAs($head)->get(route('leaves.approve_list'))
            ->assertOk()
            ->assertSee('ใบลาที่ต้องดำเนินการ')
            ->assertSee($officer->name)
            ->assertSee('รอหัวหน้าส่วนราชการ')
            ->assertSee(route('leaves.show', $leave->id), false);
    }

    public function test_document_changes_to_waiting_approval_when_final_signer_queue_is_reached(): void
    {
        Role::create(['name' => 'officer']);
        $signerAttributes = [
            'pin' => Hash::make('123456'),
            'signature' => 'signatures/test.png',
        ];
        $creator = User::factory()->create($signerAttributes)->assignRole('officer');
        $firstReviewer = User::factory()->create($signerAttributes)->assignRole('officer');
        $finalSigner = User::factory()->create($signerAttributes)->assignRole('officer');

        $document = Document::create([
            'doc_type' => 'internal',
            'title' => 'เอกสารทดสอบคิวผู้ลงนามสุดท้าย',
            'content' => 'รายละเอียดเอกสาร',
            'status' => 'DRAFT',
            'current_step' => 1,
            'created_by' => $creator->id,
        ]);
        foreach ([$creator, $firstReviewer, $finalSigner] as $index => $reviewer) {
            DocumentRoute::create([
                'document_id' => $document->id,
                'user_id' => $reviewer->id,
                'step_order' => $index + 1,
                'status' => 'pending',
            ]);
        }

        $this->actingAs($creator)->post(route('documents.sign', $document->uuid), ['pin' => '123456'])
            ->assertRedirect();
        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
            'status' => 'PROCESSING',
            'current_step' => 2,
        ]);

        $this->actingAs($firstReviewer)->post(route('documents.review', $document->uuid), [
            'pin' => '123456',
            'is_approved' => '1',
            'comment' => 'เห็นชอบ',
        ])->assertRedirect();
        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
            'status' => 'WAITING_APPROVER',
            'current_step' => 3,
        ]);

        $this->actingAs($finalSigner)->get(route('documents.approve_list'))
            ->assertOk()
            ->assertSee('เอกสารทดสอบคิวผู้ลงนามสุดท้าย')
            ->assertSee('รออนุมัติ');
    }

    public function test_incoming_assignment_is_required_inherited_and_delivered_only_after_final_approval(): void
    {
        config()->set('edoc.v2.document_reads', false);
        config()->set('edoc.v2.write_enabled', false);

        foreach (['officer', 'palad', 'executive', 'head'] as $role) {
            Role::create(['name' => $role]);
        }

        $signerAttributes = [
            'pin' => Hash::make('123456'),
            'signature' => 'signatures/test.png',
        ];
        $creator = User::factory()->create(['department' => 'สำนักงานปลัด'])->assignRole('officer');
        $palad = User::factory()->create($signerAttributes)->assignRole('palad');
        $executive = User::factory()->create($signerAttributes)->assignRole('executive');
        $assignedHead = User::factory()->create(['department' => 'กองช่าง'])->assignRole('head');

        $document = Document::create([
            'doc_type' => 'incoming',
            'title' => 'หนังสือรับเข้าทดสอบการมอบหมายตามลำดับ',
            'content' => 'รายละเอียดเอกสาร',
            'status' => 'PROCESSING',
            'current_step' => 2,
            'created_by' => $creator->id,
        ]);
        DocumentRoute::create([
            'document_id' => $document->id,
            'user_id' => $creator->id,
            'step_order' => 1,
            'status' => 'approved',
        ]);
        DocumentRoute::create([
            'document_id' => $document->id,
            'user_id' => $palad->id,
            'step_order' => 2,
            'status' => 'pending',
        ]);
        DocumentRoute::create([
            'document_id' => $document->id,
            'user_id' => $executive->id,
            'step_order' => 3,
            'status' => 'pending',
        ]);

        $this->actingAs($palad)->from(route('documents.show', $document->uuid))
            ->post(route('documents.review', $document->uuid), [
                'pin' => '123456',
                'is_approved' => '1',
            ])->assertSessionHasErrors('assignment_choice');

        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
            'current_step' => 2,
            'assigned_to' => null,
        ]);

        $this->actingAs($palad)->post(route('documents.review', $document->uuid), [
            'pin' => '123456',
            'is_approved' => '1',
            'assignment_choice' => 'กองช่าง',
            'comment' => 'เสนอให้กองช่างดำเนินการ',
        ])->assertRedirect();

        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
            'status' => 'WAITING_APPROVER',
            'current_step' => 3,
            'assigned_to' => 'กองช่าง',
            'assignment_status' => null,
            'assigned_at' => null,
        ]);
        $this->actingAs($assignedHead)->get(route('documents.assigned'))
            ->assertOk()
            ->assertDontSee('หนังสือรับเข้าทดสอบการมอบหมายตามลำดับ');

        $this->actingAs($executive)->get(route('documents.show', $document->uuid))
            ->assertOk()
            ->assertSee('ปลัด อบต. เสนอ:')
            ->assertSee('value="กองช่าง"', false)
            ->assertSee('selected', false);

        $this->actingAs($executive)->post(route('documents.review', $document->uuid), [
            'pin' => '123456',
            'is_approved' => '1',
            'assignment_choice' => 'กองช่าง',
            'comment' => 'อนุมัติ',
        ])->assertRedirect();

        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
            'status' => 'APPROVED',
            'assigned_to' => 'กองช่าง',
            'assignment_status' => 'pending',
        ]);
        $this->assertNotNull($document->fresh()->assigned_at);
        $this->actingAs($assignedHead)->get(route('documents.assigned'))
            ->assertOk()
            ->assertSee('หนังสือรับเข้าทดสอบการมอบหมายตามลำดับ');
    }
}
