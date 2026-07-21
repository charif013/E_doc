<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
        public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            // เพิ่มคอลัมน์เก็บสถานะคำขอ (ค่าเริ่มต้นคือ false)
            $table->boolean('pin_reset_requested')->default(false)->after('pin');
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('pin_reset_requested');
        });
    }
};
