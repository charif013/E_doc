<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_assignment_progress_updates', function (Blueprint $table) {
            $table->string('attachment_path', 500)->nullable()->after('note');
            $table->string('attachment_original_name')->nullable()->after('attachment_path');
            $table->string('attachment_mime_type', 150)->nullable()->after('attachment_original_name');
            $table->unsignedBigInteger('attachment_size')->nullable()->after('attachment_mime_type');
            $table->char('attachment_sha256', 64)->nullable()->after('attachment_size');
        });
    }

    public function down(): void
    {
        Schema::table('document_assignment_progress_updates', function (Blueprint $table) {
            $table->dropColumn([
                'attachment_path', 'attachment_original_name', 'attachment_mime_type',
                'attachment_size', 'attachment_sha256',
            ]);
        });
    }
};
