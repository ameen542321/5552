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
    Schema::table('employee_salary_reports', function (Blueprint $table) {
        if (!Schema::hasColumn('employee_salary_reports', 'deleted_at')) {
            $table->softDeletes();
        }
    });
}

public function down()
{
    Schema::table('employee_salary_reports', function (Blueprint $table) {
        $table->dropColumn('deleted_at');
    });
}

};
