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
    Schema::table('employee_debts', function (Blueprint $table) {
        $table->date('date')->nullable()->after('description');
    });
}

public function down()
{
    Schema::table('employee_debts', function (Blueprint $table) {
        $table->dropColumn('date');
    });
}

};
