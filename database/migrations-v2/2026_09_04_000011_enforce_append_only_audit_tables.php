<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared('DROP TRIGGER IF EXISTS audit_logs_prevent_update');
        DB::unprepared('DROP TRIGGER IF EXISTS audit_logs_prevent_delete');
        DB::unprepared('DROP TRIGGER IF EXISTS workflow_evidence_prevent_update');
        DB::unprepared('DROP TRIGGER IF EXISTS workflow_evidence_prevent_delete');

        DB::unprepared("CREATE TRIGGER audit_logs_prevent_update BEFORE UPDATE ON audit_logs FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'audit_logs is append-only'");
        DB::unprepared("CREATE TRIGGER audit_logs_prevent_delete BEFORE DELETE ON audit_logs FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'audit_logs is append-only'");
        DB::unprepared("CREATE TRIGGER workflow_evidence_prevent_update BEFORE UPDATE ON workflow_action_evidence FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'workflow_action_evidence is append-only'");
        DB::unprepared("CREATE TRIGGER workflow_evidence_prevent_delete BEFORE DELETE ON workflow_action_evidence FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'workflow_action_evidence is append-only'");
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared('DROP TRIGGER IF EXISTS audit_logs_prevent_update');
        DB::unprepared('DROP TRIGGER IF EXISTS audit_logs_prevent_delete');
        DB::unprepared('DROP TRIGGER IF EXISTS workflow_evidence_prevent_update');
        DB::unprepared('DROP TRIGGER IF EXISTS workflow_evidence_prevent_delete');
    }
};
