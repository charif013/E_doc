<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->text('delegate_decline_reason')->nullable()->after('delegate_status');
            $table->timestamp('delegate_requested_at')->nullable()->after('delegate_decline_reason');
            $table->timestamp('delegate_responded_at')->nullable()->after('delegate_requested_at');
            $table->timestamp('delegate_reminded_at')->nullable()->after('delegate_responded_at');
            $table->timestamp('delegate_escalated_at')->nullable()->after('delegate_reminded_at');
        });

        DB::table('leave_requests')
            ->whereNotNull('delegate_id')
            ->whereNull('delegate_requested_at')
            ->update(['delegate_requested_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->dropColumn([
                'delegate_decline_reason', 'delegate_requested_at', 'delegate_responded_at',
                'delegate_reminded_at', 'delegate_escalated_at',
            ]);
        });
    }
};
