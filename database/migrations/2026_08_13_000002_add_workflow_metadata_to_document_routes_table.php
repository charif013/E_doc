<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_routes', function (Blueprint $table) {
            $table->text('comment')->nullable()->after('status');
            $table->timestamp('actioned_at')->nullable()->after('comment');
            $table->unique(['document_id', 'step_order'], 'document_routes_document_step_unique');
            $table->index(['user_id', 'status'], 'document_routes_user_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('document_routes', function (Blueprint $table) {
            $table->dropUnique('document_routes_document_step_unique');
            $table->dropIndex('document_routes_user_status_index');
            $table->dropColumn(['comment', 'actioned_at']);
        });
    }
};
