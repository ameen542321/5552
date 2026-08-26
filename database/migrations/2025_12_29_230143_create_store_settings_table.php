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
    Schema::create('store_settings', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('store_id');
        $table->unsignedBigInteger('user_id');
        $table->string('key');
        $table->string('value')->nullable();
        $table->timestamps();
    });
}

public function down()
{
    Schema::dropIfExists('store_settings');
}

};
