<?php

namespace Tests\Feature;

use App\Models\V2\Document;
use App\Models\V2\DocumentFile;
use App\Models\V2\WorkflowActionEvidence;
use App\Models\V2\WorkflowInstance;
use App\Models\V2\WorkflowStep;
use App\Services\V2\DataLifecycleService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use LogicException;
use Tests\Concerns\UsesV2Database;
use Tests\TestCase;

class V2DataLifecycleTest extends TestCase
{
    use UsesV2Database;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootV2Database();
        $this->seedV2User();
        $this->seedV2Document();
        Storage::fake('local');
        Storage::fake('documents');
        $this->seedChildren();
    }

    public function test_child_queries_hide_soft_deleted_parent_and_restore_recovers_them(): void
    {
        $document = Document::findOrFail(1);
        $document->delete();

        $this->assertSame(0, DocumentFile::count());
        $this->assertSame(0, WorkflowInstance::count());
        $this->assertSame(0, WorkflowStep::count());
        $this->assertSame(1, WorkflowActionEvidence::count());
        $this->assertSame(1, DocumentFile::withoutGlobalScopes()->count());
        $this->assertSame(1, WorkflowActionEvidence::withoutGlobalScopes()->count());

        app(DataLifecycleService::class)->restoreDocument(1);

        $this->assertSame(1, DocumentFile::count());
        $this->assertSame(1, WorkflowInstance::count());
        $this->assertSame(1, WorkflowStep::count());
        $this->assertSame(1, WorkflowActionEvidence::count());
        Storage::disk('documents')->assertExists('documents/main.pdf');
        Storage::disk('local')->assertExists('documents/evidence.png');
    }

    public function test_force_delete_uses_central_service_and_removes_database_children_and_files(): void
    {
        Document::findOrFail(1)->delete();
        $result = app(DataLifecycleService::class)->forceDeleteDocument(1);

        $this->assertSame(['documents' => 1, 'files' => 1], $result);
        $this->assertDatabaseMissing('documents', ['id' => 1], 'mysql_v2');
        $this->assertDatabaseMissing('document_files', ['document_id' => 1], 'mysql_v2');
        $this->assertDatabaseCount('workflow_action_evidence', 1, 'mysql_v2');
        $this->assertDatabaseHas('workflow_action_evidence', [
            'resource_type' => 'DOCUMENT', 'resource_id' => 1, 'workflow_step_id' => null,
        ], 'mysql_v2');
        Storage::disk('documents')->assertMissing('documents/main.pdf');
        Storage::disk('local')->assertExists('documents/evidence.png');
    }

    public function test_workflow_evidence_is_append_only(): void
    {
        $evidence = WorkflowActionEvidence::firstOrFail();

        $this->expectException(LogicException::class);
        $evidence->update(['comment' => 'must not change']);
    }

    private function seedChildren(): void
    {
        $db = DB::connection('mysql_v2');
        $db->table('document_files')->insert([
            'document_id' => 1, 'file_type' => 'MAIN', 'original_name' => 'main.pdf',
            'file_path' => 'documents/main.pdf', 'created_at' => now(), 'updated_at' => now(),
        ]);
        Storage::disk('documents')->put('documents/main.pdf', 'main');

        $definitionId = $db->table('workflow_definitions')->insertGetId([
            'code' => 'LIFECYCLE_TEST', 'name' => 'Lifecycle test', 'resource_type' => 'DOCUMENT',
            'version' => 1, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $workflowId = $db->table('workflow_instances')->insertGetId([
            'workflow_definition_id' => $definitionId, 'document_id' => 1,
            'status' => 'PENDING', 'current_step' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $stepId = $db->table('workflow_steps')->insertGetId([
            'workflow_instance_id' => $workflowId, 'step_order' => 1, 'step_name' => 'Review',
            'action_type' => 'APPROVAL', 'status' => 'PENDING', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $db->table('workflow_action_evidence')->insert([
            'workflow_step_id' => $stepId, 'workflow_instance_id' => $workflowId,
            'resource_type' => 'DOCUMENT', 'resource_id' => 1,
            'workflow_code' => 'LIFECYCLE_TEST', 'workflow_version' => 1,
            'step_order' => 1, 'step_name' => 'Review',
            'action' => 'CREATED', 'actor_name' => 'Tester',
            'signature_path' => 'documents/evidence.png', 'acted_at' => now(), 'created_at' => now(),
        ]);
        Storage::disk('local')->put('documents/evidence.png', 'evidence');
    }
}
