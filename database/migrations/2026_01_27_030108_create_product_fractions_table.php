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
    Schema::create('product_fractions', function (Blueprint $table) {
        $table->id();
        $table->foreignId('product_id')->constrained()->onDelete('cascade');
        $table->string('option_name'); // مثل: رقم 1 (سيارة كبيرة)
        $table->decimal('deduction_factor', 8, 2); // كم يخصم من الرول (مثل 1.5 أو 0.2)
        $table->decimal('price', 15, 2); // سعر هذا الخيار تحديداً
        $table->timestamps();
    });
}
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_fractions');
    }
};
