<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('store_purchase_order_items') && ! Schema::hasColumn('store_purchase_order_items', 'inventory_count_unit')) {
            Schema::table('store_purchase_order_items', function (Blueprint $table): void {
                $table->string('inventory_count_unit', 20)->nullable()->after('inventory_count_quantity');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('store_purchase_order_items') && Schema::hasColumn('store_purchase_order_items', 'inventory_count_unit')) {
            Schema::table('store_purchase_order_items', fn (Blueprint $table) => $table->dropColumn('inventory_count_unit'));
        }
    }
};
