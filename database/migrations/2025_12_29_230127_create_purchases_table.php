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
    Schema::create('purchases', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('store_id');
        $table->unsignedBigInteger('user_id');
        $table->unsignedBigInteger('product_id');
        $table->integer('quantity');
        $table->decimal('cost', 10, 2);
        $table->timestamps();
    });
}

public function down()
{
    Schema::dropIfExists('purchases');
}

};
