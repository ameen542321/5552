<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
   public function up()
{
    Schema::create('daily_balances', function (Blueprint $table) {
        $table->id();
        $table->foreignId('store_id')->constrained()->onDelete('cascade');
        $table->foreignId('accountant_id')->constrained()->onDelete('cascade');

        $table->decimal('system_sales_total', 15, 2);
        $table->decimal('system_cash_expected', 15, 2);
        $table->decimal('actual_cash_submitted', 15, 2);
        $table->decimal('difference', 15, 2);

        // استخدام nullable لتجنب خطأ Syntax error 1067
        $table->timestamp('start_time')->nullable();
        $table->timestamp('end_time')->nullable();

        $table->text('notes')->nullable();
        $table->timestamps();
    });
}

    public function down()
    {
        Schema::dropIfExists('daily_balances');
    }
};
