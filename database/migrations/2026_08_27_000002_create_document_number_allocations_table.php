<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_number_allocations', function (Blueprint $table) {
            $table->id();
            $table->string('number_type', 30);
            $table->string('scope', 191)->default('organization');
            $table->unsignedSmallInteger('fiscal_year');
            $table->unsignedInteger('running_number')->nullable();
            $table->string('formatted_number')->nullable();
            $table->foreignId('document_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('leave_request_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('allocated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['number_type', 'scope', 'fiscal_year', 'running_number'], 'number_allocations_running_unique');
            $table->unique(['number_type', 'scope', 'fiscal_year', 'formatted_number'], 'number_allocations_formatted_unique');
            $table->unique('document_id');
            $table->unique('leave_request_id');
        });

        // Seed allocations for current data. INSERT IGNORE protects deployments
        // that already contain historical duplicate numbers; new allocations
        // are still guaranteed to be unique from this point forward.
        if (DB::getDriverName() === 'mysql') {
            DB::statement("INSERT IGNORE INTO document_number_allocations
                (number_type, scope, fiscal_year, running_number, formatted_number, document_id, allocated_by, created_at, updated_at)
                SELECT doc_type,
                    CASE WHEN doc_type = 'internal' THEN COALESCE((SELECT department FROM users WHERE users.id = documents.created_by), 'organization') ELSE 'organization' END,
                    CASE WHEN MONTH(created_at) >= 10 THEN YEAR(created_at) + 1 ELSE YEAR(created_at) END,
                    running_number, doc_number, id, created_by, NOW(), NOW()
                FROM documents WHERE running_number IS NOT NULL");

            DB::statement("INSERT IGNORE INTO document_number_allocations
                (number_type, scope, fiscal_year, running_number, formatted_number, leave_request_id, allocated_by, created_at, updated_at)
                SELECT 'leave', 'organization',
                    CASE WHEN MONTH(numbered_at) >= 10 THEN YEAR(numbered_at) + 1 ELSE YEAR(numbered_at) END,
                    running_number, leave_number, id, numbered_by, NOW(), NOW()
                FROM leave_requests WHERE running_number IS NOT NULL AND numbered_at IS NOT NULL");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('document_number_allocations');
    }
};
