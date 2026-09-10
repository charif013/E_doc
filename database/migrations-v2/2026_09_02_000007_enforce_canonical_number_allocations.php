<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CONSTRAINT = 'number_allocations_exactly_one_resource';

    public function up(): void
    {
        if (! Schema::hasTable('number_allocations')) {
            return;
        }

        $invalid = DB::table('number_allocations')
            ->whereRaw('(document_id IS NULL) = (leave_request_id IS NULL)')
            ->count();
        if ($invalid > 0) {
            throw new \RuntimeException("Cannot enforce canonical numbering: {$invalid} allocations have invalid resources.");
        }

        if (DB::getDriverName() === 'mysql' && ! $this->constraintExists()) {
            DB::statement('ALTER TABLE number_allocations ADD CONSTRAINT '.self::CONSTRAINT.' CHECK (
                (document_id IS NOT NULL AND leave_request_id IS NULL)
                OR (document_id IS NULL AND leave_request_id IS NOT NULL)
            )');
            DB::statement("ALTER TABLE document_number_allocations COMMENT = 'DEPRECATED compatibility table; V2 writes use number_sequences and number_allocations'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql' || ! $this->constraintExists()) {
            return;
        }

        $version = (string) (DB::selectOne('SELECT VERSION() AS version')->version ?? '');
        DB::statement(str_contains(strtolower($version), 'mariadb')
            ? 'ALTER TABLE number_allocations DROP CONSTRAINT '.self::CONSTRAINT
            : 'ALTER TABLE number_allocations DROP CHECK '.self::CONSTRAINT);
        DB::statement("ALTER TABLE document_number_allocations COMMENT = ''");
    }

    private function constraintExists(): bool
    {
        return DB::table('information_schema.table_constraints')
            ->where('constraint_schema', DB::getDatabaseName())
            ->where('table_name', 'number_allocations')
            ->where('constraint_name', self::CONSTRAINT)
            ->exists();
    }
};
