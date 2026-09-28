<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->string('source_type', 20)->default('upload')->after('doc_type');
            $table->text('sender_address')->nullable()->after('content');
            $table->string('closing_text')->nullable()->after('sender_address');
            $table->text('contact_line')->nullable()->after('closing_text');
            $table->text('attachment_description')->nullable()->after('contact_line');
            $table->string('number_status', 20)->default('UNASSIGNED')->after('running_number');
            $table->timestamp('finalized_at')->nullable()->after('number_status');
        });

        Schema::create('document_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->unsignedInteger('version_no');
            $table->json('snapshot');
            $table->string('file_path', 500)->nullable();
            $table->string('change_note', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['document_id', 'version_no']);
        });

        DB::table('documents')->where('doc_type', 'outgoing')->whereNotNull('doc_number')->update([
            'number_status' => 'RESERVED',
        ]);
        DB::table('documents')->where('doc_type', 'outgoing')->whereIn('status', ['APPROVED', 'COMPLETED', 'ARCHIVED'])->update([
            'number_status' => 'CONFIRMED',
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('document_revisions');
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn([
                'source_type', 'sender_address', 'closing_text', 'contact_line',
                'attachment_description', 'number_status', 'finalized_at',
            ]);
        });
    }
};
