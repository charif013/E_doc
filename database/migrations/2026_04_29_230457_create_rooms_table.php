<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
   public function up()
{
    Schema::create('rooms', function (Blueprint $table) {
        $table->id();
        $table->string('name'); // ชื่อห้อง
        $table->integer('capacity')->nullable(); // ความจุคน
        $table->string('status')->default('active'); // สถานะห้อง (เปิดใช้งาน/ปิดปรับปรุง)
        $table->timestamps();
    });
}
};
