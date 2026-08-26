<?php

namespace App\Services;

use App\Models\Store;

class PlanLimitService
{
    /**
     * هل يمكن إضافة محاسب جديد لهذا المتجر؟
     */
    public static function canAddAccountant(Store $store): bool
    {
        // إذا المتجر ليس لديه خطة → اعتبره بلا حدود أو امنعه حسب منطقك
        if (!$store->plan) {
            return true; // أو false حسب نظامك
        }

        // الحد المسموح به من الخطة
        $max = $store->plan->allowed_accountants;

        // عدد المحاسبين النشطين حاليًا
        $activeCount = $store->accountants()
            ->where('status', 'active')
            ->count();

        return $activeCount < $max;
    }

    /**
     * منع العملية إذا تجاوز المتجر الحد
     */
    public static function assertCanAddAccountant(Store $store)
    {
        if (! self::canAddAccountant($store)) {
            abort(403, 'لا يمكنك إضافة أو تفعيل محاسب جديد، لقد وصلت للحد الأقصى في خطتك الحالية.');
        }
    }
}
