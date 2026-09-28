<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outgoing_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->string('channel', 30);
            $table->string('recipient_name', 500);
            $table->text('recipient_address')->nullable();
            $table->string('tracking_number', 255)->nullable();
            $table->string('status', 20)->default('PENDING');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->string('evidence_path', 500)->nullable();
            $table->text('note')->nullable();
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['document_id', 'status']);
            $table->index(['status', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outgoing_deliveries');
    }
};
