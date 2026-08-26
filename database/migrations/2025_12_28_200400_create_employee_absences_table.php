<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_absences', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('employee_id');
            $table->date('date');
            $table->decimal('penalty_amount', 10, 2);

            $table->enum('status', ['pending', 'deducted'])->default('pending');

            $table->string('month');
            $table->string('deducted_month')->nullable();

            $table->unsignedBigInteger('added_by');

            $table->timestamps();

            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_absences');
    }
};
