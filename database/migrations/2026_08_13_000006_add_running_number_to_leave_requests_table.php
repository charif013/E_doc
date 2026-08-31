<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->unsignedInteger('running_number')->nullable()->after('leave_number');
            $table->index(['numbered_at', 'running_number']);
        });
    }

    public function down(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->dropIndex(['numbered_at', 'running_number']);
            $table->dropColumn('running_number');
        });
    }
};
