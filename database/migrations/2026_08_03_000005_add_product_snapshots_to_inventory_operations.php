<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->string('product_name_snapshot')->nullable()->after('product_id');
            $table->decimal('sale_price_snapshot', 12, 2)->nullable()->after('product_name_snapshot');
            $table->decimal('cost_price_snapshot', 12, 2)->nullable()->after('sale_price_snapshot');
        });
        Schema::table('purchases', function (Blueprint $table) {
            $table->string('product_name_snapshot')->nullable()->after('product_id');
        });
        Schema::table('store_transfer_items', function (Blueprint $table) {
            $table->string('product_name_snapshot')->nullable()->after('receiver_product_id');
            $table->string('unit_label_snapshot', 50)->nullable()->after('unit_type');
            $table->string('product_type_snapshot', 50)->nullable()->after('unit_label_snapshot');
            $table->boolean('is_splittable_snapshot')->nullable()->after('product_type_snapshot');
            $table->decimal('items_per_unit_snapshot', 12, 4)->nullable()->after('is_splittable_snapshot');
            $table->decimal('roll_length_snapshot', 12, 4)->nullable()->after('items_per_unit_snapshot');
        });

        $this->backfill('stock_movements');
        $this->backfill('purchases');
        $this->backfill('store_transfer_items', 'sender_product_id');
    }

    private function backfill(string $table, string $productColumn = 'product_id'): void
    {
        DB::table($table)->whereNull('product_name_snapshot')->orderBy('id')->chunkById(500, function ($rows) use ($table, $productColumn): void {
            $products = DB::table('products')->whereIn('id', $rows->pluck($productColumn)->filter()->unique())->get()->keyBy('id');
            foreach ($rows as $row) {
                $product = $products->get($row->{$productColumn});
                if (! $product) {
                    continue;
                }
                $values = ['product_name_snapshot' => $product->name];
                if ($table === 'stock_movements') {
                    $values += ['sale_price_snapshot' => $product->price, 'cost_price_snapshot' => $product->cost_price];
                } elseif ($table === 'store_transfer_items') {
                    $unitType = (string) ($row->unit_type ?? 'unit');
                    $values += [
                        'unit_label_snapshot' => $this->unitLabel($product, $unitType),
                        'product_type_snapshot' => $product->product_type,
                        'is_splittable_snapshot' => (bool) $product->is_splittable,
                        'items_per_unit_snapshot' => $product->items_per_unit,
                        'roll_length_snapshot' => $product->roll_length,
                    ];
                }
                DB::table($table)->where('id', $row->id)->update($values);
            }
        });
    }

    private function unitLabel(object $product, string $unitType): string
    {
        if ($product->product_type === 'fractional') {
            return in_array($unitType, ['meter', 'meters', 'custom'], true) ? 'متر' : 'رول';
        }

        if ((bool) $product->is_splittable && (float) $product->items_per_unit > 1) {
            return $unitType === 'piece' ? 'حبة' : 'طقم';
        }
        return 'حبة';
    }

    public function down(): void
    {
        Schema::table('store_transfer_items', fn (Blueprint $table) => $table->dropColumn([
            'product_name_snapshot', 'unit_label_snapshot', 'product_type_snapshot',
            'is_splittable_snapshot', 'items_per_unit_snapshot', 'roll_length_snapshot',
        ]));
        Schema::table('purchases', fn (Blueprint $table) => $table->dropColumn('product_name_snapshot'));
        Schema::table('stock_movements', fn (Blueprint $table) => $table->dropColumn([
            'product_name_snapshot', 'sale_price_snapshot', 'cost_price_snapshot',
        ]));
    }
};
