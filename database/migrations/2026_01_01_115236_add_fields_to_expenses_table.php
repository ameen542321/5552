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
    Schema::table('expenses', function (Blueprint $table) {

        // نوع المصروف
        $table->string('type')->nullable()->after('user_id');

        // الموظف داخل المتجر (اختياري)
        $table->unsignedBigInteger('employee_id')->nullable()->after('type');

        // نوع الشخص الذي سجّل المصروف (user/accountant)
        $table->string('actor_type')->nullable()->after('employee_id');

        // الحذف المؤقت
        $table->softDeletes();

        // علاقة الموظف
        $table->foreign('employee_id')->references('id')->on('employees')->nullOnDelete();
    });
}

public function down()
{
    Schema::table('expenses', function (Blueprint $table) {
        $table->dropForeign(['employee_id']);
        $table->dropColumn(['type', 'employee_id', 'actor_type', 'deleted_at']);
    });
}

};
