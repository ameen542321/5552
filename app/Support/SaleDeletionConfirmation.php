<?php

namespace App\Support;

class SaleDeletionConfirmation
{
    public static function message(
        int $saleId,
        bool $hasLinkedCredit,
        bool $hasLinkedCreditCollections,
        bool $hasRestorableStock,
        bool $hasLabor
    ): string {
        $details = [];

        if ($hasLinkedCredit) {
            $details[] = $hasLinkedCreditCollections
                ? 'سيتم حذف الأجل المرتبط من سجل الموظف والقوائم الحالية، مع حفظ بيانات الحذف والتحصيلات المرتبطة في سجل التدقيق.'
                : 'سيتم حذف الأجل المرتبط من سجل الموظف والقوائم الحالية، مع حفظ بيانات الحذف في سجل التدقيق.';
        }

        if ($hasRestorableStock) {
            $details[] = 'سيتم استرجاع كميات المنتجات المرتبطة إلى المخزون.';
        }

        if ($hasLabor) {
            $details[] = 'سيتم حذف شغل اليد المرتبط بهذه العملية.';
        }

        return implode("\n", array_merge([
            "هل أنت متأكد من حذف العملية رقم #{$saleId}؟",
        ], $details));
    }
}
