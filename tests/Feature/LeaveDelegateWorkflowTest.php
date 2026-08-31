<?php

namespace Tests\Feature;

use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LeaveDelegateWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('hr');
    }

    private function leave(User $owner, User $delegate, array $attributes = []): LeaveRequest
    {
        return LeaveRequest::create(array_merge([
            'user_id' => $owner->id,
            'leave_type' => 'ลาป่วย',
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'total_days' => 1,
            'reason' => 'ทดสอบ',
            'contact_info' => '0800000000',
            'status' => 'PENDING',
            'workflow_status' => 'pending_delegate',
            'delegate_id' => $delegate->id,
            'delegate_status' => 'pending',
            'delegate_requested_at' => now(),
        ], $attributes));
    }

    public function test_delegate_can_accept_assignment(): void
    {
        $owner = User::factory()->create();
        $delegate = User::factory()->create();
        $leave = $this->leave($owner, $delegate);

        $this->actingAs($delegate)
            ->post(route('leaves.delegateAction', $leave), ['action' => 'accept'])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('leave_requests', [
            'id' => $leave->id, 'delegate_status' => 'accepted', 'workflow_status' => 'pending_inspector',
        ]);
    }

    public function test_delegate_can_decline_with_reason_and_owner_can_reassign(): void
    {
        $owner = User::factory()->create();
        $delegate = User::factory()->create();
        $replacement = User::factory()->create();
        $leave = $this->leave($owner, $delegate);

        $this->actingAs($delegate)->post(route('leaves.delegateAction', $leave), [
            'action' => 'decline', 'decline_reason' => 'ติดภารกิจราชการ',
        ])->assertSessionHas('success');
        $this->assertDatabaseHas('leave_requests', [
            'id' => $leave->id, 'delegate_status' => 'declined',
            'delegate_decline_reason' => 'ติดภารกิจราชการ', 'workflow_status' => 'delegate_declined',
        ]);

        $this->actingAs($owner)->post(route('leaves.reassign_delegate', $leave), [
            'delegate_id' => $replacement->id,
        ])->assertSessionHas('success');
        $this->assertDatabaseHas('leave_requests', [
            'id' => $leave->id, 'delegate_id' => $replacement->id,
            'delegate_status' => 'pending', 'workflow_status' => 'pending_delegate',
            'delegate_decline_reason' => null,
        ]);
    }

    public function test_unrelated_user_cannot_respond_or_reassign(): void
    {
        $owner = User::factory()->create();
        $delegate = User::factory()->create();
        $unrelated = User::factory()->create();
        $leave = $this->leave($owner, $delegate);

        $this->actingAs($unrelated)
            ->post(route('leaves.delegateAction', $leave), ['action' => 'accept'])
            ->assertForbidden();
        $this->actingAs($unrelated)
            ->post(route('leaves.reassign_delegate', $leave), ['delegate_id' => $delegate->id])
            ->assertForbidden();
    }

    public function test_pending_delegation_is_reminded_then_escalated(): void
    {
        config([
            'services.leave_delegate.remind_after_hours' => 24,
            'services.leave_delegate.escalate_after_hours' => 48,
        ]);
        $owner = User::factory()->create();
        $delegate = User::factory()->create();
        $leave = $this->leave($owner, $delegate, ['delegate_requested_at' => now()->subHours(25)]);

        $this->artisan('leaves:process-delegate-timeouts')->assertSuccessful();
        $this->assertNotNull($leave->fresh()->delegate_reminded_at);

        $leave->update(['delegate_requested_at' => now()->subHours(49)]);
        $this->artisan('leaves:process-delegate-timeouts')->assertSuccessful();
        $this->assertSame('delegate_escalated', $leave->fresh()->workflow_status);
        $this->assertNotNull($leave->fresh()->delegate_escalated_at);
    }
}
