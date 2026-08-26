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

        // إعادة تسمية employee_id → loggable_id
        if (Schema::hasColumn('employee_logs', 'employee_id')) {
            $table->renameColumn('employee_id', 'loggable_id');
        }

        // إضافة loggable_type إذا لم يكن موجودًا
        if (!Schema::hasColumn('employee_logs', 'loggable_type')) {
            $table->string('loggable_type')->after('loggable_id');
        }
    });
}

public function down()
{
    Schema::table('employee_logs', function (Blueprint $table) {

        // التراجع
        if (Schema::hasColumn('employee_logs', 'loggable_id')) {
            $table->renameColumn('loggable_id', 'employee_id');
        }

        if (Schema::hasColumn('employee_logs', 'loggable_type')) {
            $table->dropColumn('loggable_type');
        }
    });
}

};
