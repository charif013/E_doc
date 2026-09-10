<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('room_bookings', function (Blueprint $table) {
            if (! Schema::hasColumn('room_bookings', 'status')) {
                $table->string('status', 30)->default('APPROVED')->after('end_time')->index();
            }
            if (! Schema::hasColumn('room_bookings', 'approved_by')) {
                $table->unsignedBigInteger('approved_by')->nullable()->after('status');
            }
            if (! Schema::hasColumn('room_bookings', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('approved_by');
            }
            if (! Schema::hasColumn('room_bookings', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('approved_at');
            }
        });

        DB::table('room_bookings')->whereNull('status')->update(['status' => 'APPROVED']);
        foreach (['pending' => 'PENDING', 'approved' => 'APPROVED', 'canceled' => 'CANCELED', 'cancelled' => 'CANCELED'] as $old => $new) {
            DB::table('room_bookings')->where('status', $old)->update(['status' => $new]);
        }
    }

    public function down(): void
    {
        // Compatibility columns may already belong to the V2 base schema.
    }
};
