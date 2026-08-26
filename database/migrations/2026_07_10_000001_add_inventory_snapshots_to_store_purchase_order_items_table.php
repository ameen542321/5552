<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_purchase_order_items', function (Blueprint $table) {
            $table->decimal('stock_quantity_before', 15, 3)->nullable()->after('update_product_cost');
            $table->decimal('stock_quantity_after', 15, 3)->nullable()->after('stock_quantity_before');
            $table->decimal('cost_price_before', 10, 2)->nullable()->after('stock_quantity_after');
            $table->decimal('cost_price_after', 10, 2)->nullable()->after('cost_price_before');
            $table->foreignId('owner_purchase_id')->nullable()->after('cost_price_after')->constrained('purchases')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('store_purchase_order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('owner_purchase_id');
            $table->dropColumn([
                'stock_quantity_before',
                'stock_quantity_after',
                'cost_price_before',
                'cost_price_after',
            ]);
        });
    }
};
