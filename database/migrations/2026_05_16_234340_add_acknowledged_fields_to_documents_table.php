<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
public function up(): void
{
    Schema::table('documents', function (Blueprint $table) {
        // เพิ่ม 2 คอลัมน์นี้เข้าไป
        $table->timestamp('acknowledged_at')->nullable()->after('assigned_to');
        $table->unsignedBigInteger('acknowledged_by')->nullable()->after('acknowledged_at');
    });
}

public function down(): void
{
    Schema::table('documents', function (Blueprint $table) {
        $table->dropColumn(['acknowledged_at', 'acknowledged_by']);
    });
}
};
