<?php

namespace App\Modules\PurchaseOrders\Support;

final class PurchaseOrderWorkflow
{
    public const UNKNOWN_LABEL = 'حالة غير معروفة';

    public static function labels(?string $ownerName = null): array
    {
        $ownerName = trim((string) $ownerName) ?: 'المالك';

        return [
            'draft_accountant' => 'مسودة لدى المحاسب',
            'pending_owner_review' => 'بانتظار مراجعة '.$ownerName,
            'returned_for_edit' => 'معادة للتعديل',
            'returned_after_edit' => 'معادة إلى '.$ownerName.' بعد التعديل',
            'returned_for_count' => 'معادة للجرد',
            'returned_after_count' => 'معادة إلى '.$ownerName.' بعد الجرد',
            'pending_receipt_confirmation' => 'بانتظار تأكيد الاستلام',
            'pending_owner_receipt_review' => 'بانتظار مراجعة تأكيد الاستلام',
            'pending_inventory_approval' => 'بانتظار الاعتماد المخزني',
            'approved_and_supplied' => 'تم اعتماد وتوريد الطلبية',
            'rejected' => 'مرفوضة',
            'cancelled' => 'ملغاة',
        ];
    }

    public static function label(?string $status, ?string $ownerName = null): string
    {
        return self::labels($ownerName)[$status] ?? self::UNKNOWN_LABEL;
    }

    public static function badgeClasses(): array
    {
        return [
            'draft_accountant' => 'ui-badge-neutral',
            'pending_owner_review' => 'ui-badge-info',
            'returned_for_edit' => 'ui-badge-warning',
            'returned_after_edit' => 'ui-badge-info',
            'returned_for_count' => 'ui-badge-warning',
            'returned_after_count' => 'ui-badge-info',
            'pending_receipt_confirmation' => 'ui-badge-success',
            'pending_owner_receipt_review' => 'ui-badge-info',
            'pending_inventory_approval' => 'ui-badge-warning',
            'approved_and_supplied' => 'ui-badge-success',
            'rejected' => 'ui-badge-danger',
            'cancelled' => 'ui-badge-danger',
        ];
    }

    public static function supportTransitions(): array
    {
        return [
            'draft_accountant' => ['draft', 'draft_accountant'],
            'pending_owner_review' => ['draft', 'pending_owner_review'],
            'returned_for_edit' => ['draft', 'returned_for_edit'],
            'returned_for_count' => ['draft', 'returned_for_count'],
            'pending_receipt_confirmation' => ['sent', 'pending_receipt_confirmation'],
            'pending_owner_receipt_review' => ['received', 'pending_owner_receipt_review'],
            'pending_inventory_approval' => ['received', 'pending_inventory_approval'],
            'rejected' => ['draft', 'rejected'],
            'cancelled' => ['cancelled', 'cancelled'],
        ];
    }

    public static function supportLabels(): array
    {
        return array_intersect_key(self::labels(), self::supportTransitions());
    }

    public static function filterLabels(?string $ownerName = null): array
    {
        return self::labels($ownerName);
    }

    public static function allowsPdf(string $mode, string $status, ?string $inventoryReviewStatus = null): bool
    {
        return match ($mode) {
            'order' => in_array($status, ['draft', 'sent'], true),
            'receipt' => in_array($status, ['sent', 'received', 'approved'], true),
            'inventory' => $status === 'approved',
            'inventory-count' => in_array($inventoryReviewStatus, ['returned_to_accountant', 'count_draft'], true),
            default => false,
        };
    }
}
