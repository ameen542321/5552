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

        // إضافة store_id
        if (!Schema::hasColumn('employee_logs', 'store_id')) {
            $table->unsignedBigInteger('store_id')->after('id');
        }

        // إضافة added_by
        if (!Schema::hasColumn('employee_logs', 'added_by')) {
            $table->unsignedBigInteger('added_by')->nullable()->after('description');
        }

        // إضافة deleted_at
        if (!Schema::hasColumn('employee_logs', 'deleted_at')) {
            $table->softDeletes();
        }
    });
}

public function down()
{
    Schema::table('employee_logs', function (Blueprint $table) {
        $table->dropColumn(['store_id', 'added_by', 'deleted_at']);
    });
}

};
