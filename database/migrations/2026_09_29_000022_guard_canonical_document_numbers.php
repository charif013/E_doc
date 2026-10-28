<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const TRIGGERS = [
        'documents_require_number_allocation_before_update',
        'number_allocations_protect_confirmed_document_before_update',
        'number_allocations_protect_confirmed_document_before_delete',
    ];

    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $missing = DB::table('documents as documents')
            ->leftJoin('number_allocations as allocations', 'allocations.document_id', '=', 'documents.id')
            ->whereNull('documents.deleted_at')
            ->whereNotNull('documents.document_number')
            ->where(function ($query) {
                $query->where('documents.number_status', 'CONFIRMED')
                    ->orWhere('documents.status', 'COMPLETED');
            })
            ->whereNull('allocations.id')
            ->count();
        if ($missing > 0) {
            throw new RuntimeException("Cannot guard canonical document numbers: {$missing} confirmed documents have no allocation.");
        }

        $this->dropTriggers();

        DB::unprepared(<<<'SQL'
CREATE TRIGGER documents_require_number_allocation_before_update
BEFORE UPDATE ON documents
FOR EACH ROW
BEGIN
    IF NEW.deleted_at IS NULL
       AND NEW.document_number IS NOT NULL
       AND (NEW.number_status = 'CONFIRMED' OR NEW.status = 'COMPLETED')
       AND NOT EXISTS (
           SELECT 1 FROM number_allocations
           WHERE document_id = NEW.id
       ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Confirmed document requires a canonical number allocation';
    END IF;

    IF NEW.deleted_at IS NULL
       AND EXISTS (
           SELECT 1
           FROM number_allocations allocations
           INNER JOIN document_types types ON types.id = NEW.document_type_id
           WHERE allocations.document_id = NEW.id
             AND (
                 (types.code = 'INCOMING' AND NOT (allocations.formatted_number <=> NEW.receive_number))
                 OR (types.code <> 'INCOMING' AND NOT (allocations.formatted_number <=> NEW.document_number))
             )
       ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Document number must match its canonical allocation';
    END IF;
END
SQL);

        DB::unprepared(<<<'SQL'
CREATE TRIGGER number_allocations_protect_confirmed_document_before_update
BEFORE UPDATE ON number_allocations
FOR EACH ROW
BEGIN
    IF OLD.document_id IS NOT NULL
       AND NOT (OLD.document_id <=> NEW.document_id)
       AND EXISTS (
           SELECT 1 FROM documents
           WHERE id = OLD.document_id
             AND deleted_at IS NULL
             AND (number_status = 'CONFIRMED' OR status = 'COMPLETED')
       ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Cannot detach the allocation from a confirmed document';
    END IF;
END
SQL);

        DB::unprepared(<<<'SQL'
CREATE TRIGGER number_allocations_protect_confirmed_document_before_delete
BEFORE DELETE ON number_allocations
FOR EACH ROW
BEGIN
    IF OLD.document_id IS NOT NULL
       AND EXISTS (
           SELECT 1 FROM documents
           WHERE id = OLD.document_id
             AND deleted_at IS NULL
             AND (number_status = 'CONFIRMED' OR status = 'COMPLETED')
       ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Cannot delete the allocation of a confirmed document';
    END IF;
END
SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            $this->dropTriggers();
        }
    }

    private function dropTriggers(): void
    {
        foreach (self::TRIGGERS as $trigger) {
            DB::statement("DROP TRIGGER IF EXISTS {$trigger}");
        }
    }
};
