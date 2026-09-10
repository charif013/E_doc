<?php

namespace Tests\Feature;

use App\Models\User as LegacyUser;
use App\Models\V2\Document;
use App\Models\V2\Room;
use App\Models\V2\User;
use App\Models\V2\WorkflowInstance;
use App\Services\V2\DashboardDocumentService;
use App\Services\V2\DocumentNumberingService;
use App\Services\V2\DocumentWriteService;
use App\Services\V2\RoomBookingService;
use App\Services\V2\WorkflowService;
use App\Policies\V2\DocumentPolicy;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Mockery;
use Mockery\MockInterface;
use Tests\Concerns\UsesV2Database;
use Tests\TestCase;

class V2SchemaAndServicesTest extends TestCase
{
    use UsesV2Database;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootV2Database();
    }

    public function test_v2_schema_contains_the_required_business_tables(): void
    {
        $schema = Schema::connection('mysql_v2');

        foreach ([
            'organization_units', 'users', 'user_signatures', 'documents', 'document_files',
            'leave_requests', 'workflow_instances', 'workflow_steps', 'workflow_action_evidence',
            'number_sequences', 'number_allocations', 'rooms', 'room_bookings', 'audit_logs',
        ] as $table) {
            $this->assertTrue($schema->hasTable($table), "Missing V2 table: {$table}");
        }
    }

    public function test_number_allocation_is_scoped_and_rejects_a_duplicate(): void
    {
        $this->seedV2User();
        $this->seedV2Document(1);
        $this->seedV2Document(2);
        $service = app(DocumentNumberingService::class);

        $service->allocate(Document::findOrFail(1), 1, 1, 'ยล 77301/1', 'สำนักงานปลัด');
        $this->assertDatabaseHas('number_allocations', [
            'document_id' => 1, 'running_number' => 1, 'formatted_number' => 'ยล 77301/1',
        ], 'mysql_v2');

        $this->expectException(ValidationException::class);
        $service->allocate(Document::findOrFail(2), 1, 1, 'ยล 77301/1', 'สำนักงานปลัด');
    }

    public function test_internal_number_uses_the_document_owners_ledger_scope(): void
    {
        $db = DB::connection('mysql_v2');
        $ownerUnitId = $db->table('organization_units')->insertGetId([
            'unit_type' => 'DEPARTMENT', 'name' => 'กองคลัง', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $issuerUnitId = $db->table('organization_units')->insertGetId([
            'unit_type' => 'DEPARTMENT', 'name' => 'สำนักงานปลัด', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->seedV2User(1);
        $this->seedV2User(2);
        $db->table('users')->where('id', 1)->update(['organization_unit_id' => $ownerUnitId]);
        $db->table('users')->where('id', 2)->update(['organization_unit_id' => $issuerUnitId]);
        $this->seedV2Document(1, 1);

        app(DocumentWriteService::class)->allocateNumber(
            Document::findOrFail(1),
            2,
            1,
            'ยล 77301/1'
        );

        $this->assertDatabaseHas('number_sequences', [
            'number_type' => 'internal',
            'scope' => 'กองคลัง',
            'last_number' => 1,
        ], 'mysql_v2');
        $this->assertDatabaseMissing('number_sequences', [
            'number_type' => 'internal',
            'scope' => 'สำนักงานปลัด',
        ], 'mysql_v2');
    }

    public function test_room_booking_service_rejects_an_overlapping_period(): void
    {
        $this->seedV2User();
        Room::create(['name' => 'Meeting room', 'status' => 'ACTIVE']);
        $service = app(RoomBookingService::class);
        $booking = $service->create([
            'room_id' => 1, 'title' => 'First', 'booking_type' => 'meeting',
            'start_time' => '2026-09-02 10:00:00', 'end_time' => '2026-09-02 11:00:00',
        ], 1);
        $this->assertSame('APPROVED', $booking->status);

        $this->expectException(ValidationException::class);
        $service->create([
            'room_id' => 1, 'title' => 'Overlap', 'booking_type' => 'meeting',
            'start_time' => '2026-09-02 10:30:00', 'end_time' => '2026-09-02 11:30:00',
        ], 1);
    }

    public function test_workflow_completion_creates_immutable_actor_and_signature_evidence(): void
    {
        $this->seedV2User();
        $this->seedV2Document();
        $db = DB::connection('mysql_v2');
        $db->table('user_signatures')->insert([
            'user_id' => 1, 'file_path' => 'private/signatures/1.png',
            'sha256' => str_repeat('a', 64), 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $definitionId = $db->table('workflow_definitions')->insertGetId([
            'code' => 'TEST', 'name' => 'Test', 'resource_type' => 'DOCUMENT',
            'version' => 1, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $instanceId = $db->table('workflow_instances')->insertGetId([
            'workflow_definition_id' => $definitionId, 'document_id' => 1,
            'status' => 'IN_PROGRESS', 'current_step' => 1,
            'started_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $db->table('workflow_steps')->insert([
            'workflow_instance_id' => $instanceId, 'step_order' => 1,
            'step_name' => 'Approve', 'action_type' => 'APPROVAL',
            'assigned_user_id' => 1, 'status' => 'PENDING',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        app(WorkflowService::class)->act(
            WorkflowInstance::findOrFail($instanceId), User::findOrFail(1), 'approved', 'Looks good', 'test-request'
        );

        $this->assertDatabaseHas('workflow_action_evidence', [
            'actor_id' => 1, 'actor_name' => 'V2 User 1',
            'signature_sha256' => str_repeat('a', 64), 'request_id' => 'test-request',
        ], 'mysql_v2');
        $this->assertDatabaseHas('documents', ['id' => 1, 'status' => 'APPROVED'], 'mysql_v2');
    }

    public function test_legacy_copy_command_repairs_a_missing_document_allocation_in_v2_only(): void
    {
        config()->set('database.connections.legacy_test', [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        DB::purge('legacy_test');
        $exitCode = Artisan::call('migrate', [
            '--database' => 'legacy_test', '--path' => 'database/migrations', '--force' => true,
        ]);
        $this->assertSame(0, $exitCode, Artisan::output());

        $legacy = DB::connection('legacy_test');
        $legacy->table('users')->insert([
            'id' => 99, 'name' => 'Legacy User', 'email' => 'legacy@example.test',
            'department' => 'สำนักงานปลัด', 'position' => 'เจ้าหน้าที่',
            'password' => password_hash('password', PASSWORD_BCRYPT),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $legacy->table('documents')->insert([
            'id' => 99, 'uuid' => '00000000-0000-4000-8000-000000000099',
            'doc_type' => 'internal', 'doc_number' => 'ยล 77301/7', 'running_number' => 7,
            'title' => 'Legacy numbered document', 'status' => 'PROCESSING', 'created_by' => 99,
            'created_at' => '2026-09-01 00:00:00', 'updated_at' => '2026-09-01 00:00:00',
        ]);
        $this->assertSame(0, $legacy->table('document_number_allocations')->count());

        $exitCode = Artisan::call('edoc:v2-migrate', [
            '--source' => 'legacy_test', '--target' => 'mysql_v2', '--commit' => true,
        ]);
        $this->assertSame(0, $exitCode, Artisan::output());
        $this->assertSame(0, $legacy->table('document_number_allocations')->count());
        $this->assertDatabaseHas('number_allocations', [
            'document_id' => 99, 'running_number' => 7, 'formatted_number' => 'ยล 77301/7',
        ], 'mysql_v2');
    }

    public function test_v2_models_expose_transitional_legacy_read_attributes(): void
    {
        Storage::fake('local');
        $db = DB::connection('mysql_v2');
        $departmentId = $db->table('organization_units')->insertGetId([
            'unit_type' => 'DEPARTMENT', 'name' => 'สำนักงานปลัด', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $divisionId = $db->table('organization_units')->insertGetId([
            'parent_id' => $departmentId, 'unit_type' => 'DIVISION', 'name' => 'ฝ่ายบริหาร',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $positionId = $db->table('positions')->insertGetId([
            'name' => 'เจ้าหน้าที่', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->seedV2User();
        $db->table('users')->where('id', 1)->update([
            'organization_unit_id' => $divisionId, 'position_id' => $positionId,
        ]);
        Storage::disk('local')->put('private/v2-signatures/1.png', 'signature-bytes');
        $db->table('user_signatures')->insert([
            'user_id' => 1, 'file_path' => 'private/v2-signatures/1.png',
            'sha256' => hash('sha256', 'signature-bytes'), 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->seedV2Document();
        $sequenceId = $db->table('number_sequences')->insertGetId([
            'number_type' => 'internal', 'scope' => 'สำนักงานปลัด', 'fiscal_year' => 2026,
            'last_number' => 12, 'padding' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $db->table('number_allocations')->insert([
            'sequence_id' => $sequenceId, 'running_number' => 12,
            'formatted_number' => 'ยล 77301/12', 'document_id' => 1,
            'allocated_by' => 1, 'allocated_at' => now(),
        ]);
        $definitionId = $db->table('workflow_definitions')->insertGetId([
            'code' => 'READ', 'name' => 'Read', 'resource_type' => 'DOCUMENT', 'version' => 1,
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $db->table('workflow_instances')->insert([
            'workflow_definition_id' => $definitionId, 'document_id' => 1,
            'status' => 'IN_PROGRESS', 'current_step' => 2,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $user = User::findOrFail(1);
        $document = Document::findOrFail(1);
        $this->assertSame('สำนักงานปลัด', $user->department);
        $this->assertSame('ฝ่ายบริหาร', $user->division);
        $this->assertSame('เจ้าหน้าที่', $user->position);
        $this->assertStringStartsWith('data:image/png;base64,', $user->signature);
        $this->assertSame('internal', $document->doc_type);
        $this->assertSame(12, $document->running_number);
        $this->assertSame(2, $document->current_step);
    }

    public function test_v2_dashboard_reads_normalized_document_columns(): void
    {
        $this->seedV2User();
        $this->seedV2Document();
        /** @var LegacyUser&MockInterface $user */
        $user = Mockery::mock(LegacyUser::class)->makePartial();
        $user->forceFill(['id' => 1]);
        $user->shouldReceive('hasRole')->with('super-admin')->andReturn(true);

        $data = app(DashboardDocumentService::class)->data($user, 'Document 1');

        $this->assertSame(1, $data['stats']['total']);
        $this->assertSame(1, $data['stats']['waiting']);
        $this->assertCount(1, $data['recentDocs']);
        $this->assertCount(1, $data['searchResults']);
        $this->assertSame('internal', $data['recentDocs']->first()->doc_type);
    }

    public function test_v2_document_write_lifecycle_creates_file_workflow_evidence_and_number(): void
    {
        Storage::fake('documents');
        Storage::fake('local');
        $this->seedV2User(1);
        $this->seedV2User(2);
        $db = DB::connection('mysql_v2');
        $db->table('document_types')->insert([
            'code' => 'INTERNAL', 'name' => 'Internal', 'direction' => 'INTERNAL',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach ([1, 2] as $userId) {
            $path = "private/v2-signatures/{$userId}.png";
            Storage::disk('local')->put($path, "signature-{$userId}");
            $db->table('user_signatures')->insert([
                'user_id' => $userId, 'file_path' => $path,
                'sha256' => hash('sha256', "signature-{$userId}"), 'is_active' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $service = app(DocumentWriteService::class);
        $document = $service->create([
            'doc_type' => 'internal', 'title' => 'V2 lifecycle', 'doc_date' => '2026-09-02',
            'content' => 'Test content', 'doc_from' => 'สำนักงานปลัด', 'doc_to' => 'นายก',
        ], [2], 1, UploadedFile::fake()->create('memo.pdf', 100, 'application/pdf'));

        $this->assertSame('DRAFT', $document->status);
        $this->assertDatabaseHas('document_files', ['document_id' => $document->id, 'file_type' => 'MAIN'], 'mysql_v2');
        $this->assertDatabaseCount('workflow_steps', 2, 'mysql_v2');

        $service->submit($document, User::findOrFail(1));
        $this->assertSame('IN_REVIEW', $document->fresh()->status);
        $service->review($document->fresh(), User::findOrFail(2), true, 'Approved', null);
        $this->assertSame('APPROVED', $document->fresh()->status);
        $this->assertDatabaseHas('workflow_action_evidence', ['actor_id' => 1, 'action' => 'CREATED'], 'mysql_v2');
        $this->assertDatabaseHas('workflow_action_evidence', ['actor_id' => 2, 'action' => 'APPROVED'], 'mysql_v2');

        $service->allocateNumber($document->fresh(), 1, 25, 'ยล 77301/25');
        $this->assertSame('COMPLETED', $document->fresh()->status);
        $this->assertDatabaseHas('number_allocations', [
            'document_id' => $document->id, 'running_number' => 25, 'formatted_number' => 'ยล 77301/25',
        ], 'mysql_v2');
    }

    public function test_creating_numbered_outgoing_document_does_not_require_receive_number(): void
    {
        $this->seedV2User(1);
        $this->seedV2User(2);
        DB::connection('mysql_v2')->table('document_types')->insert([
            'code' => 'OUTGOING', 'name' => 'Outgoing', 'direction' => 'OUTGOING',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $document = app(DocumentWriteService::class)->create([
            'doc_type' => 'outgoing',
            'doc_number' => 'ยล 77301/7',
            'running_number' => 7,
            'doc_date' => '2026-09-05',
            'title' => 'Outgoing numbered document',
            'doc_to' => 'หน่วยงานปลายทาง',
        ], [2], 1);

        $this->assertSame('ยล 77301/7', $document->fresh()->document_number);
        $this->assertDatabaseHas('number_allocations', [
            'document_id' => $document->id,
            'running_number' => 7,
            'formatted_number' => 'ยล 77301/7',
        ], 'mysql_v2');

        $service = app(DocumentWriteService::class);
        $service->submit($document->fresh(), User::findOrFail(1));
        $service->review($document->fresh(), User::findOrFail(2), true, 'อนุมัติ', null);

        $this->assertSame('COMPLETED', $document->fresh()->status);
    }

    public function test_future_reviewer_cannot_view_document_until_current_step_reaches_them(): void
    {
        $this->seedV2User(1);
        $this->seedV2User(2);
        $this->seedV2User(3);
        DB::connection('mysql_v2')->table('document_types')->insert([
            'code' => 'INTERNAL', 'name' => 'Internal', 'direction' => 'INTERNAL',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $service = app(DocumentWriteService::class);
        $document = $service->create([
            'doc_type' => 'internal', 'title' => 'Sequential document',
            'doc_date' => '2026-09-05', 'content' => 'Test',
        ], [2, 3], 1);
        $service->submit($document, User::findOrFail(1));

        $futureReviewer = Mockery::mock(LegacyUser::class)->makePartial();
        $futureReviewer->forceFill(['id' => 3]);
        $futureReviewer->shouldReceive('hasRole')->with('super-admin')->andReturn(false);
        $futureReviewer->shouldReceive('hasRole')->with('saraban')->andReturn(false);
        $futureReviewer->shouldReceive('hasRole')->with('head')->andReturn(false);
        $policy = app(DocumentPolicy::class);

        $this->assertFalse($policy->view($futureReviewer, $document->fresh()));
        $this->assertCount(0, app(DashboardDocumentService::class)->data($futureReviewer)['recentDocs']);

        $service->review($document->fresh(), User::findOrFail(2), true, 'ผ่าน', null);

        $this->assertTrue($policy->view($futureReviewer, $document->fresh()));
        $this->assertCount(1, app(DashboardDocumentService::class)->data($futureReviewer)['recentDocs']);
    }

    public function test_v2_document_rejection_can_be_edited_and_resubmitted(): void
    {
        Storage::fake('local');
        $this->seedV2User(1);
        $this->seedV2User(2);
        $db = DB::connection('mysql_v2');
        $db->table('document_types')->insert([
            'code' => 'INTERNAL', 'name' => 'Internal', 'direction' => 'INTERNAL',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach ([1, 2] as $userId) {
            $db->table('user_signatures')->insert([
                'user_id' => $userId, 'file_path' => "private/{$userId}.png",
                'sha256' => str_repeat((string) $userId, 64), 'is_active' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $service = app(DocumentWriteService::class);
        $document = $service->create([
            'doc_type' => 'internal', 'title' => 'Reject me', 'doc_date' => '2026-09-02', 'content' => 'Draft',
        ], [2], 1);
        $service->submit($document, User::findOrFail(1));
        $service->review($document->fresh(), User::findOrFail(2), false, 'Needs correction', null);
        $this->assertSame('REJECTED', $document->fresh()->status);
        $this->assertSame('Needs correction', $document->fresh()->rejection_reason);

        $updated = $service->update($document->fresh(), [
            'title' => 'Corrected', 'doc_date' => '2026-09-02', 'content' => 'Corrected content',
        ], 1);
        $this->assertSame('DRAFT', $updated->status);
        $this->assertNull($updated->rejection_reason);
        $this->assertDatabaseMissing('workflow_action_evidence', ['workflow_step_id' => $updated->workflow->steps->first()->id], 'mysql_v2');
    }

    public function test_v2_assignment_delegation_and_confidential_access_decision(): void
    {
        $db = DB::connection('mysql_v2');
        $unitId = $db->table('organization_units')->insertGetId([
            'unit_type' => 'DEPARTMENT', 'name' => 'กองทดสอบ', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->seedV2User(1);
        $this->seedV2User(2);
        $db->table('users')->whereIn('id', [1, 2])->update(['organization_unit_id' => $unitId]);
        $this->seedV2Document();
        $document = Document::findOrFail(1);
        $document->update(['status' => 'APPROVED']);
        $document->assignments()->create([
            'assigned_user_id' => 1, 'assigned_unit_id' => $unitId, 'assigned_by' => 1,
            'status' => 'PENDING', 'assigned_at' => now(),
        ]);
        /** @var LegacyUser&MockInterface $actor */
        $actor = Mockery::mock(LegacyUser::class)->makePartial();
        $actor->forceFill(['id' => 1, 'department' => 'กองทดสอบ']);
        $actor->shouldReceive('hasRole')->with('head')->andReturn(false);

        $service = app(DocumentWriteService::class);
        $service->assign($document, $actor, 'accept');
        $this->assertDatabaseHas('document_assignments', [
            'document_id' => 1, 'assigned_user_id' => 1, 'status' => 'ACKNOWLEDGED',
        ], 'mysql_v2');
        $service->assign($document, $actor, 'delegate', 2);
        $this->assertDatabaseHas('document_assignments', [
            'document_id' => 1, 'assigned_user_id' => 2, 'delegated_by' => 1, 'status' => 'PENDING',
        ], 'mysql_v2');

        $request = $service->requestAccess($document, 2, 'Need to process this document');
        $service->decideAccess($request, $actor, 'approved');
        $this->assertDatabaseHas('document_access_requests', [
            'document_id' => 1, 'requested_by' => 2, 'reviewed_by' => 1, 'status' => 'APPROVED',
        ], 'mysql_v2');
    }

    public function test_v2_assignment_proposal_is_hidden_until_the_final_reviewer_approves(): void
    {
        $db = DB::connection('mysql_v2');
        $unitId = $db->table('organization_units')->insertGetId([
            'unit_type' => 'DEPARTMENT', 'name' => 'กองช่าง', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $db->table('document_types')->insert([
            'code' => 'INCOMING', 'name' => 'Incoming', 'direction' => 'INCOMING',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach ([1, 2, 3] as $id) {
            $this->seedV2User($id);
        }

        $service = app(DocumentWriteService::class);
        $document = $service->create([
            'doc_type' => 'incoming', 'title' => 'Incoming assignment proposal',
            'doc_date' => '2026-09-09', 'receive_date' => '2026-09-09',
            'receive_number' => 'รับ 1', 'content' => 'Details',
        ], [2, 3], 1);
        $service->submit($document, User::findOrFail(1));
        $service->review($document->fresh(), User::findOrFail(2), true, 'เสนอให้กองช่าง', 'กองช่าง');

        $this->assertDatabaseHas('document_assignments', [
            'document_id' => $document->id,
            'assigned_unit_id' => $unitId,
            'status' => 'PROPOSED',
            'assigned_at' => null,
        ], 'mysql_v2');

        /** @var LegacyUser&MockInterface $head */
        $head = Mockery::mock(LegacyUser::class)->makePartial();
        $head->forceFill(['id' => 99, 'department' => 'กองช่าง']);
        $head->shouldReceive('hasRole')->with('head')->andReturn(true);
        $this->assertCount(0, app(\App\Services\V2\DocumentReadService::class)->assignedDocuments($head));

        $service->review($document->fresh(), User::findOrFail(3), true, 'อนุมัติ', 'กองช่าง');

        $this->assertSame('APPROVED', $document->fresh()->status);
        $this->assertDatabaseHas('document_assignments', [
            'document_id' => $document->id,
            'assigned_unit_id' => $unitId,
            'status' => 'PENDING',
        ], 'mysql_v2');
        $this->assertCount(1, app(\App\Services\V2\DocumentReadService::class)->assignedDocuments($head));
    }

    public function test_acknowledged_unit_assignment_leaves_the_dashboard_action_queue(): void
    {
        $db = DB::connection('mysql_v2');
        $unitId = $db->table('organization_units')->insertGetId([
            'unit_type' => 'DEPARTMENT', 'name' => 'กองช่าง', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->seedV2User();
        $db->table('users')->where('id', 1)->update(['organization_unit_id' => $unitId]);
        $this->seedV2Document();
        $db->table('documents')->where('id', 1)->update(['status' => 'APPROVED']);
        $assignmentId = $db->table('document_assignments')->insertGetId([
            'document_id' => 1, 'assigned_unit_id' => $unitId,
            'status' => 'PENDING', 'assigned_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        /** @var LegacyUser&MockInterface $head */
        $head = Mockery::mock(LegacyUser::class)->makePartial();
        $head->forceFill(['id' => 1, 'department' => 'กองช่าง']);
        $head->shouldReceive('hasRole')->with('super-admin')->andReturn(false);
        $head->shouldReceive('hasRole')->with('head')->andReturn(true);
        $roles = Mockery::mock(\Illuminate\Database\Eloquent\Relations\BelongsToMany::class);
        $roles->shouldReceive('pluck')->with('roles.id')->andReturn(collect());
        $head->shouldReceive('roles')->andReturn($roles);

        $queue = app(\App\Services\V2\DocumentReadService::class);
        $this->assertCount(1, $queue->approvalQueue($head));

        $db->table('document_assignments')->where('id', $assignmentId)->update([
            'assigned_user_id' => 1,
            'status' => 'ACKNOWLEDGED',
            'acknowledged_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertCount(0, $queue->approvalQueue($head));
    }
}
