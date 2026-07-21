<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('pin')->nullable()->after('password'); // เก็บ PIN เข้ารหัส
            
            $table->longText('signature')->nullable()->after('pin'); // เก็บรูปลายเซ็น (Base64)
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['pin', 'signature']);
        });
    }
};