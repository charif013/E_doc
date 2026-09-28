<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->longText('applicant_signature')->nullable();
            $table->timestamp('submitted_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->dropIndex(['submitted_at']);
            $table->dropColumn(['applicant_signature', 'submitted_at']);
        });
    }
};
