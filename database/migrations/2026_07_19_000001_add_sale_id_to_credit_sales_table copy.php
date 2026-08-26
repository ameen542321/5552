<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('credit_sales')) {
            return;
        }

        Schema::table('credit_sales', function (Blueprint $table) {
            if (! Schema::hasColumn('credit_sales', 'sale_id')) {
                $table->unsignedBigInteger('sale_id')->nullable()->after('store_id');
                $table->index('sale_id', 'credit_sales_sale_id_index');
            }
        });

        DB::table('credit_sales')
            ->whereNull('sale_id')
            ->whereNotNull('description')
            ->select('id', 'store_id', 'description')
            ->orderBy('id')
            ->chunkById(200, function ($creditSales): void {
                foreach ($creditSales as $creditSale) {
                    if (preg_match('/#(\d+)/', (string) $creditSale->description, $matches) !== 1) {
                        continue;
                    }

                    $saleId = (int) $matches[1];
                    $saleExists = DB::table('sales')
                        ->where('id', $saleId)
                        ->where('store_id', $creditSale->store_id)
                        ->exists();

                    if (! $saleExists) {
                        continue;
                    }

                    DB::table('credit_sales')
                        ->where('id', $creditSale->id)
                        ->update(['sale_id' => $saleId]);
                }
            });
    }

    public function down(): void
    {
        if (! Schema::hasTable('credit_sales') || ! Schema::hasColumn('credit_sales', 'sale_id')) {
            return;
        }

        Schema::table('credit_sales', function (Blueprint $table) {
            $table->dropIndex('credit_sales_sale_id_index');
            $table->dropColumn('sale_id');
        });
    }
};
