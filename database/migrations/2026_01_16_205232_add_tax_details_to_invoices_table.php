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
    Schema::table('invoices', function (Blueprint $table) {
        // إضافة الحقول المالية (إذا لم تكن موجودة)
        $table->integer('subtotal')->after('tax_number')->comment('المبلغ قبل الضريبة');
        $table->integer('tax_amount')->after('subtotal')->comment('قيمة الضريبة 15%');
        $table->integer('total_amount')->after('tax_amount')->comment('الإجمالي شامل الضريبة');

        // إضافة حالة الفاتورة
        $table->string('status')->default('printed')->after('total_amount');
    });
}

public function down()
{
    Schema::table('invoices', function (Blueprint $table) {
        $table->dropColumn(['subtotal', 'tax_amount', 'total_amount', 'status']);
    });
}
};
