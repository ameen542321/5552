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
    Schema::table('sales', function (Blueprint $table) {
        // إضافة نسبة الضريبة (افتراضياً 0)
        if (!Schema::hasColumn('sales', 'tax_rate')) {
            $table->integer('tax_rate')->default(0)->after('products_total');
        }
        // التأكد من وجود عمود أجور اليد (إذا لم يكن موجوداً)
        if (!Schema::hasColumn('sales', 'labor_total')) {
            $table->decimal('labor_total', 10, 2)->default(0)->after('tax_rate');
        }
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            //
        });
    }
};
