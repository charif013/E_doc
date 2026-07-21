<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
{
    Schema::table('users', function (Blueprint $table) {
        // เพิ่มช่องเก็บ line_id หลังช่องอีเมล (ให้เป็นค่าว่างได้)
        $table->string('line_id')->nullable()->after('email');
    });
}

public function down()
{
    Schema::table('users', function (Blueprint $table) {
        $table->dropColumn('line_id');
    });
}
};
