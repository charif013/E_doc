<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    
    public function up()
{
    Schema::create('holidays', function (Blueprint $table) {
        $table->id();
        $table->date('holiday_date')->unique(); // วันที่หยุด (ห้ามซ้ำ)
        $table->string('name'); // ชื่อวันหยุด
        $table->string('source')->default('admin'); // 'google_api' หรือ 'admin'
        $table->timestamps();
    });
}
};
