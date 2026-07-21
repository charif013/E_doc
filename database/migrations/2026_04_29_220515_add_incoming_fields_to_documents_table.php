<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
   public function up()
{
    Schema::table('documents', function (Blueprint $table) {
        $table->string('receive_number')->nullable()->after('doc_number');
        $table->date('receive_date')->nullable()->after('receive_number');
        $table->string('doc_from')->nullable()->after('title');
        $table->string('doc_type_category')->nullable()->after('doc_from');
        $table->string('doc_speed')->nullable()->after('doc_type_category');
        $table->string('doc_secret')->nullable()->after('doc_speed');
    });
}

public function down()
{
    Schema::table('documents', function (Blueprint $table) {
        $table->dropColumn(['receive_number', 'receive_date', 'doc_from', 'doc_type_category', 'doc_speed', 'doc_secret']);
    });
}
};
