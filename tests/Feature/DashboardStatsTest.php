<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DashboardStatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_executive_report_displays_summary_and_urgent_documents(): void
    {
        Role::create(['name' => 'executive']);
        $executive = User::factory()->create()->assignRole('executive');

        Document::create([
            'doc_type' => 'incoming',
            'title' => 'หนังสือดำเนินการแล้ว',
            'status' => 'APPROVED',
            'doc_speed' => 'ปกติ',
            'created_by' => $executive->id,
        ]);
        $urgentDocument = Document::create([
            'doc_type' => 'incoming',
            'title' => 'หนังสือด่วนที่สุดรอพิจารณา',
            'status' => 'WAITING_NAYOK',
            'doc_speed' => 'ด่วนที่สุด',
            'assigned_to' => 'สำนักงานปลัด',
            'created_by' => $executive->id,
        ]);

        $this->actingAs($executive)->get(route('dashboard.executive'))
            ->assertOk()
            ->assertSee('รายงานภาพรวมงานบริหาร')
            ->assertSee('สรุปประจำเดือน')
            ->assertSee('เอกสารแยกตามส่วนราชการ')
            ->assertSee('รายการเร่งด่วน')
            ->assertSee('หนังสือด่วนที่สุดรอพิจารณา')
            ->assertSee(route('documents.show', $urgentDocument->uuid), false)
            ->assertViewHas('stats', fn ($stats) => $stats['total'] === 2
                && $stats['waiting'] === 1
                && $stats['approved'] === 1
                && $stats['urgent'] === 1);
    }

    public function test_owner_draft_is_counted_as_waiting_for_action(): void
    {
        Role::create(['name' => 'officer']);
        $owner = User::factory()->create()->assignRole('officer');
        Document::create([
            'doc_type' => 'internal',
            'title' => 'รอลงนามนำส่ง',
            'status' => 'DRAFT',
            'created_by' => $owner->id,
        ]);

        $this->actingAs($owner)->get(route('home'))
            ->assertOk()
            ->assertViewHas('stats', fn ($stats) => $stats['total'] === 1 && $stats['waiting'] === 1);
    }

    public function test_someone_elses_draft_is_not_counted_as_users_waiting_work(): void
    {
        Role::create(['name' => 'officer']);
        $owner = User::factory()->create()->assignRole('officer');
        $other = User::factory()->create()->assignRole('officer');
        Document::create([
            'doc_type' => 'internal',
            'title' => 'ร่างของผู้อื่น',
            'status' => 'DRAFT',
            'created_by' => $owner->id,
        ]);

        $this->actingAs($other)->get(route('home'))
            ->assertOk()
            ->assertViewHas('stats', fn ($stats) => $stats['total'] === 0 && $stats['waiting'] === 0);
    }

    public function test_saraban_sees_documents_that_have_reached_their_queue_on_dashboard(): void
    {
        Role::create(['name' => 'officer']);
        Role::create(['name' => 'saraban']);
        $owner = User::factory()->create()->assignRole('officer');
        $saraban = User::factory()->create()->assignRole('saraban');
        $document = Document::create([
            'doc_type' => 'incoming',
            'title' => 'หนังสือรอธุรการตรวจสอบ',
            'status' => 'WAITING_ADMIN',
            'created_by' => $owner->id,
        ]);

        $this->actingAs($saraban)->get(route('home'))
            ->assertOk()
            ->assertSee('งานที่ต้องดำเนินการ')
            ->assertSee('หนังสือรอธุรการตรวจสอบ')
            ->assertSee(route('documents.show', $document->uuid), false)
            ->assertViewHas('documentTasks', fn ($tasks) => $tasks->contains('id', $document->id));
    }

    public function test_leave_reviewer_sees_only_leaves_that_have_reached_their_queue(): void
    {
        Role::create(['name' => 'officer']);
        Role::create(['name' => 'head']);
        $head = User::factory()->create(['department' => 'สำนักงานปลัด'])->assignRole('head');
        $sameDepartmentOwner = User::factory()->create(['department' => 'สำนักงานปลัด'])->assignRole('officer');
        $otherDepartmentOwner = User::factory()->create(['department' => 'กองช่าง'])->assignRole('officer');

        $visibleLeave = LeaveRequest::create([
            'user_id' => $sameDepartmentOwner->id,
            'leave_type' => 'ลาป่วย',
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
            'total_days' => 1,
            'reason' => 'ทดสอบคิวใบลา',
            'status' => 'PENDING',
            'workflow_status' => 'pending_head',
        ]);
        $hiddenLeave = LeaveRequest::create([
            'user_id' => $otherDepartmentOwner->id,
            'leave_type' => 'ลากิจส่วนตัว',
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
            'total_days' => 1,
            'reason' => 'คนละส่วนราชการ',
            'status' => 'PENDING',
            'workflow_status' => 'pending_head',
        ]);

        $this->actingAs($head)->get(route('home'))
            ->assertOk()
            ->assertSee('ใบลารอพิจารณา')
            ->assertSee($sameDepartmentOwner->name)
            ->assertDontSee($otherDepartmentOwner->name)
            ->assertViewHas('leaveTasks', fn ($tasks) => $tasks->contains('id', $visibleLeave->id)
                && ! $tasks->contains('id', $hiddenLeave->id))
            ->assertViewHas('stats', fn ($stats) => $stats['waiting'] === 1);
    }
}
