<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_salary_reports', function (Blueprint $table) {
            $table->id();

            // الربط مع العامل
            $table->unsignedBigInteger('employee_id');

            // الربط مع المتجر
            $table->unsignedBigInteger('store_id');

            // المستخدم الذي أنشأ التقرير
            $table->unsignedBigInteger('user_id');

            // الشهر والسنة
            $table->string('month');
            $table->string('year');

            // الراتب الأساسي
            $table->decimal('base_salary', 10, 2)->default(0);

            // مجموع البنود
            $table->decimal('total_withdrawals', 10, 2)->default(0);
            $table->decimal('total_absences', 10, 2)->default(0);
            $table->decimal('total_normal_debts', 10, 2)->default(0);
            $table->decimal('total_credit_sales', 10, 2)->default(0);
            $table->decimal('previous_debts', 10, 2)->default(0);

            // الإكرامية والخصم الإضافي
            $table->decimal('bonus', 10, 2)->default(0);
            $table->decimal('extra_deduction', 10, 2)->default(0);

            // الراتب النهائي
            $table->decimal('final_salary', 10, 2)->default(0);

            // ملاحظات
            $table->text('notes')->nullable();

            $table->timestamps();

            // العلاقات
            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
            $table->foreign('store_id')->references('id')->on('stores')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_salary_reports');
    }
};
