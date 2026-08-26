<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_credit_sales', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('store_id');

            $table->decimal('amount', 10, 2);
            $table->string('description')->nullable();
            $table->date('date');

            $table->enum('status', ['pending', 'deducted'])->default('pending');

            $table->string('month');
            $table->string('deducted_month')->nullable();

            $table->unsignedBigInteger('added_by');

            $table->timestamps();

            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
            $table->foreign('store_id')->references('id')->on('stores')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_credit_sales');
    }
};
