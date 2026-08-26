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
   Schema::create('sale_items', function (Blueprint $table) {
    $table->id();

    // ربط العنصر بالبيع
    $table->unsignedBigInteger('sale_id');
    $table->foreign('sale_id')->references('id')->on('sales')->cascadeOnDelete();

    // المنتج
    $table->unsignedBigInteger('product_id');

    // الكمية
    $table->integer('quantity');

    $table->timestamps();
});

}


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sale_items');
    }
};
