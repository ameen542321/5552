<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accountants', function (Blueprint $table) {
            $table->id();

            // المحاسب تابع للمستخدم (مالك المتاجر)
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // المحاسب مرتبط بمتجر واحد فقط
            $table->foreignId('store_id')->nullable()->constrained()->nullOnDelete();

            // بيانات أساسية
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone')->nullable();

            // كلمة المرور لتسجيل الدخول
            $table->string('password');

            // الدور ثابت accountant
            $table->string('role')->default('accountant');

            // حالة الحساب
            $table->enum('status', ['active', 'suspended'])->default('active');
            $table->string('suspension_reason')->nullable();

            // تسجيل الدخول التلقائي
            $table->rememberToken();

            $table->timestamps();
            $table->softDeletes(); // مهم جدًا
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accountants');
    }
};
