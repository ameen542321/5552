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
      // داخل الـ Migration
Schema::table('stock_movements', function (Blueprint $table) {
    $table->decimal('meters', 8, 2)->nullable()->after('quantity');
    $table->decimal('roll_length_at_movement', 8, 2)->nullable()->after('meters');
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            //
        });
    }
};
