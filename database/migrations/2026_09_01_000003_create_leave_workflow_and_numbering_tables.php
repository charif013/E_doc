<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->decimal('max_days', 6, 2)->nullable();
            $table->boolean('requires_attachment')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->date('holiday_date')->unique();
            $table->string('name');
            $table->string('holiday_type', 30)->default('PUBLIC');
            $table->string('source')->default('admin');
            $table->timestamps();
        });

        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('leave_type_id')->constrained()->restrictOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('total_days', 8, 2);
            $table->text('reason');
            $table->string('contact_info')->nullable();
            $table->foreignId('delegate_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('delegate_status', 30)->nullable();
            $table->text('delegate_decline_reason')->nullable();
            $table->timestamp('delegate_requested_at')->nullable();
            $table->timestamp('delegate_responded_at')->nullable();
            $table->timestamp('delegate_reminded_at')->nullable();
            $table->timestamp('delegate_escalated_at')->nullable();
            $table->string('leave_number', 100)->nullable()->unique();
            $table->string('status', 30)->default('DRAFT');
            $table->text('reject_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['user_id', 'status', 'start_date']);
            $table->index(['status', 'created_at']);
            $table->index(['delegate_user_id', 'delegate_status']);
            $table->index(['start_date', 'end_date']);
        });

        Schema::create('workflow_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 100);
            $table->string('name');
            $table->string('resource_type', 30);
            $table->unsignedInteger('version')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['code', 'version']);
        });

        Schema::create('workflow_definition_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_definition_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('step_order');
            $table->string('name');
            $table->foreignId('required_role_id')->nullable()->constrained('roles')->nullOnDelete();
            $table->foreignId('required_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action_type', 30)->default('APPROVAL');
            $table->boolean('is_required')->default(true);
            $table->unsignedInteger('required_approvals')->default(1);
            $table->timestamps();
            $table->unique(['workflow_definition_id', 'step_order'], 'workflow_definition_step_unique');
        });

        Schema::create('workflow_instances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_definition_id')->constrained()->restrictOnDelete();
            $table->foreignId('document_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('leave_request_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('status', 30)->default('PENDING');
            $table->unsignedInteger('current_step')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique('document_id');
            $table->unique('leave_request_id');
            $table->index(['status', 'created_at']);
        });

        Schema::create('workflow_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_instance_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workflow_definition_step_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('step_order');
            $table->string('step_name');
            $table->string('action_type', 30);
            $table->foreignId('assigned_role_id')->nullable()->constrained('roles')->nullOnDelete();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 30)->default('PENDING');
            $table->text('comment')->nullable();
            $table->timestamp('acted_at')->nullable();
            $table->timestamps();
            $table->unique(['workflow_instance_id', 'step_order'], 'workflow_instance_step_unique');
            $table->index(['assigned_user_id', 'status']);
            $table->index(['assigned_role_id', 'status']);
            $table->index(['workflow_instance_id', 'status']);
        });

        Schema::create('workflow_action_evidence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_step_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('workflow_instance_id')->nullable()->constrained('workflow_instances')->nullOnDelete();
            $table->string('resource_type', 30)->nullable();
            $table->unsignedBigInteger('resource_id')->nullable();
            $table->string('workflow_code', 100)->nullable();
            $table->unsignedInteger('workflow_version')->nullable();
            $table->unsignedInteger('step_order')->nullable();
            $table->string('step_name')->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 30);
            $table->string('actor_name');
            $table->string('actor_position')->nullable();
            $table->string('signature_path', 500)->nullable();
            $table->longText('signature_snapshot')->nullable();
            $table->char('signature_sha256', 64)->nullable();
            $table->text('comment')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('request_id', 64)->nullable();
            $table->timestamp('acted_at');
            $table->timestamp('created_at')->nullable();
            $table->index(['workflow_step_id', 'acted_at']);
            $table->index(['resource_type', 'resource_id', 'acted_at'], 'workflow_evidence_resource_index');
            $table->index('request_id');
        });

        Schema::create('number_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('number_type', 50);
            $table->string('scope', 191)->default('organization');
            $table->unsignedSmallInteger('fiscal_year');
            $table->string('prefix', 100)->nullable();
            $table->unsignedInteger('last_number')->default(0);
            $table->unsignedTinyInteger('padding')->default(0);
            $table->timestamps();
            $table->unique(['number_type', 'scope', 'fiscal_year']);
        });

        Schema::create('number_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sequence_id')->constrained('number_sequences')->restrictOnDelete();
            $table->unsignedInteger('running_number');
            $table->string('formatted_number');
            $table->foreignId('document_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('leave_request_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('allocated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('allocated_at')->useCurrent();
            $table->unique(['sequence_id', 'running_number']);
            $table->unique(['sequence_id', 'formatted_number']);
            $table->unique('document_id');
            $table->unique('leave_request_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('number_allocations');
        Schema::dropIfExists('number_sequences');
        Schema::dropIfExists('workflow_action_evidence');
        Schema::dropIfExists('workflow_steps');
        Schema::dropIfExists('workflow_instances');
        Schema::dropIfExists('workflow_definition_steps');
        Schema::dropIfExists('workflow_definitions');
        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('holidays');
        Schema::dropIfExists('leave_types');
    }
};
