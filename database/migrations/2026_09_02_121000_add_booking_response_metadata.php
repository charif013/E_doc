<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['room_booking_user', 'room_booking_participants'] as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            if (! Schema::hasColumn($tableName, 'participant_role')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->string('participant_role', 20)->default('ATTENDEE');
                });
            }

            if (! Schema::hasColumn($tableName, 'responded_at')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->timestamp('responded_at')->nullable();
                });
            }

            if (Schema::hasColumn($tableName, 'status')) {
                DB::table($tableName)
                    ->whereNotNull('status')
                    ->select(['id', 'status'])
                    ->orderBy('id')
                    ->eachById(function ($participant) use ($tableName) {
                        DB::table($tableName)->where('id', $participant->id)->update([
                            'status' => strtoupper((string) $participant->status),
                        ]);
                    });
            }
        }
    }

    public function down(): void
    {
        // Compatibility columns also exist in the V2 schema, so keep them when rolling back.
    }
};
