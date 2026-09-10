<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->string('signer_name')->nullable()->after('recipient_name');
            $table->string('reference_text', 500)->nullable()->after('signer_name');
            $table->text('remark')->nullable()->after('reference_text');
            $table->text('rejection_reason')->nullable()->after('remark');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn(['signer_name', 'reference_text', 'remark', 'rejection_reason']);
        });
    }
};
