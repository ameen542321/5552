<?php

namespace App\Modules\PurchaseOrders\Support;

use App\Models\Product;
use App\Modules\PurchaseOrders\Models\StorePurchaseOrderItem;

class PurchaseOrderCostCalculator
{
    public function receiptLineCost(StorePurchaseOrderItem $item, float $quantity, string $unitType, ?int $matchedProductId = null): float
    {
        if ($quantity <= 0) {
            return 0.0;
        }

        $product = $item->product;
        if (! $product && $matchedProductId) {
            $product = Product::where('store_id', $item->order->store_id)->find($matchedProductId);
        }

        if ($product) {
            return $this->orderLineCost($product, $quantity, $unitType);
        }

        $requestedQuantity = (float) ($item->quantity_requested ?? 0);
        $orderPrice = (float) ($item->cost_price_at_order ?? 0);

        return ($requestedQuantity > 0 && $quantity > 0)
            ? round(($orderPrice / $requestedQuantity) * $quantity, 2)
            : $orderPrice;
    }

    public function normalizedProductCostFromReceipt(Product $product, float $receiptPrice, float $quantity, string $unitType): float
    {
        if ($receiptPrice <= 0 || $quantity <= 0) {
            return (float) ($product->cost_price ?? 0);
        }

        $unitReceiptCost = $receiptPrice / $quantity;

        if (in_array($unitType, ['meter', 'meters'], true) && (float) ($product->roll_length ?? 0) > 0) {
            return round($unitReceiptCost * (float) $product->roll_length, 2);
        }

        if ($unitType === 'piece' && (int) ($product->items_per_unit ?? 0) > 0) {
            return round($unitReceiptCost * (int) $product->items_per_unit, 2);
        }

        return round($unitReceiptCost, 2);
    }

    public function orderLineCost(Product $product, float $quantity, string $unitType): float
    {
        if ($quantity <= 0) {
            return 0.0;
        }

        $cost = (float) ($product->cost_price ?? 0);

        if (in_array($unitType, ['meter', 'meters'], true) && (float) ($product->roll_length ?? 0) > 0) {
            return round(($cost / (float) $product->roll_length) * $quantity, 2);
        }

        if ($unitType === 'piece' && (int) ($product->items_per_unit ?? 0) > 0) {
            return round(($cost / (float) $product->items_per_unit) * $quantity, 2);
        }

        return round($cost * $quantity, 2);
    }
}
