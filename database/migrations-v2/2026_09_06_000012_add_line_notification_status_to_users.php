<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('line_friend_status')->default(false)->after('line_id');
            $table->timestamp('line_connected_at')->nullable()->after('line_friend_status');
            $table->timestamp('line_followed_at')->nullable()->after('line_connected_at');
            $table->index(['line_id', 'line_friend_status']);
        });

        DB::table('users')->whereNotNull('line_id')->where('line_id', '!=', '')->update([
            'line_friend_status' => true,
            'line_connected_at' => now(),
            'line_followed_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['line_id', 'line_friend_status']);
            $table->dropColumn(['line_friend_status', 'line_connected_at', 'line_followed_at']);
        });
    }
};
