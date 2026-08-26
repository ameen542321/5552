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
    Schema::table('sale_items', function (Blueprint $table) {
        // إضافة معرف التجزئة وربطه بجدول التجزئة
        $table->foreignId('fraction_id')->nullable()->constrained('product_fractions')->nullOnDelete();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            //
        });
    }
};
