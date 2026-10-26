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
}
