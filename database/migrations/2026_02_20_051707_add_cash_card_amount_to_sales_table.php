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
        Schema::table('sales', function (Blueprint $table) {
            // إضافة حقول المبالغ النقدية والشبكة
            $table->decimal('cash_amount', 10, 2)
                  ->default(0)
                  ->after('paid_amount')
                  ->comment('المبلغ المدفوع نقداً');

            $table->decimal('card_amount', 10, 2)
                  ->default(0)
                  ->after('cash_amount')
                  ->comment('المبلغ المدفوع بالشبكة');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['cash_amount', 'card_amount']);
        });
    }
};
