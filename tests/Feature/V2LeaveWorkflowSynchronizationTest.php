<?php

namespace Tests\Feature;

use App\Models\LeaveRequest;
use App\Models\User;
use App\Models\V2\LeaveRequest as V2LeaveRequest;
use App\Services\LeaveWorkflowService;
use App\Services\V2\DataLifecycleService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\Concerns\UsesV2Database;
use Tests\TestCase;

class V2LeaveWorkflowSynchronizationTest extends TestCase
{
    use UsesV2Database;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootV2Database();
        config()->set('database.default', 'mysql_v2');
        DB::setDefaultConnection('mysql_v2');
        foreach (range(1, 6) as $id) { $this->seedV2User($id); }
        foreach (['head', 'hr', 'saraban', 'palad', 'deputy-palad', 'executive'] as $name) {
            Role::findOrCreate($name);
        }
        DB::connection('mysql_v2')->table('leave_types')->insert([
            'id' => 1, 'code' => 'VACATION', 'name' => 'ลาพักผ่อน',
            'requires_attachment' => false, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_workflow_is_the_only_source_for_leave_approval_status_and_evidence(): void
    {
        DB::connection('mysql_v2')->table('users')->whereIn('id', [1, 2])->update(['department' => 'กองช่าง']);
        User::findOrFail(2)->assignRole('head');
        User::findOrFail(3)->assignRole('hr');
        User::findOrFail(4)->assignRole('saraban');
        User::findOrFail(5)->assignRole('palad');
        User::findOrFail(6)->assignRole('executive');

        $leave = LeaveRequest::create([
            'user_id' => 1, 'leave_type_id' => 1,
            'start_date' => '2026-09-11', 'end_date' => '2026-09-11',
            'total_days' => 1, 'reason' => 'ทดสอบ canonical workflow',
        ]);
        $service = app(LeaveWorkflowService::class);
        $this->assertSame('pending_head', $leave->workflow_status);
        $service->approve($leave, User::findOrFail(2));
        $service->approve($leave->fresh(), User::findOrFail(3));
        $service->approve($leave->fresh(), User::findOrFail(4), 1);
        $service->approve($leave->fresh(), User::findOrFail(5));
        $service->approve($leave->fresh(), User::findOrFail(6));

        $leave = $leave->fresh(['workflow', 'numberAllocation']);
        $this->assertSame('APPROVED', $leave->status);
        $this->assertSame('approved', $leave->workflow_status);
        $this->assertSame(5, DB::connection('mysql_v2')->table('workflow_action_evidence')
            ->where('resource_type', 'LEAVE')->where('resource_id', $leave->id)->count());
        $this->assertNotNull($leave->numberAllocation);
        foreach (['leave_type', 'delegate_id', 'workflow_status', 'status', 'head_id', 'head_signature', 'running_number'] as $column) {
            $this->assertFalse(Schema::connection('mysql_v2')->hasColumn('leave_requests', $column));
        }
    }

    public function test_leave_evidence_survives_retention_force_delete(): void
    {
        $leave = LeaveRequest::create([
            'user_id' => 1, 'leave_type_id' => 1,
            'start_date' => '2026-09-11', 'end_date' => '2026-09-11',
            'total_days' => 1, 'reason' => 'ทดสอบ retention',
        ]);
        app(LeaveWorkflowService::class)->cancel($leave, User::findOrFail(1));
        V2LeaveRequest::findOrFail($leave->id)->delete();
        app(DataLifecycleService::class)->forceDeleteLeaveRequest($leave->id);

        $this->assertDatabaseMissing('leave_requests', ['id' => $leave->id], 'mysql_v2');
        $this->assertDatabaseHas('workflow_action_evidence', [
            'resource_type' => 'LEAVE', 'resource_id' => $leave->id,
            'workflow_step_id' => null, 'workflow_instance_id' => null,
        ], 'mysql_v2');
    }
}
