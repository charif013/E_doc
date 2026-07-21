<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            
            // 🌟 เพิ่ม 2 ช่องนี้เข้าไปใหม่ สำหรับเก็บประเภทและไฟล์แนบ
            $table->string('doc_type')->default('internal'); 
            $table->string('attachment_path')->nullable();

            $table->string('doc_number')->nullable(); // เลขที่หนังสือ
            $table->date('doc_date')->nullable();     // วันที่
            $table->string('title');                  // เรื่อง
            $table->text('content')->nullable();      // เนื้อหา
            
            // 🌟 แก้ default เป็น DRAFT ให้ตรงกับระบบ Controller ที่เราเขียนไว้
            $table->string('status')->default('DRAFT'); 
            
            // 1. คนสร้างเอกสาร (เจ้าพนักงานธุรการ)
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade'); 
            $table->longText('creator_signature')->nullable();

            // 2. ด่านที่ 1: หัวหน้าสำนักปลัด
            $table->foreignId('supervisor_id')->nullable()->constrained('users')->onDelete('set null');
            $table->longText('supervisor_signature')->nullable();
            $table->timestamp('supervisor_approved_at')->nullable();

            // 3. ด่านที่ 2: ปลัด อบต.
            $table->foreignId('palad_id')->nullable()->constrained('users')->onDelete('set null');
            $table->longText('palad_signature')->nullable();
            $table->timestamp('palad_approved_at')->nullable();

            // 4. ด่านที่ 3: นายก อบต.
            $table->foreignId('nayok_id')->nullable()->constrained('users')->onDelete('set null');
            $table->longText('nayok_signature')->nullable();
            $table->timestamp('nayok_approved_at')->nullable();

            $table->text('reject_reason')->nullable(); // เหตุผลตีกลับ

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('documents');
    }
};