<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('leave_requests')) {
            return;
        }

        // ใบลาที่ยังไม่ผ่านหัวหน้าต้นสังกัด ต้องเริ่มที่หัวหน้าก่อนงานบุคคล
        DB::table('leave_requests')
            ->where('workflow_status', 'pending_inspector')
            ->where(function ($query) {
                $query->whereNull('head_status')->orWhere('head_status', '!=', 'approved');
            })
            ->update(['workflow_status' => 'pending_head']);

        // ใบลาเดิมที่ตรวจสิทธิ์แล้วแต่ยังไม่มีเลขรับ ให้เข้าคิวธุรการลงเลข
        DB::table('leave_requests')
            ->whereIn('workflow_status', ['pending_palad', 'pending_nayok'])
            ->whereNull('numbered_at')
            ->where('inspector_status', 'approved')
            ->update(['workflow_status' => 'pending_numbering']);

        // ถ้ายังไม่เคยตรวจสิทธิ์ ให้ย้อนมาที่งานบุคคลก่อนธุรการ
        DB::table('leave_requests')
            ->whereIn('workflow_status', ['pending_palad', 'pending_nayok'])
            ->whereNull('numbered_at')
            ->update(['workflow_status' => 'pending_inspector']);
    }

    public function down(): void
    {
        // ไม่ย้อนสถานะ workflow เพราะอาจมีการลงนามจริงหลัง migration แล้ว
    }
};
