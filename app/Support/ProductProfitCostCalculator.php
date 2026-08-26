<?php

namespace App\Support;

class ProductProfitCostCalculator
{
    /**
     * حساب تكلفة عنصر البيع حسب نوع المنتج ووحدة البيع الفعلية.
     *
     * @param  object|array<string,mixed>  $product
     * @param  object|array<string,mixed>  $item
     */
    public static function calculateItemCost(object|array $product, object|array $item): float
    {
        $product = (object) $product;
        $item = (object) $item;

        $costPrice = (float) ($product->cost_price ?? 0);
        if ($costPrice <= 0) {
            return 0.0;
        }

        $quantity = (float) ($item->quantity ?? 0);
        $unitType = (string) ($item->unit_type ?? 'unit');

        if (($product->product_type ?? null) === 'fractional') {
            $rollLength = (float) ($product->roll_length ?? 0);
            $consumedMeters = (float) ($item->custom_consumption ?? 0);

            if ($consumedMeters <= 0) {
                if (in_array($unitType, ['roll', 'unit'], true) && $rollLength > 0) {
                    $consumedMeters = $quantity * $rollLength;
                } elseif (in_array($unitType, ['meter', 'meters', 'custom'], true)) {
                    $consumedMeters = $quantity;
                } else {
                    $consumedMeters = $quantity;
                }
            }

            if ($rollLength > 0) {
                return ($consumedMeters / $rollLength) * $costPrice;
            }

            return $consumedMeters * $costPrice;
        }

        if ((int) ($product->is_splittable ?? 0) === 1
            && (int) ($product->items_per_unit ?? 0) > 0
            && $unitType === 'piece') {
            return $quantity * ($costPrice / (float) $product->items_per_unit);
        }

        return $quantity * $costPrice;
    }
}
