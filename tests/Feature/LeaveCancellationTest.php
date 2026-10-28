<?php

namespace Tests\Feature;

use App\Models\LeaveRequest;
use App\Models\User;
use App\Services\NotificationDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LeaveCancellationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['head', 'hr', 'palad', 'executive', 'officer'] as $role) {
            Role::findOrCreate($role);
        }
    }

    public function test_owner_can_cancel_their_own_pending_leave(): void
    {
        $owner = User::factory()->create(['department' => 'กองช่าง'])->assignRole('officer');
        $delegate = User::factory()->create(['department' => 'กองช่าง']);

        $leave = LeaveRequest::create([
            'user_id' => $owner->id,
            'delegate_id' => $delegate->id,
            'leave_type' => 'ลาพักผ่อน',
            'start_date' => now()->addDays(2)->toDateString(),
            'end_date' => now()->addDays(3)->toDateString(),
            'total_days' => 2,
            'reason' => 'ไปทำธุระส่วนตัว',
            'status' => 'PENDING',
            'workflow_status' => 'pending_delegate',
            'is_draft' => false,
        ]);

        $this->actingAs($owner)->get(route('leaves.show', $leave))
            ->assertOk()
            ->assertSee('ยกเลิกใบลาของฉัน');

        $this->actingAs($owner)->put(route('leaves.cancel', $leave))
            ->assertRedirect()
            ->assertSessionHas('success', 'ยกเลิกใบลาเรียบร้อยแล้ว');

        $leave->refresh();
        $this->assertSame('CANCELED', $leave->status);
        $this->assertSame('canceled', $leave->workflow_status);
    }

    public function test_owner_can_cancel_their_own_approved_leave(): void
    {
        $owner = User::factory()->create(['department' => 'กองช่าง'])->assignRole('officer');

        $leave = LeaveRequest::create([
            'user_id' => $owner->id,
            'leave_type' => 'ลาพักผ่อน',
            'start_date' => now()->addDays(5)->toDateString(),
            'end_date' => now()->addDays(6)->toDateString(),
            'total_days' => 2,
            'reason' => 'ลาพักผ่อนที่อนุมัติแล้ว',
            'status' => 'APPROVED',
            'workflow_status' => 'approved',
            'is_draft' => false,
        ]);

        $this->actingAs($owner)->get(route('leaves.show', $leave))
            ->assertOk()
            ->assertSee('ยกเลิกใบลาของฉัน');

        $this->actingAs($owner)->put(route('leaves.cancel', $leave))
            ->assertRedirect()
            ->assertSessionHas('success', 'ยกเลิกใบลาเรียบร้อยแล้ว');

        $leave->refresh();
        $this->assertSame('CANCELED', $leave->status);
        $this->assertSame('canceled', $leave->workflow_status);
    }

    public function test_user_cannot_cancel_someone_elses_leave(): void
    {
        $owner = User::factory()->create(['department' => 'กองช่าง'])->assignRole('officer');
        $otherUser = User::factory()->create(['department' => 'กองช่าง'])->assignRole('officer');

        $leave = LeaveRequest::create([
            'user_id' => $owner->id,
            'leave_type' => 'ลาพักผ่อน',
            'start_date' => now()->addDays(2)->toDateString(),
            'end_date' => now()->addDays(3)->toDateString(),
            'total_days' => 2,
            'reason' => 'ลาส่วนตัว',
            'status' => 'PENDING',
            'workflow_status' => 'pending_head',
            'is_draft' => false,
        ]);

        $this->actingAs($otherUser)->put(route('leaves.cancel', $leave))
            ->assertForbidden();

        $leave->refresh();
        $this->assertSame('PENDING', $leave->status);
    }

    public function test_cannot_cancel_already_rejected_or_canceled_leave(): void
    {
        $owner = User::factory()->create(['department' => 'กองช่าง'])->assignRole('officer');

        $rejectedLeave = LeaveRequest::create([
            'user_id' => $owner->id,
            'leave_type' => 'ลาพักผ่อน',
            'start_date' => now()->addDays(2)->toDateString(),
            'end_date' => now()->addDays(3)->toDateString(),
            'total_days' => 2,
            'reason' => 'ใบลาที่ถูกปฏิเสธ',
            'status' => 'REJECTED',
            'workflow_status' => 'rejected',
            'is_draft' => false,
        ]);

        $this->actingAs($owner)->put(route('leaves.cancel', $rejectedLeave))
            ->assertForbidden();

        $canceledLeave = LeaveRequest::create([
            'user_id' => $owner->id,
            'leave_type' => 'ลาพักผ่อน',
            'start_date' => now()->addDays(2)->toDateString(),
            'end_date' => now()->addDays(3)->toDateString(),
            'total_days' => 2,
            'reason' => 'ใบลาที่ยกเลิกไปแล้ว',
            'status' => 'CANCELED',
            'workflow_status' => 'canceled',
            'is_draft' => false,
        ]);

        $this->actingAs($owner)->put(route('leaves.cancel', $canceledLeave))
            ->assertForbidden();
    }
}
