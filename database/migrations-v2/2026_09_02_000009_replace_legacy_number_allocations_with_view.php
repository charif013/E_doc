<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('document_number_allocations')) {
            return;
        }

        $mismatches = DB::table('document_number_allocations as legacy')
            ->leftJoin('number_allocations as allocations', function ($join) {
                $join->on('allocations.document_id', '=', 'legacy.document_id')
                    ->orOn('allocations.leave_request_id', '=', 'legacy.leave_request_id');
            })
            ->leftJoin('number_sequences as sequences', 'sequences.id', '=', 'allocations.sequence_id')
            ->where(function ($query) {
                $query->whereNull('allocations.id')
                    ->orWhereColumn('legacy.number_type', '!=', 'sequences.number_type')
                    ->orWhereColumn('legacy.scope', '!=', 'sequences.scope')
                    ->orWhereColumn('legacy.fiscal_year', '!=', 'sequences.fiscal_year')
                    ->orWhereColumn('legacy.running_number', '!=', 'allocations.running_number')
                    ->orWhereColumn('legacy.formatted_number', '!=', 'allocations.formatted_number');
            })->count();
        if ($mismatches > 0) {
            throw new \RuntimeException("Cannot replace compatibility numbering table: {$mismatches} rows do not match canonical allocations.");
        }

        Schema::drop('document_number_allocations');
        DB::statement('CREATE VIEW document_number_allocations AS
            SELECT allocations.id,
                   sequences.number_type,
                   sequences.scope,
                   sequences.fiscal_year,
                   allocations.running_number,
                   allocations.formatted_number,
                   allocations.document_id,
                   allocations.leave_request_id,
                   allocations.allocated_by,
                   allocations.allocated_at AS created_at,
                   allocations.allocated_at AS updated_at
            FROM number_allocations allocations
            INNER JOIN number_sequences sequences ON sequences.id = allocations.sequence_id');
    }

    public function down(): void
    {
        $rows = DB::table('document_number_allocations')->get()->map(fn ($row) => (array) $row)->all();
        DB::statement('DROP VIEW IF EXISTS document_number_allocations');
        Schema::create('document_number_allocations', function (Blueprint $table) {
            $table->id();
            $table->string('number_type', 30);
            $table->string('scope', 191)->default('organization');
            $table->unsignedSmallInteger('fiscal_year');
            $table->unsignedInteger('running_number')->nullable();
            $table->string('formatted_number')->nullable();
            $table->foreignId('document_id')->nullable()->unique();
            $table->foreignId('leave_request_id')->nullable()->unique();
            $table->foreignId('allocated_by')->nullable();
            $table->timestamps();
            $table->unique(['number_type', 'scope', 'fiscal_year', 'running_number'], 'compat_number_running_unique');
            $table->unique(['number_type', 'scope', 'fiscal_year', 'formatted_number'], 'compat_number_formatted_unique');
        });
        if ($rows !== []) {
            DB::table('document_number_allocations')->insert($rows);
        }
    }
};
