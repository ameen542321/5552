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
    Schema::table('invoices', function (Blueprint $table) {
        $table->string('customer_name')->nullable();
        $table->string('customer_phone')->nullable();
        $table->string('vehicle_type')->nullable();
        $table->string('plate_number')->nullable();
        $table->string('tax_number')->nullable();
    });
}

public function down()
{
    Schema::table('invoices', function (Blueprint $table) {
        $table->dropColumn([
            'customer_name',
            'customer_phone',
            'vehicle_type',
            'plate_number',
            'tax_number',
        ]);
    });
}

};
