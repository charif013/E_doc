<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $instances = DB::table('workflow_instances')
            ->join('workflow_definitions', 'workflow_definitions.id', '=', 'workflow_instances.workflow_definition_id')
            ->where('workflow_definitions.code', 'APPLICATION_LEAVE')
            ->where('workflow_instances.current_step', 4)
            ->select('workflow_instances.id')
            ->get();

        foreach ($instances as $instance) {
            DB::transaction(function () use ($instance) {
                DB::table('workflow_steps')
                    ->where('workflow_instance_id', $instance->id)
                    ->where('step_order', 4)
                    ->where('status', 'PENDING')
                    ->update([
                        'status' => 'SKIPPED',
                        'comment' => 'ยกเลิกขั้นตอนลงเลขใบลา',
                        'updated_at' => now(),
                    ]);

                $next = DB::table('workflow_steps')
                    ->where('workflow_instance_id', $instance->id)
                    ->where('step_order', '>', 4)
                    ->where('status', 'PENDING')
                    ->orderBy('step_order')
                    ->first();

                DB::table('workflow_instances')->where('id', $instance->id)->update($next ? [
                    'status' => 'IN_PROGRESS',
                    'current_step' => $next->step_order,
                    'updated_at' => now(),
                ] : [
                    'status' => 'APPROVED',
                    'current_step' => null,
                    'completed_at' => now(),
                    'updated_at' => now(),
                ]);
            });
        }
    }

    public function down(): void
    {
        // ไม่ย้อนสถานะ เพราะรายการอาจถูกอนุมัติต่อหลังจากข้ามขั้นตอนลงเลขแล้ว
    }
};
