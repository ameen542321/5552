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
    Schema::table('employee_absences', function (Blueprint $table) {

        // إضافة store_id
        if (!Schema::hasColumn('employee_absences', 'store_id')) {
            $table->unsignedBigInteger('store_id')->after('id');
        }

        // إضافة deleted_at
        if (!Schema::hasColumn('employee_absences', 'deleted_at')) {
            $table->softDeletes();
        }
    });
}

public function down()
{
    Schema::table('employee_absences', function (Blueprint $table) {
        $table->dropColumn(['store_id', 'deleted_at']);
    });
}

};
