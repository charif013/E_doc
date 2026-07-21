<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            
            // 1. ผู้รับมอบงาน
            if (!Schema::hasColumn('leave_requests', 'delegate_id')) {
                $table->unsignedBigInteger('delegate_id')->nullable()->after('contact_info');
                $table->string('delegate_status')->default('pending');
            }
            
            // 2. ผู้ตรวจสอบ (HR/ธุรการ)
            if (!Schema::hasColumn('leave_requests', 'inspector_id')) {
                $table->unsignedBigInteger('inspector_id')->nullable();
                $table->string('inspector_status')->default('pending'); 
                $table->text('inspector_signature')->nullable();
                $table->timestamp('inspector_at')->nullable();
            }

            // 3. หัวหน้าสำนัก/ผอ.กอง
            if (!Schema::hasColumn('leave_requests', 'head_id')) {
                $table->unsignedBigInteger('head_id')->nullable();
                $table->string('head_status')->default('pending');
                $table->text('head_signature')->nullable();
                $table->timestamp('head_at')->nullable();
            }

            // 4. ปลัด อบต. (เช็คก่อนว่ามีหรือยัง ถ้ามีแล้วจะได้ข้ามไป)
            if (!Schema::hasColumn('leave_requests', 'palad_id')) {
                $table->unsignedBigInteger('palad_id')->nullable();
                $table->string('palad_status')->default('pending');
                $table->text('palad_signature')->nullable();
                $table->timestamp('palad_at')->nullable();
            }

            // 5. นายก อบต. (เช็คก่อนว่ามีหรือยัง)
            if (!Schema::hasColumn('leave_requests', 'nayok_id')) {
                $table->unsignedBigInteger('nayok_id')->nullable();
                $table->string('nayok_status')->default('pending');
                $table->text('nayok_signature')->nullable();
                $table->timestamp('nayok_at')->nullable();
            }
            
            // สถานะรวมของใบลา
            if (!Schema::hasColumn('leave_requests', 'workflow_status')) {
                $table->string('workflow_status')->default('pending_delegate')->after('status');
            }
        });
    }

    public function down()
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $columns = [
                'delegate_id', 'delegate_status',
                'inspector_id', 'inspector_status', 'inspector_signature', 'inspector_at',
                'head_id', 'head_status', 'head_signature', 'head_at',
                'workflow_status'
            ];
            foreach ($columns as $col) {
                if (Schema::hasColumn('leave_requests', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};