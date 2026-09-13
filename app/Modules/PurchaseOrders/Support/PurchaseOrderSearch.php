<?php

namespace App\Modules\PurchaseOrders\Support;

final class PurchaseOrderSearch
{
    /**
     * استخراج رقم السجل من المرجع الظاهر للمستخدم مثل PO-3-2026-00015.
     * يبقى تقييد المتجر/المالك في الاستعلام الأساسي، لذلك لا يوسع هذا التحويل نطاق الصلاحية.
     */
    public static function orderId(string $search): ?int
    {
        if (ctype_digit($search)) {
            return (int) $search;
        }

        if (preg_match('/^PO-\d+-\d{4}-(\d+)$/i', $search, $matches) === 1) {
            return (int) $matches[1];
        }

        return null;
    }
}
