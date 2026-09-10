<?php

namespace Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\UsesV2Database;
use Tests\TestCase;

class RetentionPurgeCommandTest extends TestCase
{
    use UsesV2Database;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootV2Database();
        $this->seedV2User();
        Storage::fake('local');
        Storage::fake('documents');
        CarbonImmutable::setTestNow('2026-09-02 12:00:00');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_it_dry_runs_then_purges_only_records_older_than_three_calendar_months(): void
    {
        $db = DB::connection('mysql_v2');
        $db->table('document_types')->insert([
            'id' => 1, 'code' => 'INTERNAL', 'name' => 'Internal', 'direction' => 'INTERNAL',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        foreach ([
            [1, 'Expired document', '2026-05-31 12:00:00'],
            [2, 'Recoverable document', '2026-06-03 12:00:00'],
            [3, 'Active document', null],
        ] as [$id, $title, $deletedAt]) {
            $db->table('documents')->insert([
                'id' => $id,
                'uuid' => '00000000-0000-4000-8000-'.str_pad((string) $id, 12, '0', STR_PAD_LEFT),
                'document_type_id' => 1,
                'title' => $title,
                'status' => 'DRAFT',
                'created_by' => 1,
                'created_at' => '2026-01-01 00:00:00',
                'updated_at' => '2026-01-01 00:00:00',
                'deleted_at' => $deletedAt,
            ]);
        }

        $db->table('document_files')->insert([
            ['document_id' => 1, 'file_type' => 'MAIN', 'original_name' => 'expired.pdf', 'file_path' => 'retention/expired.pdf', 'created_at' => now(), 'updated_at' => now()],
            ['document_id' => 2, 'file_type' => 'MAIN', 'original_name' => 'recoverable.pdf', 'file_path' => 'retention/recoverable.pdf', 'created_at' => now(), 'updated_at' => now()],
        ]);
        Storage::disk('documents')->put('retention/expired.pdf', 'expired');
        Storage::disk('documents')->put('retention/recoverable.pdf', 'recoverable');

        $definitionId = $db->table('workflow_definitions')->insertGetId([
            'code' => 'RETENTION_TEST', 'name' => 'Retention test', 'resource_type' => 'DOCUMENT',
            'version' => 1, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $workflowId = $db->table('workflow_instances')->insertGetId([
            'workflow_definition_id' => $definitionId, 'document_id' => 1,
            'status' => 'CANCELED', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $stepId = $db->table('workflow_steps')->insertGetId([
            'workflow_instance_id' => $workflowId, 'step_order' => 1, 'step_name' => 'Archived',
            'action_type' => 'APPROVAL', 'status' => 'CANCELED', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $db->table('workflow_action_evidence')->insert([
            'workflow_step_id' => $stepId, 'workflow_instance_id' => $workflowId,
            'resource_type' => 'DOCUMENT', 'resource_id' => 1,
            'workflow_code' => 'RETENTION_TEST', 'workflow_version' => 1,
            'step_order' => 1, 'step_name' => 'Archived',
            'action' => 'CREATED', 'actor_name' => 'Test',
            'signature_path' => 'retention/evidence.png', 'acted_at' => now(), 'created_at' => now(),
        ]);
        Storage::disk('local')->put('retention/evidence.png', 'evidence');

        $leaveTypeId = $db->table('leave_types')->insertGetId([
            'code' => 'TEST', 'name' => 'Test leave', 'is_active' => true,
            'requires_attachment' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach ([[1, '2026-05-01 00:00:00'], [2, '2026-08-01 00:00:00']] as [$id, $deletedAt]) {
            $db->table('leave_requests')->insert([
                'id' => $id, 'user_id' => 1, 'leave_type_id' => $leaveTypeId,
                'start_date' => '2026-01-01', 'end_date' => '2026-01-01', 'total_days' => 1,
                'reason' => 'Retention test',
                'created_at' => now(), 'updated_at' => now(), 'deleted_at' => $deletedAt,
            ]);
        }

        $this->artisan('edoc:purge-retention', ['--dry-run' => true])
            ->expectsOutputToContain('Dry run completed')
            ->assertSuccessful();
        $this->assertDatabaseHas('documents', ['id' => 1], 'mysql_v2');
        Storage::disk('documents')->assertExists('retention/expired.pdf');

        $this->artisan('edoc:purge-retention')->assertSuccessful();

        $this->assertDatabaseMissing('documents', ['id' => 1], 'mysql_v2');
        $this->assertDatabaseHas('documents', ['id' => 2], 'mysql_v2');
        $this->assertDatabaseHas('documents', ['id' => 3], 'mysql_v2');
        $this->assertDatabaseMissing('leave_requests', ['id' => 1], 'mysql_v2');
        $this->assertDatabaseHas('leave_requests', ['id' => 2], 'mysql_v2');
        $this->assertDatabaseMissing('workflow_instances', ['id' => $workflowId], 'mysql_v2');
        $this->assertDatabaseHas('workflow_action_evidence', [
            'resource_type' => 'DOCUMENT', 'resource_id' => 1,
            'workflow_step_id' => null, 'workflow_instance_id' => null,
        ], 'mysql_v2');
        Storage::disk('documents')->assertMissing('retention/expired.pdf');
        Storage::disk('local')->assertExists('retention/evidence.png');
        Storage::disk('documents')->assertExists('retention/recoverable.pdf');
    }
}
