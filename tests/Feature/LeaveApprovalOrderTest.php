<?php

namespace Tests\Feature;

use App\Models\LeaveRequest;
use App\Models\User;
use App\Services\LeaveWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LeaveApprovalOrderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['head', 'hr', 'saraban', 'palad', 'deputy-palad', 'executive'] as $role) {
            Role::findOrCreate($role);
        }
    }

    public function test_leave_moves_from_department_head_to_hr_then_palad_and_nayok(): void
    {
        $owner = User::factory()->create(['department' => 'กองช่าง']);
        $head = User::factory()->create(['department' => 'กองช่าง'])->assignRole('head');
        $otherHead = User::factory()->create(['department' => 'กองคลัง'])->assignRole('head');
        $hr = User::factory()->create(['department' => 'สำนักงานปลัด'])->assignRole('hr');
        $saraban = User::factory()->create(['department' => 'สำนักงานปลัด'])->assignRole('saraban');
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
        $this->assertSame('pending_numbering', $leave->workflow_status);
        $this->assertNull($leave->numbered_at);

        $workflow->approve($leave, $saraban, 1);
        $leave->refresh();
        $this->assertSame('pending_palad', $leave->workflow_status);
        $this->assertSame('ลา/กองช่าง/1/'.(now()->month >= 10 ? now()->year + 544 : now()->year + 543), $leave->leave_number);
        $this->assertNotNull($leave->numbered_at);

        $workflow->approve($leave, $palad);
        $this->assertSame('pending_nayok', $leave->fresh()->workflow_status);

        $workflow->approve($leave->fresh(), $nayok);
        $leave->refresh();
        $this->assertSame('approved', $leave->workflow_status);
        $this->assertSame('APPROVED', $leave->status);
    }

    public function test_leave_number_books_are_separate_for_each_department(): void
    {
        $engineeringOwner = User::factory()->create(['department' => 'กองช่าง']);
        $financeOwner = User::factory()->create(['department' => 'กองคลัง']);
        $saraban = User::factory()->create(['department' => 'สำนักงานปลัด'])->assignRole('saraban');
        $workflow = app(LeaveWorkflowService::class);

        $makeLeave = fn (User $owner) => LeaveRequest::create([
            'user_id' => $owner->id,
            'leave_type' => 'ลาพักผ่อน',
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'total_days' => 1,
            'reason' => 'ทดสอบสมุดแยกกอง',
            'contact_info' => '0800000000',
            'status' => 'PENDING',
            'workflow_status' => 'pending_numbering',
            'head_status' => 'approved',
            'inspector_status' => 'approved',
        ]);

        $engineeringLeave = $workflow->approve($makeLeave($engineeringOwner), $saraban, 1);
        $financeLeave = $workflow->approve($makeLeave($financeOwner), $saraban, 1);

        $this->assertStringContainsString('ลา/กองช่าง/1/', $engineeringLeave->leave_number);
        $this->assertStringContainsString('ลา/กองคลัง/1/', $financeLeave->leave_number);
        $this->assertDatabaseHas('document_number_allocations', [
            'leave_request_id' => $engineeringLeave->id, 'scope' => 'กองช่าง', 'running_number' => 1,
        ]);
        $this->assertDatabaseHas('document_number_allocations', [
            'leave_request_id' => $financeLeave->id, 'scope' => 'กองคลัง', 'running_number' => 1,
        ]);
    }
}
