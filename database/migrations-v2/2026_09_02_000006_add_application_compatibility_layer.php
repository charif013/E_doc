<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('department')->nullable()->after('line_id');
            $table->string('division')->nullable()->after('department');
            $table->string('work_unit')->nullable()->after('division');
            $table->string('position')->nullable()->after('work_unit');
            $table->longText('signature')->nullable()->after('pin');
            $table->index('department');
        });

        Schema::table('leave_requests', function (Blueprint $table) {
            $table->string('leave_type')->nullable()->after('leave_type_id');
            $table->foreignId('delegate_id')->nullable()->after('delegate_user_id')->constrained('users')->nullOnDelete();
            $table->string('workflow_status', 50)->nullable()->index();
            $table->unsignedInteger('running_number')->nullable();
            $table->foreignId('numbered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('numbered_at')->nullable();
            foreach (['supervisor', 'palad', 'nayok'] as $role) {
                $table->foreignId($role.'_id')->nullable()->constrained('users')->nullOnDelete();
                $table->longText($role.'_signature')->nullable();
                $table->timestamp($role.'_approved_at')->nullable();
            }
            foreach (['inspector', 'head'] as $role) {
                $table->foreignId($role.'_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string($role.'_status', 30)->nullable();
                $table->longText($role.'_signature')->nullable();
                $table->timestamp($role.'_at')->nullable();
            }
            $table->string('palad_status', 30)->nullable();
            $table->timestamp('palad_at')->nullable();
            $table->string('nayok_status', 30)->nullable();
            $table->timestamp('nayok_at')->nullable();
        });

        Schema::create('document_number_allocations', function (Blueprint $table) {
            $table->id();
            $table->string('number_type', 30);
            $table->string('scope', 191)->default('organization');
            $table->unsignedSmallInteger('fiscal_year');
            $table->unsignedInteger('running_number')->nullable();
            $table->string('formatted_number')->nullable();
            $table->foreignId('document_id')->nullable();
            $table->foreignId('leave_request_id')->nullable();
            $table->foreignId('allocated_by')->nullable();
            $table->timestamps();
            $table->unique(['number_type', 'scope', 'fiscal_year', 'running_number'], 'compat_number_running_unique');
            $table->unique(['number_type', 'scope', 'fiscal_year', 'formatted_number'], 'compat_number_formatted_unique');
            $table->unique('document_id');
            $table->unique('leave_request_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_number_allocations');
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->dropColumn([
                'leave_type', 'delegate_id', 'workflow_status', 'running_number', 'numbered_by', 'numbered_at',
                'supervisor_id', 'supervisor_signature', 'supervisor_approved_at',
                'palad_id', 'palad_signature', 'palad_approved_at', 'palad_status', 'palad_at',
                'nayok_id', 'nayok_signature', 'nayok_approved_at', 'nayok_status', 'nayok_at',
                'inspector_id', 'inspector_status', 'inspector_signature', 'inspector_at',
                'head_id', 'head_status', 'head_signature', 'head_at',
            ]);
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['department']);
            $table->dropColumn(['department', 'division', 'work_unit', 'position', 'signature']);
        });
    }
};
