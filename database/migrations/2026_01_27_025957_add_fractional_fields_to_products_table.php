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
    Schema::table('products', function (Blueprint $table) {
        // تغيير نوع الكمية لتقبل الكسور
        $table->decimal('quantity', 10, 2)->change();

        // إضافة نوع المنتج: عادي أو مجزأ
        $table->enum('product_type', ['standard', 'fractional'])->default('standard')->after('category_id');

        // إضافة حقل نسبة الهالك
        $table->decimal('waste_percentage', 5, 2)->default(0)->after('product_type');
    });
}
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            //
        });
    }
};
