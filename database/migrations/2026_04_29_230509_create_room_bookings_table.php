<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
{
    Schema::create('room_bookings', function (Blueprint $table) {
        $table->id();
        $table->string('title'); // หัวข้อการจอง/ประชุม
        $table->enum('booking_type', ['meeting', 'general_use', 'maintenance'])->default('meeting'); // ประเภทการจอง
        $table->text('description')->nullable(); // รายละเอียด
        $table->foreignId('room_id')->constrained('rooms')->cascadeOnDelete(); // เชื่อมกับตารางห้อง
        $table->foreignId('created_by')->constrained('users')->cascadeOnDelete(); // คนจอง
        $table->dateTime('start_time'); // เวลาเริ่ม
        $table->dateTime('end_time'); // เวลาจบ
        $table->timestamps();
    });
}
};
