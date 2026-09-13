<?php

namespace App\Modules\PurchaseOrders\Services;

use App\Models\Store;
use App\Modules\PurchaseOrders\Models\PurchaseOrderLimitSetting;

class PurchaseOrderLimitService
{
    public function global(): PurchaseOrderLimitSetting
    {
        return PurchaseOrderLimitSetting::firstOrCreate(
            ['store_id' => null],
            ['weekly_limit' => PurchaseOrderLimitSetting::DEFAULT_WEEKLY_LIMIT, 'counted_statuses' => PurchaseOrderLimitSetting::DEFAULT_COUNTED_STATUSES]
        );
    }

    public function forStore(Store $store): PurchaseOrderLimitSetting
    {
        $storeSetting = PurchaseOrderLimitSetting::where('store_id', $store->id)->first();
        if ($storeSetting) {
            return $storeSetting;
        }

        return $this->global();
    }
}
