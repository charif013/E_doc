<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->text('head_comment')->nullable();
            $table->text('inspector_comment')->nullable();
            $table->text('palad_comment')->nullable();
            $table->text('nayok_comment')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->dropColumn(['head_comment', 'inspector_comment', 'palad_comment', 'nayok_comment']);
        });
    }
};
