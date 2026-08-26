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
    Schema::table('employee_logs', function (Blueprint $table) {
        $table->unsignedBigInteger('person_id')->after('id');
        $table->string('person_type')->after('person_id');
    });
}

public function down()
{
    Schema::table('employee_logs', function (Blueprint $table) {
        $table->dropColumn(['person_id', 'person_type']);
    });
}

};
