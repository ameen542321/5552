<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_logs', function (Blueprint $table) {
            $table->string('product_name_snapshot')->nullable()->after('product_id');
            $table->decimal('quantity_snapshot', 15, 4)->nullable()->after('quantity_change');
        });

        DB::table('inventory_logs')
            ->whereNull('product_name_snapshot')
            ->orderBy('id')
            ->chunkById(500, function ($logs): void {
                $products = DB::table('products')
                    ->whereIn('id', $logs->pluck('product_id')->filter()->unique())
                    ->pluck('name', 'id');

                foreach ($logs as $log) {
                    DB::table('inventory_logs')->where('id', $log->id)->update([
                        'product_name_snapshot' => $products->get($log->product_id),
                        'quantity_snapshot' => $log->quantity_change,
                    ]);
                }
            });

        DB::table('store_purchase_order_items')
            ->whereNull('custom_product_name')
            ->orderBy('id')
            ->chunkById(500, function ($items): void {
                $productIds = $items->pluck('product_id')
                    ->merge($items->pluck('matched_product_id'))
                    ->filter()
                    ->unique();
                $products = DB::table('products')->whereIn('id', $productIds)->pluck('name', 'id');

                foreach ($items as $item) {
                    $productId = $item->product_id ?: $item->matched_product_id;
                    DB::table('store_purchase_order_items')->where('id', $item->id)->update([
                        'custom_product_name' => $products->get($productId),
                    ]);
                }
            });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->unsignedBigInteger('product_id')->nullable()->change();
            $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
        });

        Schema::table('store_transfer_items', function (Blueprint $table) {
            $table->dropForeign(['sender_product_id']);
            $table->unsignedBigInteger('sender_product_id')->nullable()->change();
            $table->foreign('sender_product_id')->references('id')->on('products')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('store_transfer_items', function (Blueprint $table) {
            $table->dropForeign(['sender_product_id']);
            $table->foreign('sender_product_id')->references('id')->on('products')->cascadeOnDelete();
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
        });

        Schema::table('inventory_logs', function (Blueprint $table) {
            $table->dropColumn(['product_name_snapshot', 'quantity_snapshot']);
        });
    }
};
