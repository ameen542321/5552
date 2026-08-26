<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        // تعديل جدول إعدادات المستخدم [cite: 16]
        Schema::table('user_settings', function (Blueprint $table) {
            $table->dropForeign(['user_id']); // حذف الربط القديم
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        // تعديل جدول السجلات [cite: 10]
        Schema::table('logs', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        // تعديل جدول المتاجر [cite: 15]
        Schema::table('stores', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void {
        // لإعادة الأمور كما كانت في حال أردت التراجع
    }
};
