<?php

namespace App\Services\Stores;

use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Collection;

class StoreAccessService
{
    /**
     * المتاجر النشطة التي يمكن للمالك استخدامها في الواجهات التشغيلية.
     */
    public function activeStoresForOwner(User $user): Collection
    {
        return $user->stores()
            ->where('status', 'active')
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * يحدد هل المالك الحالي يملك صلاحية الوصول إلى المتجر المحدد.
     */
    public function ownerCanAccess(User $user, Store $store): bool
    {
        return (int) $store->user_id === (int) $user->id;
    }

    /**
     * يوقف التنفيذ فورًا إذا حاول مالك الوصول إلى متجر لا يملكه.
     */
    public function ensureOwnerCanAccess(User $user, Store $store): void
    {
        if (! $this->ownerCanAccess($user, $store)) {
            abort(403, 'غير مصرح لك بالوصول لهذا المتجر.');
        }
    }

    /**
     * يحدد هل المتجر مفعل للتشغيل اليومي.
     */
    public function isActive(Store $store): bool
    {
        return $store->status === 'active';
    }

    /**
     * يحدد هل المتجر صالح لتدفقات الشفتات، ويستبعد المتاجر غير النشطة أو المحذوفة.
     */
    public function isUsableForShiftWorkflow(Store $store): bool
    {
        return $this->isActive($store) && ! $store->trashed();
    }
}
