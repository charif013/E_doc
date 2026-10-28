<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_assignment_progress_updates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('document_assignment_id');
            $table->unsignedBigInteger('updated_by');
            $table->string('status', 30);
            $table->unsignedTinyInteger('progress_percent')->nullable();
            $table->text('note')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['document_assignment_id', 'created_at'], 'dap_updates_assignment_created_idx');
            $table->foreign('document_assignment_id', 'dap_updates_assignment_fk')
                ->references('id')->on('document_assignments')->cascadeOnDelete();
            $table->foreign('updated_by', 'dap_updates_user_fk')
                ->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_assignment_progress_updates');
    }
};
