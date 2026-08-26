<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();

            // المتجر الذي ينتمي له العامل
            $table->unsignedBigInteger('store_id');

            // بيانات العامل
            $table->string('name');
            $table->string('phone')->nullable();
            $table->decimal('salary', 10, 2)->default(0);

            // المستخدم الذي أضاف العامل
            $table->unsignedBigInteger('added_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // العلاقات
            $table->foreign('store_id')
                  ->references('id')
                  ->on('stores')
                  ->onDelete('cascade');

            $table->foreign('added_by')
                  ->references('id')
                  ->on('users')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
