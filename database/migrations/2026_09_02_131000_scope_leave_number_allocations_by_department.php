<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('document_number_allocations') || ! Schema::hasTable('leave_requests')) {
            return;
        }

        DB::table('document_number_allocations')
            ->where('number_type', 'leave')
            ->whereNotNull('leave_request_id')
            ->orderBy('id')
            ->each(function ($allocation) {
                $department = DB::table('leave_requests')
                    ->join('users', 'users.id', '=', 'leave_requests.user_id')
                    ->where('leave_requests.id', $allocation->leave_request_id)
                    ->value('users.department');

                DB::table('document_number_allocations')
                    ->where('id', $allocation->id)
                    ->update(['scope' => $department ?: 'ไม่ระบุสังกัด']);
            });
    }

    public function down(): void
    {
        if (Schema::hasTable('document_number_allocations')) {
            DB::table('document_number_allocations')
                ->where('number_type', 'leave')
                ->update(['scope' => 'organization']);
        }
    }
};
