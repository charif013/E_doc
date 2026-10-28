<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $statusChecks = [
        'documents' => ['DRAFT', 'REGISTERED', 'IN_REVIEW', 'APPROVED', 'REJECTED', 'COMPLETED', 'CANCELED', 'ARCHIVED'],
        'workflow_instances' => ['PENDING', 'IN_PROGRESS', 'APPROVED', 'REJECTED', 'CANCELED', 'COMPLETED'],
        'workflow_steps' => ['PENDING', 'IN_PROGRESS', 'APPROVED', 'REJECTED', 'SKIPPED', 'CANCELED'],
        'document_assignments' => ['PROPOSED', 'PENDING', 'ACKNOWLEDGED', 'IN_PROGRESS', 'COMPLETED', 'CANCELED'],
        'document_access_requests' => ['PENDING', 'APPROVED', 'REJECTED', 'REVOKED'],
        'room_bookings' => ['PENDING', 'APPROVED', 'REJECTED', 'CANCELED', 'COMPLETED'],
    ];

    public function up(): void
    {
        $this->assertCanonicalLeaveDataIsReady();

        if (DB::connection()->getDriverName() === 'sqlite') {
            $this->rebuildCanonicalLeavesForSqlite();
            $this->addOperationalIndexes();

            return;
        }

        // MySQL currently uses the compatibility composite index to support
        // the delegate_user_id foreign key, so provide its canonical index first.
        Schema::table('leave_requests', fn (Blueprint $table) =>
            $table->index('delegate_user_id', 'leave_requests_delegate_user_id_index'));

        Schema::table('leave_requests', function (Blueprint $table) {
            $table->dropIndex('leave_requests_delegate_user_id_delegate_status_index');
            $table->dropIndex('leave_requests_status_created_at_index');
            $table->dropIndex('leave_requests_workflow_status_index');
            $table->dropUnique('leave_requests_leave_number_unique');

            foreach ([
                'delegate_id', 'numbered_by', 'supervisor_id', 'palad_id', 'nayok_id',
                'inspector_id', 'head_id',
            ] as $column) {
                $table->dropConstrainedForeignId($column);
            }

            $table->dropColumn([
                'leave_type', 'delegate_status', 'delegate_decline_reason',
                'delegate_requested_at', 'delegate_responded_at', 'delegate_reminded_at',
                'delegate_escalated_at', 'leave_number', 'status', 'reject_reason',
                'workflow_status', 'running_number', 'numbered_at',
                'supervisor_signature', 'supervisor_approved_at',
                'palad_signature', 'palad_approved_at', 'palad_status', 'palad_at',
                'nayok_signature', 'nayok_approved_at', 'nayok_status', 'nayok_at',
                'inspector_status', 'inspector_signature', 'inspector_at',
                'head_status', 'head_signature', 'head_at',
            ]);

            $table->index(['leave_type_id', 'start_date'], 'leave_requests_type_start_index');
            $table->index('deleted_at', 'leave_requests_deleted_at_index');
        });

        $this->addOperationalIndexes();
        $this->addStatusChecks();
    }

    private function rebuildCanonicalLeavesForSqlite(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::create('leave_requests_canonical', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('leave_type_id')->constrained()->restrictOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('total_days', 8, 2);
            $table->text('reason');
            $table->string('contact_info')->nullable();
            $table->foreignId('delegate_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['user_id', 'start_date'], 'leave_requests_user_start_index');
            $table->index(['leave_type_id', 'start_date'], 'leave_requests_type_start_index');
            $table->index('deleted_at', 'leave_requests_deleted_at_index');
        });
        DB::statement('INSERT INTO leave_requests_canonical
            (id, user_id, leave_type_id, start_date, end_date, total_days, reason, contact_info,
             delegate_user_id, created_at, updated_at, deleted_at)
            SELECT id, user_id, leave_type_id, start_date, end_date, total_days, reason, contact_info,
                   delegate_user_id, created_at, updated_at, deleted_at FROM leave_requests');
        Schema::drop('leave_requests');
        Schema::rename('leave_requests_canonical', 'leave_requests');
        Schema::enableForeignKeyConstraints();
    }

    private function addOperationalIndexes(): void
    {
        Schema::table('documents', fn (Blueprint $table) =>
            $table->index('deleted_at', 'documents_deleted_at_index'));
        Schema::table('workflow_instances', fn (Blueprint $table) =>
            $table->index(['status', 'current_step', 'leave_request_id'], 'workflow_leave_queue_index'));
        Schema::table('workflow_action_evidence', fn (Blueprint $table) =>
            $table->index(['action', 'acted_at'], 'workflow_evidence_action_date_index'));
    }

    private function assertCanonicalLeaveDataIsReady(): void
    {
        $missingTypes = DB::table('leave_requests as leaves')
            ->leftJoin('leave_types as types', 'types.id', '=', 'leaves.leave_type_id')
            ->whereNull('types.id')->count();
        $missingWorkflows = DB::table('leave_requests as leaves')
            ->leftJoin('workflow_instances as workflows', 'workflows.leave_request_id', '=', 'leaves.id')
            ->whereNull('workflows.id')->count();
        $delegateMismatches = DB::table('leave_requests')
            ->where(fn ($query) => $query
                ->whereRaw('(delegate_id IS NULL) <> (delegate_user_id IS NULL)')
                ->orWhereColumn('delegate_id', '!=', 'delegate_user_id'))
            ->count();

        if ($missingTypes || $missingWorkflows || $delegateMismatches) {
            throw new \RuntimeException(
                "Canonical leave migration blocked: missing_types={$missingTypes}, "
                ."missing_workflows={$missingWorkflows}, delegate_mismatches={$delegateMismatches}. "
                .'Run edoc:v2-repair-leave-workflows and resolve duplicate fields first.'
            );
        }
    }

    private function addStatusChecks(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        foreach ($this->statusChecks as $table => $values) {
            $invalid = DB::table($table)->whereNotIn('status', $values)->count();
            if ($invalid) {
                throw new \RuntimeException("Cannot constrain {$table}.status: {$invalid} invalid row(s).");
            }
            $constraint = $table.'_status_check';
            $allowed = implode(', ', array_map(fn ($value) => DB::getPdo()->quote($value), $values));
            DB::statement("ALTER TABLE `{$table}` ADD CONSTRAINT `{$constraint}` CHECK (`status` IN ({$allowed}))");
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            foreach (array_keys($this->statusChecks) as $table) {
                DB::statement("ALTER TABLE `{$table}` DROP CONSTRAINT `{$table}_status_check`");
            }
        }

        Schema::table('workflow_action_evidence', fn (Blueprint $table) =>
            $table->dropIndex('workflow_evidence_action_date_index'));
        Schema::table('workflow_instances', fn (Blueprint $table) =>
            $table->dropIndex('workflow_leave_queue_index'));
        Schema::table('documents', fn (Blueprint $table) =>
            $table->dropIndex('documents_deleted_at_index'));

        Schema::table('leave_requests', function (Blueprint $table) {
            $table->dropIndex('leave_requests_type_start_index');
            $table->dropIndex('leave_requests_deleted_at_index');
            $table->string('leave_type')->nullable()->after('leave_type_id');
            $table->foreignId('delegate_id')->nullable()->after('delegate_user_id')->constrained('users')->nullOnDelete();
            $table->string('delegate_status', 30)->nullable();
            $table->text('delegate_decline_reason')->nullable();
            $table->timestamp('delegate_requested_at')->nullable();
            $table->timestamp('delegate_responded_at')->nullable();
            $table->timestamp('delegate_reminded_at')->nullable();
            $table->timestamp('delegate_escalated_at')->nullable();
            $table->string('leave_number', 100)->nullable()->unique();
            $table->string('status', 30)->default('PENDING');
            $table->text('reject_reason')->nullable();
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
    }
};
