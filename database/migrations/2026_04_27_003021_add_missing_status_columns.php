<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            
            // เติมคอลัมน์ของ ปลัด ที่ขาดหายไป
            if (!Schema::hasColumn('leave_requests', 'palad_status')) {
                $table->string('palad_status')->default('pending');
            }
            if (!Schema::hasColumn('leave_requests', 'palad_at')) {
                $table->timestamp('palad_at')->nullable();
            }

            // เติมคอลัมน์ของ นายก ที่ขาดหายไป
            if (!Schema::hasColumn('leave_requests', 'nayok_status')) {
                $table->string('nayok_status')->default('pending');
            }
            if (!Schema::hasColumn('leave_requests', 'nayok_at')) {
                $table->timestamp('nayok_at')->nullable();
            }

        });
    }

    public function down()
    {
        // 
    }
};