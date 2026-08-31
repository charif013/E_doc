<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->string('leave_number', 100)->nullable()->unique()->after('workflow_status');
            $table->foreignId('numbered_by')->nullable()->after('leave_number')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('numbered_at')->nullable()->after('numbered_by');
        });
    }

    public function down(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->dropForeign(['numbered_by']);
            $table->dropUnique(['leave_number']);
            $table->dropColumn(['leave_number', 'numbered_by', 'numbered_at']);
        });
    }
};
