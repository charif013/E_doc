<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuthorizationPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_document_owner_can_edit_a_draft(): void
    {
        Role::create(['name' => 'officer']);
        $owner = User::factory()->create()->assignRole('officer');
        $other = User::factory()->create()->assignRole('officer');
        $document = Document::create([
            'doc_type' => 'internal', 'title' => 'ทดสอบสิทธิ์', 'status' => 'DRAFT',
            'created_by' => $owner->id,
        ]);

        $this->actingAs($other)->get(route('documents.edit', $document->uuid))->assertForbidden();
        $this->actingAs($owner)->get(route('documents.edit', $document->uuid))->assertOk();
    }

    public function test_leave_owner_can_view_but_unrelated_user_cannot(): void
    {
        Role::create(['name' => 'officer']);
        $owner = User::factory()->create()->assignRole('officer');
        $other = User::factory()->create()->assignRole('officer');
        $leave = LeaveRequest::create([
            'user_id' => $owner->id, 'leave_type' => 'ลาป่วย',
            'start_date' => now()->toDateString(), 'end_date' => now()->toDateString(),
            'total_days' => 1, 'reason' => 'ทดสอบ', 'status' => 'PENDING',
            'workflow_status' => 'pending_inspector',
        ]);

        $this->actingAs($owner)->get(route('leaves.show', $leave))->assertOk();
        $this->actingAs($other)->get(route('leaves.show', $leave))->assertForbidden();
    }

    public function test_legacy_document_is_not_visible_to_unrelated_authenticated_user(): void
    {
        Role::create(['name' => 'officer']);
        $owner = User::factory()->create(['department' => 'กองคลัง'])->assignRole('officer');
        $other = User::factory()->create(['department' => 'สำนักปลัด'])->assignRole('officer');
        $document = Document::create([
            'doc_type' => 'internal', 'title' => 'เอกสารระบบเดิม', 'status' => 'APPROVED',
            'created_by' => $owner->id,
        ]);

        $this->assertTrue($owner->can('view', $document));
        $this->assertFalse($other->can('view', $document));
    }

    public function test_legacy_head_can_only_view_documents_from_own_department(): void
    {
        Role::create(['name' => 'officer']);
        Role::create(['name' => 'head']);
        $owner = User::factory()->create(['department' => 'กองคลัง'])->assignRole('officer');
        $sameDepartmentHead = User::factory()->create(['department' => 'กองคลัง'])->assignRole('head');
        $otherDepartmentHead = User::factory()->create(['department' => 'กองช่าง'])->assignRole('head');
        $document = Document::create([
            'doc_type' => 'internal', 'title' => 'เอกสารรอหัวหน้า', 'status' => 'WAITING_SUPERVISOR',
            'created_by' => $owner->id,
        ]);

        $this->assertTrue($sameDepartmentHead->can('view', $document));
        $this->assertFalse($otherDepartmentHead->can('view', $document));
    }

    public function test_number_ledger_and_next_number_api_require_numbering_role(): void
    {
        Role::create(['name' => 'officer']);
        Role::create(['name' => 'saraban']);
        $officer = User::factory()->create()->assignRole('officer');
        $saraban = User::factory()->create()->assignRole('saraban');

        $this->actingAs($officer)->get(route('documents.number_ledger'))->assertForbidden();
        $this->actingAs($officer)->getJson(route('documents.api_next_number'))->assertForbidden();

        $this->actingAs($saraban)->get(route('documents.number_ledger'))->assertOk();
        $this->actingAs($saraban)->getJson(route('documents.api_next_number'))
            ->assertOk()->assertJsonStructure(['next_number', 'formatted']);
    }

    public function test_saraban_can_review_document_waiting_for_number_after_routes_are_completed(): void
    {
        Role::create(['name' => 'officer']);
        Role::create(['name' => 'saraban']);
        $owner = User::factory()->create()->assignRole('officer');
        $reviewer = User::factory()->create()->assignRole('officer');
        $saraban = User::factory()->create()->assignRole('saraban');
        $document = Document::create([
            'doc_type' => 'internal', 'title' => 'เอกสารรอออกเลข',
            'status' => 'WAITING_NUMBERING', 'current_step' => 2,
            'created_by' => $owner->id,
        ]);
        $document->routes()->create([
            'user_id' => $reviewer->id,
            'step_order' => 2,
            'status' => 'approved',
        ]);

        $this->assertTrue($saraban->can('review', $document));
        $this->assertFalse($owner->can('review', $document));
    }
}
