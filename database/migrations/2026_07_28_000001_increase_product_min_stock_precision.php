<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Keep fractional set thresholds such as one set plus one piece.
     * Existing integer values are preserved exactly by this type widening.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            Schema::table('products', fn (Blueprint $table) => $table->decimal('min_stock', 18, 8)->default(0)->change());
        } else {
            DB::statement('ALTER TABLE products MODIFY COLUMN min_stock DECIMAL(18, 8) NOT NULL DEFAULT 0.00000000');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            Schema::table('products', fn (Blueprint $table) => $table->integer('min_stock')->default(0)->change());
        } else {
            DB::statement('ALTER TABLE products MODIFY COLUMN min_stock INT NOT NULL DEFAULT 0');
        }
    }
};
