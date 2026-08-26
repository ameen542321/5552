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
       Schema::create('stock_movements', function (Blueprint $table) {
    $table->id();

    $table->foreignId('store_id')
        ->constrained()
        ->cascadeOnDelete();

    $table->foreignId('product_id')
        ->constrained()
        ->cascadeOnDelete();

    $table->foreignId('user_id')
        ->nullable()
        ->constrained()
        ->nullOnDelete();

    // نوع الحركة: زيادة أو خصم
    $table->enum('type', ['increase', 'decrease']);

    // الكمية المتحركة
    $table->integer('quantity');

    // ملاحظة اختيارية (سبب الحركة)
    $table->string('note')->nullable();

    $table->timestamps();
});

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
