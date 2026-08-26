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
  Schema::create('sales', function (Blueprint $table) {
    $table->id();

    // المتجر الذي تمت فيه الفاتورة
    $table->unsignedBigInteger('store_id');
    $table->foreign('store_id')
          ->references('id')
          ->on('stores')
          ->cascadeOnDelete();

    // الموظف الذي أنشأ الفاتورة (اختياري)
    $table->unsignedBigInteger('employee_id')->nullable();
    $table->foreign('employee_id')
          ->references('id')
          ->on('employees')
          ->nullOnDelete();

    // إجمالي الفاتورة
    $table->decimal('total', 10, 2);

    // المبلغ المدفوع
    $table->decimal('paid_amount', 10, 2);

    // المتبقي (للبيع الآجل)
    $table->decimal('remaining_amount', 10, 2)->default(0);

    // نوع البيع
    $table->enum('sale_type', ['cash', 'card', 'credit']);

    // هل يوجد فاتورة ورقية
    $table->boolean('has_invoice')->default(false);

    // الوصف الذي طلبته
    $table->string('description')->nullable();

    $table->timestamps();
});



}


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
