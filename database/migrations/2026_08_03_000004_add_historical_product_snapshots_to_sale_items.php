<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->string('product_name_snapshot')->nullable()->after('product_id');
            $table->string('unit_label_snapshot', 50)->nullable()->after('unit_type');
            $table->string('product_type_snapshot', 50)->nullable()->after('unit_label_snapshot');
            $table->string('product_usage_snapshot', 50)->nullable()->after('product_type_snapshot');
            $table->boolean('is_splittable_snapshot')->nullable()->after('product_usage_snapshot');
            $table->decimal('items_per_unit_snapshot', 12, 4)->nullable()->after('is_splittable_snapshot');
            $table->decimal('roll_length_snapshot', 12, 4)->nullable()->after('items_per_unit_snapshot');
            $table->decimal('quantity_snapshot', 12, 4)->nullable()->after('roll_length_snapshot');
            $table->decimal('sale_price_snapshot', 12, 2)->nullable()->after('quantity_snapshot');
            $table->decimal('cost_price_snapshot', 12, 2)->nullable()->after('sale_price_snapshot');
            $table->string('snapshot_source', 30)->nullable()->after('cost_price_snapshot');
        });

        DB::table('sale_items')
            ->whereNull('product_name_snapshot')
            ->orderBy('id')
            ->chunkById(500, function ($items): void {
                $products = DB::table('products')
                    ->whereIn('id', $items->pluck('product_id')->filter()->unique())
                    ->get()
                    ->keyBy('id');

                foreach ($items as $item) {
                    $product = $products->get($item->product_id);
                    $unitType = (string) ($item->unit_type ?? 'unit');
                    DB::table('sale_items')->where('id', $item->id)->update([
                        'product_name_snapshot' => $item->custom_name ?: ($product->name ?? null),
                        'unit_label_snapshot' => $this->unitLabel($product, $unitType),
                        'product_type_snapshot' => $product->product_type ?? null,
                        'product_usage_snapshot' => $product->usage_type ?? null,
                        'is_splittable_snapshot' => isset($product) ? (bool) $product->is_splittable : null,
                        'items_per_unit_snapshot' => $product->items_per_unit ?? null,
                        'roll_length_snapshot' => $item->roll_length_at_sale ?? ($product->roll_length ?? null),
                        'quantity_snapshot' => $item->quantity,
                        'sale_price_snapshot' => $item->price ?? null,
                        'cost_price_snapshot' => $item->cost_price ?? ($product->cost_price ?? null),
                        'snapshot_source' => 'legacy_backfill',
                    ]);
                }
            });
    }

    private function unitLabel(?object $product, string $unitType): string
    {
        if (($product->product_type ?? null) === 'fractional') {
            return in_array($unitType, ['meter', 'meters', 'custom'], true) ? 'متر' : 'رول';
        }

        if ((bool) ($product->is_splittable ?? false) && (float) ($product->items_per_unit ?? 0) > 1) {
            return $unitType === 'piece' ? 'حبة' : 'طقم';
        }

        return 'حبة';
    }

    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropColumn([
                'product_name_snapshot', 'unit_label_snapshot', 'product_type_snapshot', 'product_usage_snapshot',
                'is_splittable_snapshot', 'items_per_unit_snapshot', 'roll_length_snapshot',
                'quantity_snapshot', 'sale_price_snapshot', 'cost_price_snapshot', 'snapshot_source',
            ]);
        });
    }
};
