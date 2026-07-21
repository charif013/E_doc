<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
{
    Schema::create('booking_user', function (Blueprint $table) {
        $table->id();
        $table->foreignId('room_booking_id')->constrained('room_bookings')->cascadeOnDelete();
        $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
        $table->string('status')->default('pending'); // สถานะการตอบรับ: pending, accepted, declined
        $table->timestamps();
    });
}
};
