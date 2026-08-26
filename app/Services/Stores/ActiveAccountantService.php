<?php

namespace App\Services\Stores;

use App\Models\Accountant;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Collection;

class ActiveAccountantService
{
    /**
     * المحاسبون النشطون المرتبطون بمتجر محدد ومالك محدد لاستخدامهم في طلبات الشفتات والواجهات التشغيلية.
     */
    public function activeAccountantsForStore(Store $store, User $owner): Collection
    {
        return Accountant::query()
            ->where('store_id', $store->id)
            ->where('user_id', $owner->id)
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * يبحث عن محاسب نشط صالح لاستلام طلب شفت داخل متجر المالك.
     */
    public function findActiveAccountantForStore(Store $store, User $owner, int $accountantId): ?Accountant
    {
        return Accountant::query()
            ->where('id', $accountantId)
            ->where('store_id', $store->id)
            ->where('user_id', $owner->id)
            ->where('status', 'active')
            ->first();
    }
}
