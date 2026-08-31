<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->text('external_url')->nullable()->after('attachment_path');
            $table->string('external_attachment_path')->nullable()->after('external_url');
            $table->string('external_original_name')->nullable()->after('external_attachment_path');
            $table->string('external_mime_type', 150)->nullable()->after('external_original_name');
            $table->unsignedBigInteger('external_file_size')->nullable()->after('external_mime_type');
            $table->char('external_sha256', 64)->nullable()->after('external_file_size');
            $table->timestamp('external_downloaded_at')->nullable()->after('external_sha256');
            $table->text('external_download_error')->nullable()->after('external_downloaded_at');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn([
                'external_url',
                'external_attachment_path',
                'external_original_name',
                'external_mime_type',
                'external_file_size',
                'external_sha256',
                'external_downloaded_at',
                'external_download_error',
            ]);
        });
    }
};
