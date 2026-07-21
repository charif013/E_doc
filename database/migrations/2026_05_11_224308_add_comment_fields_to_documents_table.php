<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
   public function up()
{
    Schema::table('documents', function (Blueprint $table) {
        $table->text('supervisor_comment')->nullable();
        $table->text('palad_comment')->nullable();
        $table->text('nayok_comment')->nullable();
    });
}

public function down()
{
    Schema::table('documents', function (Blueprint $table) {
        $table->dropColumn(['supervisor_comment', 'palad_comment', 'nayok_comment']);
    });
}
};
