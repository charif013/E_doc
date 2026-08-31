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
            $table->foreignId('document_id')->nullable()->after('description')->constrained('documents')->nullOnDelete();
        });

        DB::table('rooms')->update(['status' => 'inactive']);
        DB::table('rooms')->updateOrInsert(
            ['name' => 'ห้องประชุมชั้น 2'],
            ['capacity' => 30, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]
        );
    }

    public function down(): void
    {
        Schema::table('room_bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('document_id');
        });
    }
};
