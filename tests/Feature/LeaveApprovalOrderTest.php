<?php

namespace Tests\Feature;

use App\Models\LeaveRequest;
use App\Models\User;
use App\Services\LeaveWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LeaveApprovalOrderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['head', 'hr', 'saraban', 'palad', 'executive'] as $role) {
            Role::findOrCreate($role);
        }
    }

    public function test_leave_moves_from_department_head_to_hr_then_palad_and_nayok(): void
    {
        $owner = User::factory()->create(['department' => 'กองช่าง']);
        $head = User::factory()->create(['department' => 'กองช่าง'])->assignRole('head');
        $otherHead = User::factory()->create(['department' => 'กองคลัง'])->assignRole('head');
        $hr = User::factory()->create(['department' => 'สำนักงานปลัด'])->assignRole('hr');
        $palad = User::factory()->create()->assignRole('palad');
        $nayok = User::factory()->create()->assignRole('executive');

        $leave = LeaveRequest::create([
            'user_id' => $owner->id,
            'leave_type' => 'ลาพักผ่อน',
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'total_days' => 1,
            'reason' => 'ทดสอบลำดับอนุมัติ',
            'contact_info' => '0800000000',
            'status' => 'PENDING',
            'workflow_status' => 'pending_head',
        ]);

        $this->assertTrue($head->can('review', $leave));
        $this->assertFalse($otherHead->can('review', $leave));

        $workflow = app(LeaveWorkflowService::class);
        $workflow->approve($leave, $head);
        $this->assertSame('pending_inspector', $leave->fresh()->workflow_status);

        $workflow->approve($leave->fresh(), $hr);
        $leave->refresh();
        $this->assertSame('pending_palad', $leave->workflow_status);
        $this->assertNull($leave->numbered_at);
        $this->assertNull($leave->leave_number);

        $workflow->approve($leave, $palad);
        $this->assertSame('pending_nayok', $leave->fresh()->workflow_status);

        $workflow->approve($leave->fresh(), $nayok);
        $leave->refresh();
        $this->assertSame('approved', $leave->workflow_status);
        $this->assertSame('APPROVED', $leave->status);
    }

    public function test_leave_approval_does_not_allocate_a_document_number(): void
    {
        $owner = User::factory()->create(['department' => 'กองช่าง']);
        $palad = User::factory()->create()->assignRole('palad');
        $workflow = app(LeaveWorkflowService::class);

        $leave = LeaveRequest::create([
            'user_id' => $owner->id,
            'leave_type' => 'ลาพักผ่อน',
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'total_days' => 1,
            'reason' => 'ทดสอบไม่ลงเลขใบลา',
            'contact_info' => '0800000000',
            'status' => 'PENDING',
            'workflow_status' => 'pending_palad',
            'head_status' => 'approved',
            'inspector_status' => 'approved',
        ]);

        $workflow->approve($leave, $palad);
        $leave->refresh();

        $this->assertSame('pending_nayok', $leave->workflow_status);
        $this->assertNull($leave->leave_number);
        $this->assertDatabaseMissing('document_number_allocations', ['leave_request_id' => $leave->id]);
    }

    public function test_approval_returns_to_review_queue_and_displays_reviewer_signature(): void
    {
        $owner = User::factory()->create(['department' => 'กองช่าง']);
        $signature = 'data:image/png;base64,'.base64_encode('signature-image');
        $head = User::factory()->create([
            'department' => 'กองช่าง',
            'position' => 'ผู้อำนวยการกองช่าง',
            'pin' => Hash::make('123456'),
            'signature' => $signature,
        ])->assignRole('head');
        $leave = LeaveRequest::create([
            'user_id' => $owner->id,
            'leave_type' => 'ลาป่วย',
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'total_days' => 1,
            'reason' => 'ทดสอบลายเซ็นบนใบลา',
            'status' => 'PENDING',
            'workflow_status' => 'pending_head',
        ]);

        $this->actingAs($head)->post(route('leaves.reviewAction', $leave), [
            'is_approved' => 1,
            'pin' => '123456',
            'reject_reason' => 'เห็นควรอนุญาต เนื่องจากงานไม่ติดขัด',
        ])->assertRedirect(route('leaves.approve_list'));

        $leave->refresh();
        $this->assertSame('pending_inspector', $leave->workflow_status);
        $this->assertNotNull($leave->head_signature);
        $this->assertSame('เห็นควรอนุญาต เนื่องจากงานไม่ติดขัด', $leave->head_comment);
        $this->actingAs($head)->get(route('leaves.show', $leave))
            ->assertOk()
            ->assertSee('ความเห็นหัวหน้าส่วนราชการ')
            ->assertSee('เห็นควรอนุญาต เนื่องจากงานไม่ติดขัด')
            ->assertSee('ผู้อำนวยการกองช่าง')
            ->assertSee($leave->head_signature, false);
    }

    public function test_leave_for_department_head_applicant_routes_directly_to_hr_then_palad_and_nayok(): void
    {
        $head = User::factory()->create(['department' => 'กองช่าง'])->assignRole('head');
        $hr = User::factory()->create(['department' => 'สำนักงานปลัด'])->assignRole('hr');
        $palad = User::factory()->create()->assignRole('palad');
        $nayok = User::factory()->create()->assignRole('executive');

        $leave = LeaveRequest::create([
            'user_id' => $head->id,
            'leave_type' => 'ลาพักผ่อน',
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'total_days' => 1,
            'reason' => 'หัวหน้ากองลาพักผ่อน',
            'status' => 'PENDING',
            'is_draft' => false,
        ]);

        $leave->refresh();
        $this->assertSame('pending_inspector', $leave->workflow_status);
        $this->assertFalse($head->can('review', $leave));
        $this->assertTrue($hr->can('review', $leave));

        $workflow = app(LeaveWorkflowService::class);
        $workflow->approve($leave, $hr);
        $leave->refresh();
        $this->assertSame('pending_palad', $leave->workflow_status);

        $workflow->approve($leave, $palad);
        $leave->refresh();
        $this->assertSame('pending_nayok', $leave->workflow_status);

        $workflow->approve($leave, $nayok);
        $leave->refresh();
        $this->assertSame('approved', $leave->workflow_status);
        $this->assertSame('APPROVED', $leave->status);

        $this->actingAs($hr)->get(route('leaves.show', $leave))
            ->assertOk()
            ->assertDontSee('ความเห็นหัวหน้าส่วนราชการ')
            ->assertSee('ความเห็นผู้บังคับบัญชา (ปลัด อบต.)');
    }

    public function test_leave_for_palad_applicant_routes_directly_to_hr_then_nayok(): void
    {
        $palad = User::factory()->create(['department' => 'สำนักงานปลัด'])->assignRole('palad');
        $hr = User::factory()->create(['department' => 'สำนักงานปลัด'])->assignRole('hr');
        $nayok = User::factory()->create()->assignRole('executive');

        $leave = LeaveRequest::create([
            'user_id' => $palad->id,
            'leave_type' => 'ลาพักผ่อน',
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'total_days' => 1,
            'reason' => 'ปลัดลาพักผ่อน',
            'status' => 'PENDING',
            'is_draft' => false,
        ]);

        $leave->refresh();
        $this->assertSame('pending_inspector', $leave->workflow_status);
        $this->assertFalse($palad->can('review', $leave));
        $this->assertTrue($hr->can('review', $leave));

        $workflow = app(LeaveWorkflowService::class);
        $workflow->approve($leave, $hr);
        $leave->refresh();
        $this->assertSame('pending_nayok', $leave->workflow_status);

        $workflow->approve($leave, $nayok);
        $leave->refresh();
        $this->assertSame('approved', $leave->workflow_status);
        $this->assertSame('APPROVED', $leave->status);

        $this->actingAs($hr)->get(route('leaves.show', $leave))
            ->assertOk()
            ->assertDontSee('ความเห็นหัวหน้าส่วนราชการ')
            ->assertDontSee('ความเห็นผู้บังคับบัญชา (ปลัด อบต.)');
    }

    public function test_executive_cannot_submit_leave_and_does_not_see_creation_menu(): void
    {
        $nayok = User::factory()->create()->assignRole('executive');

        $this->actingAs($nayok)->get(route('home'))
            ->assertOk()
            ->assertDontSee('ยื่นใบลาพักผ่อน');

        $this->actingAs($nayok)->get(route('leaves.create'))
            ->assertForbidden();

        $this->actingAs($nayok)->post(route('leaves.store'), [
            'leave_type' => 'ลาพักผ่อน',
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'total_days' => 1,
            'reason' => 'ทดสอบยื่นใบลา',
        ])->assertForbidden();
    }

    public function test_head_and_palad_leave_with_delegate_routes_to_hr_after_delegate_accepts(): void
    {
        $head = User::factory()->create(['department' => 'กองช่าง'])->assignRole('head');
        $delegate = User::factory()->create(['department' => 'กองช่าง']);
        $hr = User::factory()->create(['department' => 'สำนักงานปลัด'])->assignRole('hr');

        $leave = LeaveRequest::create([
            'user_id' => $head->id,
            'delegate_id' => $delegate->id,
            'leave_type' => 'ลาพักผ่อน',
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'total_days' => 1,
            'reason' => 'หัวหน้ากองลามีผู้รับมอบงาน',
            'status' => 'PENDING',
            'workflow_status' => 'pending_delegate',
            'is_draft' => false,
        ]);

        $this->assertSame('pending_delegate', $leave->workflow_status);

        $workflow = app(LeaveWorkflowService::class);
        $workflow->respondToDelegation($leave, $delegate, 'accept');

        $leave->refresh();
        $this->assertSame('pending_inspector', $leave->workflow_status);
        $this->assertTrue($hr->can('review', $leave));
    }
}
