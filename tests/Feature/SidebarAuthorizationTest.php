<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SidebarAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_without_document_permissions_does_not_see_document_or_review_menus(): void
    {
        $user = $this->userWithRole('worker');

        $this->actingAs($user)->get(route('home'))
            ->assertOk()
            ->assertDontSee('งานสารบรรณ (e-Saraban)')
            ->assertDontSee('แฟ้มพิจารณาอนุมัติ')
            ->assertDontSee('งานที่ได้รับมอบหมาย')
            ->assertDontSee('รายงานภาพรวม');
    }

    public function test_general_officer_sees_only_the_document_creation_menu_they_can_use(): void
    {
        $user = $this->userWithRole('officer', ['create_document']);

        $this->actingAs($user)->get(route('home'))
            ->assertOk()
            ->assertSee('สร้างบันทึกข้อความ')
            ->assertDontSee('อัปโหลดหนังสือส่งออก')
            ->assertDontSee('อัปโหลดหนังสือรับเข้า')
            ->assertDontSee('สมุดคุมเลขสารบรรณ');
    }

    public function test_saraban_sees_register_work_but_hr_does_not_see_executive_dashboard(): void
    {
        $saraban = $this->userWithRole('saraban', ['receive_register', 'send_register']);
        $hr = $this->userWithRole('hr');

        $this->actingAs($saraban)->get(route('home'))
            ->assertOk()
            ->assertSee('อัปโหลดหนังสือส่งออก')
            ->assertSee('อัปโหลดหนังสือรับเข้า')
            ->assertSee('สมุดคุมเลขสารบรรณ');

        $this->actingAs($hr)->get(route('home'))
            ->assertOk()
            ->assertSee('พิจารณาใบลา')
            ->assertDontSee('รายงานภาพรวม');
    }

    public function test_approver_roles_keep_the_internal_memo_creation_menu(): void
    {
        foreach (['executive', 'palad', 'deputy-palad', 'head'] as $roleName) {
            $user = $this->userWithRole($roleName);

            $this->actingAs($user)->get(route('home'))
                ->assertOk()
                ->assertSee('สร้างบันทึกข้อความ');
        }
    }

    public function test_palad_does_not_see_the_saraban_number_registry_menu(): void
    {
        $palad = $this->userWithRole('palad');

        $this->actingAs($palad)->get(route('home'))
            ->assertOk()
            ->assertDontSee('สมุดคุมเลขสารบรรณ');
    }

    public function test_assigned_work_badge_counts_only_delivered_documents_and_pending_leave_delegations(): void
    {
        $head = $this->userWithRole('head');
        $head->update(['department' => 'กองช่าง']);
        $owner = $this->userWithRole('officer');

        Document::create([
            'doc_type' => 'incoming',
            'title' => 'งานที่อนุมัติและส่งถึงกองช่างแล้ว',
            'status' => 'APPROVED',
            'assigned_to' => 'กองช่าง',
            'assignment_status' => 'pending',
            'assigned_at' => now(),
            'created_by' => $owner->id,
        ]);
        Document::create([
            'doc_type' => 'incoming',
            'title' => 'ข้อเสนอที่ยังไม่อนุมัติ',
            'status' => 'WAITING_APPROVER',
            'assigned_to' => 'กองช่าง',
            'assignment_status' => null,
            'created_by' => $owner->id,
        ]);
        LeaveRequest::create([
            'user_id' => $owner->id,
            'delegate_id' => $head->id,
            'leave_type' => 'ลาป่วย',
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
            'total_days' => 1,
            'reason' => 'ทดสอบ Badge',
            'status' => 'PENDING',
            'workflow_status' => 'pending_delegate',
            'delegate_status' => 'pending',
        ]);

        $this->actingAs($head)->get(route('home'))
            ->assertOk()
            ->assertSee('aria-label="งานค้าง 2 รายการ"', false)
            ->assertDontSee('aria-label="งานค้าง 3 รายการ"', false);
    }

    public function test_saraban_number_registry_badge_counts_documents_waiting_for_numbering(): void
    {
        $saraban = $this->userWithRole('saraban');
        $owner = $this->userWithRole('officer');

        Document::create([
            'doc_type' => 'internal',
            'title' => 'บันทึกข้อความรอลงเลข',
            'status' => 'WAITING_NUMBERING',
            'created_by' => $owner->id,
        ]);
        Document::create([
            'doc_type' => 'internal',
            'title' => 'บันทึกข้อความที่ลงเลขแล้ว',
            'status' => 'APPROVED',
            'running_number' => 10,
            'created_by' => $owner->id,
        ]);

        $this->actingAs($saraban)->get(route('home'))
            ->assertOk()
            ->assertSee('สมุดคุมเลขสารบรรณ')
            ->assertSee('aria-label="เอกสารรอลงเลข 1 รายการ"', false);
    }

    public function test_leave_review_badge_counts_only_pending_leaves_for_the_head_department(): void
    {
        $head = $this->userWithRole('head');
        $head->update(['department' => 'กองช่าง']);
        $sameDepartmentOwner = $this->userWithRole('officer');
        $sameDepartmentOwner->update(['department' => 'กองช่าง']);
        $otherDepartmentOwner = $this->userWithRole('officer');
        $otherDepartmentOwner->update(['department' => 'กองคลัง']);

        foreach ([$sameDepartmentOwner, $otherDepartmentOwner] as $owner) {
            LeaveRequest::create([
                'user_id' => $owner->id,
                'leave_type' => 'ลาป่วย',
                'start_date' => now()->toDateString(),
                'end_date' => now()->toDateString(),
                'total_days' => 1,
                'reason' => 'ทดสอบ Badge พิจารณาใบลา',
                'status' => 'PENDING',
                'workflow_status' => 'pending_head',
            ]);
        }

        $this->actingAs($head)->get(route('home'))
            ->assertOk()
            ->assertSee('aria-label="ใบลารอพิจารณา 1 รายการ"', false)
            ->assertDontSee('aria-label="ใบลารอพิจารณา 2 รายการ"', false);
    }

    private function userWithRole(string $roleName, array $permissions = []): User
    {
        $role = Role::findOrCreate($roleName);
        foreach ($permissions as $permissionName) {
            Permission::findOrCreate($permissionName);
        }
        $role->syncPermissions($permissions);

        return User::factory()->create()->assignRole($role);
    }
}
