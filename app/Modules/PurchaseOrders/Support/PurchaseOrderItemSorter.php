<?php

namespace App\Modules\PurchaseOrders\Support;

use App\Modules\PurchaseOrders\Models\StorePurchaseOrder;

class PurchaseOrderItemSorter
{
    public static function sortLoadedItemsByName(StorePurchaseOrder $order): void
    {
        if (! $order->relationLoaded('items')) {
            return;
        }

        $order->setRelation('items', $order->items
            ->sortBy(fn ($item) => mb_strtolower($item->productName() ?: ''))
            ->values());
    }
}
