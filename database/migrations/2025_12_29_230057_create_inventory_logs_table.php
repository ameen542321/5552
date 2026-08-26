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
    Schema::create('inventory_logs', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('store_id');
        $table->unsignedBigInteger('user_id');
        $table->unsignedBigInteger('product_id');
        $table->integer('quantity_change');
        $table->string('type'); // add, remove, sale, return
        $table->timestamps();
    });
}

public function down()
{
    Schema::dropIfExists('inventory_logs');
}

};
