<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rooms', fn (Blueprint $table) => $table->softDeletes());
        Schema::table('room_bookings', function (Blueprint $table) {
            $table->string('room_name_snapshot')->nullable()->after('room_id');
        });
        DB::table('room_bookings')->orderBy('id')->chunkById(200, function ($bookings) {
            foreach ($bookings as $booking) {
                $name = DB::table('rooms')->where('id', $booking->room_id)->value('name');
                DB::table('room_bookings')->where('id', $booking->id)->update(['room_name_snapshot' => $name]);
            }
        });

        if (! Schema::hasColumn('room_booking_user', 'status')) {
            Schema::table('room_booking_user', function (Blueprint $table) {
                $table->string('status')->default('pending')->after('user_id');
            });
        }

        if (Schema::hasTable('booking_user')) {
            DB::table('booking_user')->orderBy('id')->chunkById(200, function ($rows) {
                foreach ($rows as $row) {
                    DB::table('room_booking_user')->updateOrInsert(
                        ['room_booking_id' => $row->room_booking_id, 'user_id' => $row->user_id],
                        [
                            'status' => $row->status ?? 'pending',
                            'created_at' => $row->created_at ?? now(),
                            'updated_at' => $row->updated_at ?? now(),
                        ]
                    );
                }
            });
            Schema::drop('booking_user');
        }

        $duplicates = DB::table('room_booking_user')
            ->select('room_booking_id', 'user_id', DB::raw('MIN(id) as keep_id'))
            ->groupBy('room_booking_id', 'user_id')->havingRaw('COUNT(*) > 1')->get();
        foreach ($duplicates as $duplicate) {
            DB::table('room_booking_user')
                ->where('room_booking_id', $duplicate->room_booking_id)
                ->where('user_id', $duplicate->user_id)
                ->where('id', '<>', $duplicate->keep_id)->delete();
        }

        Schema::table('room_booking_user', function (Blueprint $table) {
            $table->unique(['room_booking_id', 'user_id'], 'room_booking_user_booking_user_unique');
        });
    }

    public function down(): void
    {
        Schema::table('room_booking_user', function (Blueprint $table) {
            $table->dropUnique('room_booking_user_booking_user_unique');
            $table->dropColumn('status');
        });
        Schema::table('rooms', fn (Blueprint $table) => $table->dropSoftDeletes());
        Schema::table('room_bookings', fn (Blueprint $table) => $table->dropColumn('room_name_snapshot'));
    }
};
