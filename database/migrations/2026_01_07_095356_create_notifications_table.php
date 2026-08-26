<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
   public function up(): void
{
    Schema::create('notifications', function (Blueprint $table) {
        $table->id();

        // المرسل
        $table->unsignedBigInteger('sender_id')->nullable();
        $table->enum('sender_type', ['manager', 'store_owner', 'system']);

        // نوع المستهدف
        $table->enum('target_type', ['all', 'users', 'stores', 'mixed', 'user', 'store']);

        // قائمة المستهدفين (إن وجدت)
        $table->json('target_ids')->nullable();

        // محتوى الإشعار
        $table->string('title');
        $table->text('message');

        // مفتاح القالب الثابت (إن وجد)
        $table->string('template_key')->nullable();

        // قناة الإرسال
        $table->enum('channel', ['site', 'push', 'both'])->default('site');

        // من قرأ الإشعار (IDs)
        $table->json('read_by')->nullable();

        $table->timestamps();
    });
}

public function down(): void
{
    Schema::dropIfExists('notifications');
}

};
