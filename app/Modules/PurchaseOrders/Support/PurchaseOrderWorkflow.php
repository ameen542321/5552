<?php

namespace App\Modules\PurchaseOrders\Support;

use App\Modules\PurchaseOrders\Models\StorePurchaseOrder;
use Illuminate\Validation\ValidationException;

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
            'reversed' => 'تم عكس اعتماد الطلبية',
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
            'reversed' => 'ui-badge-danger',
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

    /**
     * المصدر المركزي للانتقالات التشغيلية المسموحة. تبقى شروط البيانات التفصيلية
     * داخل الخدمة، بينما يمنع هذا العقد تنفيذ إجراء من مرحلة غير صحيحة.
     */
    public static function actionSources(): array
    {
        return [
            'edit_draft' => ['pending_owner_review', 'returned_for_edit', 'returned_after_edit', 'returned_for_count', 'returned_after_count'],
            'mark_sent' => ['pending_owner_review', 'returned_after_edit', 'returned_after_count'],
            'receive_accountant' => ['pending_receipt_confirmation'],
            'receive_owner' => ['pending_receipt_confirmation', 'pending_owner_receipt_review'],
            'approve' => ['pending_inventory_approval'],
            'return_for_edit' => ['pending_owner_review', 'returned_after_edit', 'returned_after_count'],
            'return_for_count' => ['pending_owner_review', 'returned_after_edit', 'returned_after_count'],
            'approve_inventory_review' => ['returned_after_count'],
            'reject' => ['pending_owner_review', 'returned_after_edit', 'returned_after_count'],
            'reopen' => ['rejected'],
            'cancel' => ['pending_owner_review', 'returned_after_edit', 'returned_after_count', 'pending_receipt_confirmation'],
            'reverse' => ['approved_and_supplied'],
        ];
    }

    public static function assertAllows(StorePurchaseOrder $order, string $action): void
    {
        $allowed = self::actionSources()[$action] ?? [];
        if (! in_array((string) $order->workflow_status, $allowed, true)) {
            throw ValidationException::withMessages([
                'order' => 'لا يمكن تنفيذ هذا الإجراء من المرحلة الحالية للطلبية.',
            ]);
        }
    }

    /** @return list<string> */
    public static function consistencyIssues(StorePurchaseOrder $order): array
    {
        $issues = [];
        $expectedStatus = [
            'draft_accountant' => 'draft', 'pending_owner_review' => 'draft',
            'returned_for_edit' => 'draft', 'returned_after_edit' => 'draft',
            'returned_for_count' => 'draft', 'returned_after_count' => 'draft',
            'rejected' => 'draft', 'pending_receipt_confirmation' => 'sent',
            'pending_owner_receipt_review' => 'received', 'pending_inventory_approval' => 'received',
            'approved_and_supplied' => 'approved', 'reversed' => 'approved', 'cancelled' => 'cancelled',
        ][$order->workflow_status] ?? null;

        if ($expectedStatus === null) {
            $issues[] = 'مرحلة تشغيلية غير معروفة.';
        } elseif ($order->status !== $expectedStatus) {
            $issues[] = 'الحالة العامة لا تطابق المرحلة التشغيلية.';
        }
        if ($order->status === 'approved' && (! $order->approved_at || ! $order->approval_operation_id || ! $order->approved_business_date)) {
            $issues[] = 'طلبية معتمدة ينقصها وقت الاعتماد أو اليوم أو معرف العملية.';
        }
        if ($order->workflow_status === 'reversed' && (! $order->reversed_at || ! $order->reversal_operation_id || ! trim((string) $order->reversal_reason))) {
            $issues[] = 'طلبية معكوسة ينقصها سجل العكس الإلزامي.';
        }
        if ($order->status === 'sent' && ! $order->sent_at) {
            $issues[] = 'طلبية مرسلة بلا تاريخ إرسال.';
        }
        if ($order->status === 'received' && ! $order->received_at) {
            $issues[] = 'طلبية مستلمة بلا تاريخ تأكيد استلام.';
        }
        if ($order->status === 'cancelled' && ! $order->cancelled_at) {
            $issues[] = 'طلبية ملغاة بلا تاريخ إلغاء.';
        }
        if ($order->status === 'approved' && in_array($order->inventory_review_status, ['returned_to_accountant', 'count_draft', 'pending_owner_after_count'], true)) {
            $issues[] = 'طلبية معتمدة وما زالت مراجعة الجرد معلقة.';
        }

        return $issues;
    }

    public static function eventHelp(?string $event): string
    {
        return [
            'created' => 'أنشئت الطلبية كمسودة فقط، ولم يتغير المخزون.',
            'items_updated' => 'حُفظت تعديلات البنود والكميات مع تسجيل الفروقات، دون إضافة مخزون.',
            'item_added' => 'أضيف بند إلى مسودة الطلبية، ولا يؤثر في المخزون قبل الاعتماد النهائي.',
            'item_deleted' => 'حذف بند من المسودة وسُجل اسمه في الأحداث للمراجعة.',
            'sent_to_supplier' => 'أقفلت المسودة وأصبحت الطلبية بانتظار تأكيد ما وصل من المورد.',
            'receipt_confirmed' => 'حُفظت الكميات والتكاليف المستلمة للمراجعة، ولم تُضف إلى المخزون بعد.',
            'receipt_review_updated' => 'عُدلت بيانات الاستلام مع إبقاء الطلبية في دورة المراجعة.',
            'returned_for_edit' => 'أعاد المالك الطلبية لتصحيح البنود قبل إرسالها للمورد.',
            'returned_for_count' => 'أعاد المالك بنودًا للجرد، ولا يكشف ذلك رصيد النظام للمحاسب.',
            'count_submitted' => 'أرسل المحاسب نتيجة الجرد للمالك، مع حفظ لقطة النظام للمقارنة فقط.',
            'count_approved' => 'اعتمد المالك مراجعة الجرد دون تعديل المخزون بفروقات الجرد.',
            'inventory_approved' => 'اعتمد المالك التوريد؛ أضيفت كميات منتجات البيع وسُجلت مشتريات المالك وحُفظت اللقطات.',
            'inventory_approval_reversed' => 'عكس الأدمن أثر الاعتماد بحركات مقابلة، وأبقى الطلبية وسبب العكس وسجلها للمراجعة.',
            'support_status_corrected' => 'صحح الأدمن المرحلة فقط دون تنفيذ حركة مخزون، وسُجل السبب ورقم التذكرة.',
            'support_restored' => 'استعاد الأدمن الطلبية المحذوفة مع تسجيل سبب الاستعادة وتذكرة الدعم.',
            'rejected' => 'رفض المالك الطلبية مع حفظ السبب، ويمكن إعادتها للمراجعة لاحقًا.',
            'reopened' => 'أعيدت الطلبية المرفوضة إلى مراجعة المالك دون تغيير المخزون.',
            'cancelled' => 'ألغيت الطلبية قبل الاستلام، ولذلك لم ينفذ أثر مخزني.',
        ][$event] ?? 'يوثق هذا الحدث ما تغير في الطلبية ومن نفذه ووقته، ولا يعني وحده وجود حركة مخزون.';
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
