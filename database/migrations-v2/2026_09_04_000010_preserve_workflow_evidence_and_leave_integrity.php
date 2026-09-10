<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('workflow_action_evidence', 'workflow_instance_id')) {
            Schema::table('workflow_action_evidence', function (Blueprint $table) {
                $table->foreignId('workflow_instance_id')->nullable()->after('workflow_step_id')
                    ->constrained('workflow_instances')->nullOnDelete();
                $table->string('resource_type', 30)->nullable()->after('workflow_instance_id');
                $table->unsignedBigInteger('resource_id')->nullable()->after('resource_type');
                $table->string('workflow_code', 100)->nullable()->after('resource_id');
                $table->unsignedInteger('workflow_version')->nullable()->after('workflow_code');
                $table->unsignedInteger('step_order')->nullable()->after('workflow_version');
                $table->string('step_name')->nullable()->after('step_order');
                $table->longText('signature_snapshot')->nullable()->after('signature_path');
                $table->index(['resource_type', 'resource_id', 'acted_at'], 'workflow_evidence_resource_index');
            });
        }

        if (DB::connection()->getDriverName() === 'mysql') {
            $database = DB::connection()->getDatabaseName();
            $deleteRule = DB::table('information_schema.REFERENTIAL_CONSTRAINTS')
                ->where('CONSTRAINT_SCHEMA', $database)
                ->where('TABLE_NAME', 'workflow_action_evidence')
                ->where('CONSTRAINT_NAME', 'workflow_action_evidence_workflow_step_id_foreign')
                ->value('DELETE_RULE');

            if ($deleteRule !== 'SET NULL') {
                DB::statement('ALTER TABLE workflow_action_evidence DROP FOREIGN KEY workflow_action_evidence_workflow_step_id_foreign');
                DB::statement('ALTER TABLE workflow_action_evidence MODIFY workflow_step_id BIGINT UNSIGNED NULL');
                DB::statement('ALTER TABLE workflow_action_evidence ADD CONSTRAINT workflow_action_evidence_workflow_step_id_foreign FOREIGN KEY (workflow_step_id) REFERENCES workflow_steps(id) ON DELETE SET NULL');
            }

            foreach (['delegate_id', 'numbered_by', 'supervisor_id', 'palad_id', 'nayok_id', 'inspector_id', 'head_id'] as $column) {
                $constraint = "leave_requests_{$column}_foreign";
                $exists = DB::table('information_schema.REFERENTIAL_CONSTRAINTS')
                    ->where('CONSTRAINT_SCHEMA', $database)
                    ->where('TABLE_NAME', 'leave_requests')
                    ->where('CONSTRAINT_NAME', $constraint)
                    ->exists();
                if (! $exists) {
                    DB::statement("ALTER TABLE leave_requests ADD CONSTRAINT {$constraint} FOREIGN KEY ({$column}) REFERENCES users(id) ON DELETE SET NULL");
                }
            }
        }
    }

    public function down(): void
    {
        // Evidence snapshots are deliberately retained. Rolling this migration back
        // must never restore cascading deletion of an audit record.
    }
};
