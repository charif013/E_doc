<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('document_routes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('documents')->onDelete('cascade'); // ผูกกับเอกสาร
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade'); // คนที่ต้องพิจารณา
            $table->integer('step_order'); // ลำดับที่ (1, 2, 3...)
            $table->string('status')->default('pending'); // สถานะ (pending, approved, rejected)
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_routes');
    }
};
