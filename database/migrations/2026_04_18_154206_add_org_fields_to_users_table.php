<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    
    public function up()
{
    Schema::table('users', function (Blueprint $table) {
        $table->string('department')->nullable()->after('email'); // สำนัก
        $table->string('division')->nullable()->after('department'); // ฝ่าย
        $table->string('work_unit')->nullable()->after('division'); // งาน
        $table->string('position')->nullable()->after('work_unit'); // ตำแหน่ง
    });
}

public function down()
{
    Schema::table('users', function (Blueprint $table) {
        $table->dropColumn(['department', 'division', 'work_unit', 'position']);
    });
}
};
