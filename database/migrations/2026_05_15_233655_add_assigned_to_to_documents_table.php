<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
{
    Schema::table('documents', function (Blueprint $table) {
        // 🌟 เพิ่มคอลัมน์ assigned_to โดยตั้งค่าให้เป็น Nullable (เผื่อเอกสารบางประเภทไม่ต้องระบุกอง)
        $table->string('assigned_to')->nullable()->after('doc_secret');
    });
}

public function down(): void
{
    Schema::table('documents', function (Blueprint $table) {
        $table->dropColumn('assigned_to');
    });
}
};
