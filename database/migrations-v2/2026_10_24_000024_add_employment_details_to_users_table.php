<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const GENERAL_EMPLOYEE_POSITIONS = ['พนักงานขับรถยนต์', 'คนงาน', 'ภารโรง'];

    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('employment_type', 30)->default('local_employee')->after('gender')->index();
            $table->date('employment_started_at')->nullable()->after('employment_type');
            $table->date('vacation_eligible_at')->nullable()->after('employment_started_at');
        });

        DB::table('users')
            ->whereIn('position', self::GENERAL_EMPLOYEE_POSITIONS)
            ->update(['employment_type' => 'general_employee']);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['employment_type']);
            $table->dropColumn(['employment_type', 'employment_started_at', 'vacation_eligible_at']);
        });
    }
};
