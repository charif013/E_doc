<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('leave_requests')
            ->where(function ($query) {
                $query->whereNotNull('delegate_user_id')
                    ->orWhereNotNull('delegate_id');
            })
            ->where('workflow_status', 'pending_delegate')
            ->whereNull('delegate_status')
            ->update(['delegate_status' => 'pending']);
    }

    public function down(): void
    {
        // ไม่ย้อนสถานะ เพราะจะทำให้งานที่ซ่อมแล้วหายจากคิวผู้รับมอบหมาย
    }
};
