<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
{
    Schema::table('documents', function (Blueprint $table) {
        $table->string('reference_doc')->nullable()->after('signer_name');
        $table->string('remark')->nullable()->after('reference_doc');
    });
}

    public function down()
{
    Schema::table('documents', function (Blueprint $table) {
        $table->dropColumn(['reference_doc', 'remark']);
    });
}
};
