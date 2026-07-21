<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
   public function up()
    {
        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // เชื่อมกับ User ว่าใครเป็นคนลา
            
            // ข้อมูลการลา
            $table->string('leave_type'); // ประเภท: ลาป่วย, ลากิจ, ลาพักผ่อน
            $table->date('start_date');   // วันที่เริ่มลา
            $table->date('end_date');     // วันที่สิ้นสุดการลา
            $table->float('total_days');  // จำนวนวันลา (ใช้ float เผื่อมีการลาครึ่งวัน = 0.5)
            $table->text('reason');       // เหตุผลการลา
            $table->string('contact_info')->nullable(); // ข้อมูลติดต่อระหว่างลา
            
            // สถานะการอนุมัติ (ใช้ 3 ด่านเหมือนระบบเอกสาร)
            $table->string('status')->default('WAITING_SUPERVISOR'); 
            $table->text('reject_reason')->nullable(); // เหตุผลที่ไม่อนุมัติ

            // ด่านที่ 1: หัวหน้าสำนักปลัด (หรือหัวหน้าส่วน)
            $table->foreignId('supervisor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('supervisor_signature')->nullable();
            $table->timestamp('supervisor_approved_at')->nullable();

            // ด่านที่ 2: ปลัด อบต.
            $table->foreignId('palad_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('palad_signature')->nullable();
            $table->timestamp('palad_approved_at')->nullable();

            // ด่านที่ 3: นายก อบต.
            $table->foreignId('nayok_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('nayok_signature')->nullable();
            $table->timestamp('nayok_approved_at')->nullable();

            $table->timestamps();
        });
    }
};
