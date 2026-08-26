<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_withdrawals', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('employee_id');
            $table->decimal('amount', 10, 2);
            $table->string('description')->nullable();
            $table->date('date');

            // pending = لم تُخصم
            // deducted = خُصمت في تقرير الراتب
            $table->enum('status', ['pending', 'deducted'])->default('pending');

            $table->string('month');          // شهر الإنشاء
            $table->string('deducted_month')->nullable(); // شهر الخصم

            $table->unsignedBigInteger('added_by'); // المحاسب أو المستخدم

            $table->timestamps();

            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_withdrawals');
    }
};
