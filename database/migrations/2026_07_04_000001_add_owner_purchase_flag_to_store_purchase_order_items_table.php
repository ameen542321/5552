<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_purchase_order_items', function (Blueprint $table) {
            if (! Schema::hasColumn('store_purchase_order_items', 'add_to_owner_purchases')) {
                $table->boolean('add_to_owner_purchases')->default(false)->after('update_product_cost');
            }
        });
    }

    public function down(): void
    {
        Schema::table('store_purchase_order_items', function (Blueprint $table) {
            if (Schema::hasColumn('store_purchase_order_items', 'add_to_owner_purchases')) {
                $table->dropColumn('add_to_owner_purchases');
            }
        });
    }
};
