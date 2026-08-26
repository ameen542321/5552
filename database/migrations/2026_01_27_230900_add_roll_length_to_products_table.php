<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // إضافة طول الرول بعد نوع المنتج
            // decimal(8,2) يسمح بأرقام مثل 999999.99
            $table->decimal('roll_length', 8, 2)
                  ->default(30.00)
                  ->after('product_type')
                  ->comment('طول الرول الكامل بالأمتار (للمنتجات من نوع fractional)');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('roll_length');
        });
    }
};