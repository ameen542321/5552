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
    Schema::create('invoices', function (Blueprint $table) {
        $table->id();

        $table->unsignedBigInteger('sale_id');
        $table->string('invoice_number')->unique();

        $table->timestamps();

        $table->foreign('sale_id')->references('id')->on('sales')->onDelete('cascade');
    });
}



    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
