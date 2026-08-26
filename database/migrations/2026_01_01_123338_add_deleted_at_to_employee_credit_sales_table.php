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
    Schema::table('employee_credit_sales', function (Blueprint $table) {
        if (!Schema::hasColumn('employee_credit_sales', 'deleted_at')) {
            $table->softDeletes();
        }
    });
}

public function down()
{
    Schema::table('employee_credit_sales', function (Blueprint $table) {
        $table->dropColumn('deleted_at');
    });
}

};
