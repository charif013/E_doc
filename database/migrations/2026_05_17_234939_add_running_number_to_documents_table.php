<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
{
    Schema::table('documents', function (Blueprint $table) {
        $table->integer('running_number')->nullable()->after('doc_number');
    });
}
    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            // ถ้ายกเลิก (Rollback) ให้ลบคอลัมน์ running_number ทิ้งไป
            $table->dropColumn('running_number');
        });
    }
};
