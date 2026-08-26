<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->decimal('quantity', 18, 6)->default(0)->change();
        });

        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->decimal('quantity', 18, 6)->change();
            $table->decimal('meters', 18, 6)->nullable()->change();
            $table->decimal('roll_length_at_movement', 18, 6)->nullable()->change();
        });

        // تصحيح كسور الأطقم القديمة التي قُرّبت سابقًا إلى منزلتين مثل 3 ÷ 24 = 0.13.
        if (Schema::hasColumn('products', 'is_splittable') && Schema::hasColumn('products', 'items_per_unit')) {
            DB::table('products')
                ->where('is_splittable', true)
                ->where('items_per_unit', '>', 1)
                ->update([
                    'quantity' => DB::raw('ROUND(quantity * items_per_unit) / items_per_unit'),
                ]);
        }

        DB::table('stock_movements')
            ->where('is_splittable_at_movement', true)
            ->where('items_per_unit_at_movement', '>', 1)
            ->update([
                'quantity' => DB::raw('ROUND(quantity * items_per_unit_at_movement) / items_per_unit_at_movement'),
                'meters' => DB::raw('CASE WHEN meters IS NULL THEN NULL ELSE ROUND(meters * items_per_unit_at_movement) / items_per_unit_at_movement END'),
                'roll_length_at_movement' => DB::raw('CASE WHEN roll_length_at_movement IS NULL THEN NULL ELSE ROUND(roll_length_at_movement * items_per_unit_at_movement) / items_per_unit_at_movement END'),
            ]);
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->decimal('quantity', 15, 2)->default(0)->change();
        });

        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->decimal('quantity', 15, 2)->change();
            $table->decimal('meters', 8, 2)->nullable()->change();
            $table->decimal('roll_length_at_movement', 8, 2)->nullable()->change();
        });
    }
};
