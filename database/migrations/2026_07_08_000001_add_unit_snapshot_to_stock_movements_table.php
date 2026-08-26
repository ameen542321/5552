<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->decimal('requested_quantity', 15, 4)->nullable()->after('quantity');
            $table->string('unit_type_at_movement', 30)->nullable()->after('requested_quantity');
            $table->string('product_type_at_movement', 30)->nullable()->after('unit_type_at_movement');
            $table->boolean('is_splittable_at_movement')->nullable()->after('product_type_at_movement');
            $table->decimal('items_per_unit_at_movement', 15, 4)->nullable()->after('is_splittable_at_movement');
            $table->decimal('roll_length_value_at_movement', 15, 4)->nullable()->after('items_per_unit_at_movement');
            $table->string('display_unit_label', 30)->nullable()->after('roll_length_value_at_movement');
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropColumn([
                'requested_quantity',
                'unit_type_at_movement',
                'product_type_at_movement',
                'is_splittable_at_movement',
                'items_per_unit_at_movement',
                'roll_length_value_at_movement',
                'display_unit_label',
            ]);
        });
    }
};
