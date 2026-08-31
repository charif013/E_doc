<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->foreignId('assigned_user_id')->nullable()->after('assigned_to')->constrained('users')->nullOnDelete();
            $table->foreignId('delegated_by')->nullable()->after('assigned_user_id')->constrained('users')->nullOnDelete();
            $table->string('assignment_status')->nullable()->after('delegated_by');
            $table->timestamp('assigned_at')->nullable()->after('assignment_status');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assigned_user_id');
            $table->dropConstrainedForeignId('delegated_by');
            $table->dropColumn(['assignment_status', 'assigned_at']);
        });
    }
};
