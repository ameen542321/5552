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
    Schema::create('user_settings', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->constrained()->onDelete('cascade'); // ربط الإعدادات بالمستخدم

        // إعدادات مدة الحذف (بالأيام)
        $table->integer('notifications_expiry')->default(15); // الافتراضي 15 يوم
        $table->integer('invoices_expiry')->default(30);      // الافتراضي شهر (30 يوم)
       
        // يمكنك إضافة إعدادات أخرى مستقبلاً هنا
        $table->boolean('email_notifications')->default(true);

        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_settings');
    }
};
