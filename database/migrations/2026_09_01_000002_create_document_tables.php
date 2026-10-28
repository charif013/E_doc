<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->string('direction', 20);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('document_categories', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('document_priorities', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 100);
            $table->unsignedTinyInteger('level_no')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('document_confidentialities', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 100);
            $table->unsignedTinyInteger('level_no')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('document_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('document_category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('priority_id')->nullable()->constrained('document_priorities')->nullOnDelete();
            $table->foreignId('confidentiality_id')->nullable()->constrained('document_confidentialities')->nullOnDelete();
            $table->string('document_number', 100)->nullable();
            $table->string('receive_number', 100)->nullable();
            $table->date('document_date')->nullable();
            $table->date('receive_date')->nullable();
            $table->string('title', 500);
            $table->longText('content')->nullable();
            $table->string('sender_name')->nullable();
            $table->string('recipient_name')->nullable();
            $table->foreignId('sender_unit_id')->nullable()->constrained('organization_units')->nullOnDelete();
            $table->foreignId('recipient_unit_id')->nullable()->constrained('organization_units')->nullOnDelete();
            $table->string('status', 30)->default('DRAFT');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'created_at']);
            $table->index(['created_by', 'status', 'created_at']);
            $table->index(['document_type_id', 'created_at']);
            $table->index('document_date');
            $table->index('receive_date');
            $table->index('title');
        });

        Schema::create('document_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->string('file_type', 30)->default('ATTACHMENT');
            $table->string('original_name');
            $table->string('file_path', 500)->nullable();
            $table->text('external_url')->nullable();
            $table->string('mime_type', 150)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->char('sha256', 64)->nullable();
            $table->unsignedInteger('version_no')->default(1);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('downloaded_at')->nullable();
            $table->text('download_error')->nullable();
            $table->timestamps();
            $table->index(['document_id', 'file_type']);
            $table->index('sha256');
        });

        Schema::create('document_references', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('referenced_document_id')->constrained('documents')->cascadeOnDelete();
            $table->string('reference_type', 30)->default('RELATED');
            $table->timestamp('created_at')->nullable();
            $table->unique(['document_id', 'referenced_document_id', 'reference_type'], 'document_reference_unique');
            $table->index('referenced_document_id');
        });

        Schema::create('document_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_unit_id')->nullable()->constrained('organization_units')->nullOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('delegated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 30)->default('PENDING');
            $table->text('note')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['assigned_user_id', 'status']);
            $table->index(['assigned_unit_id', 'status']);
            $table->index(['document_id', 'created_at']);
        });

        Schema::create('document_access_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->text('reason')->nullable();
            $table->string('status', 20)->default('PENDING');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->unique(['document_id', 'requested_by']);
            $table->index(['requested_by', 'status']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_access_requests');
        Schema::dropIfExists('document_assignments');
        Schema::dropIfExists('document_references');
        Schema::dropIfExists('document_files');
        Schema::dropIfExists('documents');
        Schema::dropIfExists('document_confidentialities');
        Schema::dropIfExists('document_priorities');
        Schema::dropIfExists('document_categories');
        Schema::dropIfExists('document_types');
    }
};
