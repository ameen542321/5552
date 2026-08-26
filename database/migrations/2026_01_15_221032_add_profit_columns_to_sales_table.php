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
    Schema::table('sales', function (Blueprint $table) {
        $table->decimal('products_total', 10, 2)->default(0);
        $table->decimal('labor_total', 10, 2)->default(0);
        $table->decimal('final_total', 10, 2)->default(0);
        $table->decimal('profit', 10, 2)->default(0);
    });
}

public function down()
{
    Schema::table('sales', function (Blueprint $table) {
        $table->dropColumn(['products_total', 'labor_total', 'final_total', 'profit']);
    });
}

};
