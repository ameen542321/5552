<?php

namespace App\Support;

class EventPolicy
{
    /** @var array<string, string[]> */
    private const GENERAL_TO_SPECIFIC = [
        'accountant' => ['accountant_created', 'accountant_updated', 'accountant_deleted', 'accountant_restored', 'accountant_force_deleted', 'accountant_status_changed'],
        'employee' => ['employee_created', 'employee_updated', 'employee_deleted', 'employee_restored', 'employee_force_deleted', 'employee_promoted', 'employee_demoted', 'debt_added', 'debt_collected', 'withdrawal_added', 'credit_sale_added', 'credit_sale_collection', 'absence_recorded'],
        'product' => ['product_created', 'product_updated', 'product_deleted', 'product_status_changed', 'product_import_csv', 'product_export_csv'],
        'category' => ['category_created', 'category_updated', 'category_deleted', 'category_force_deleted', 'category_moved'],
        'store' => ['store_created', 'store_updated', 'store_deleted', 'set_current'],
        'finance' => ['expense_added', 'balance_done', 'sale_created', 'sale_updated', 'sale_deleted'],
        'notification' => ['notification_sent', 'notification_marked_read', 'notification_deleted'],
        'security' => ['status_change', 'login', 'logout'],
    ];

    private const GENERAL_LABELS = [
        'accountant' => 'المحاسبين',
        'employee' => 'الموظفين',
        'product' => 'المنتجات',
        'category' => 'الأقسام',
        'store' => 'المتاجر',
        'finance' => 'المالية',
        'notification' => 'الإشعارات',
        'security' => 'الأمان',
    ];

    private const SPECIFIC_LABELS = [
        'create' => 'إنشاء',
        'update' => 'تعديل',
        'delete' => 'حذف',
        'set_current' => 'تعيين متجر',
        'status_change' => 'تغيير حالة',
        'balance_done' => 'إقفال شفت',
        'accountant_created' => 'إضافة محاسب',
        'accountant_updated' => 'تعديل محاسب',
        'accountant_deleted' => 'حذف محاسب',
        'accountant_restored' => 'استرجاع محاسب',
        'accountant_force_deleted' => 'حذف نهائي لمحاسب',
        'employee_created' => 'إضافة موظف',
        'employee_updated' => 'تعديل موظف',
        'employee_deleted' => 'حذف موظف',
        'employee_restored' => 'استرجاع موظف',
        'employee_force_deleted' => 'حذف نهائي لموظف',
        'employee_promoted' => 'ترقية موظف',
        'employee_demoted' => 'إلغاء صلاحية موظف',
        'debt_added' => 'إضافة دين',
        'debt_collected' => 'تحصيل دين',
        'withdrawal_added' => 'إضافة سحب',
        'credit_sale_added' => 'إضافة بيع آجل',
        'credit_sale_collection' => 'تحصيل بيع آجل',
        'absence_recorded' => 'تسجيل غياب',
        'product_created' => 'إضافة منتج',
        'product_updated' => 'تعديل منتج',
        'product_deleted' => 'حذف منتج',
        'product_status_changed' => 'تغيير حالة منتج',
        'product_import_csv' => 'استيراد CSV للمنتجات',
        'product_export_csv' => 'تصدير CSV للمنتجات',
        'category_created' => 'إضافة قسم',
        'category_updated' => 'تعديل قسم',
        'category_deleted' => 'حذف قسم',
        'category_force_deleted' => 'حذف نهائي لقسم',
        'category_moved' => 'نقل قسم',
        'store_created' => 'إضافة متجر',
        'store_updated' => 'تعديل متجر',
        'store_deleted' => 'حذف متجر',
        'expense_added' => 'إضافة مصروف',
        'sale_created' => 'إضافة بيع',
        'sale_updated' => 'تعديل بيع',
        'sale_deleted' => 'حذف بيع',
        'notification_sent' => 'إرسال إشعار',
        'notification_marked_read' => 'تعليم إشعار كمقروء',
        'notification_deleted' => 'حذف إشعار',
        'login' => 'تسجيل دخول',
        'logout' => 'تسجيل خروج',
    ];

    public static function normalizeGeneralAction(string $value): string
    {
        $value = trim(mb_strtolower($value));
        $value = str_replace([' ', '-'], '_', $value);

        $aliases = [
            'all' => '',
            'الكل' => '',
            'عام' => 'all',
            'general' => 'all',
            'accountants' => 'accountant',
            'employees' => 'employee',
            'products' => 'product',
            'categories' => 'category',
            'stores' => 'store',
            'notifications' => 'notification',
            'financial' => 'finance',
        ];

        return $aliases[$value] ?? $value;
    }

    /** @return string[] */
    public static function expandToSpecific(string $action): array
    {
        $normalized = self::normalizeGeneralAction($action);

        if (isset(self::GENERAL_TO_SPECIFIC[$normalized])) {
            return self::GENERAL_TO_SPECIFIC[$normalized];
        }

        return [$normalized];
    }

    public static function generalFor(string $action): string
    {
        $normalized = self::normalizeGeneralAction($action);

        foreach (self::GENERAL_TO_SPECIFIC as $general => $specificActions) {
            if (in_array($normalized, $specificActions, true)) {
                return $general;
            }
        }

        if (isset(self::GENERAL_TO_SPECIFIC[$normalized])) {
            return $normalized;
        }

        if (str_starts_with($normalized, 'accountant_')) return 'accountant';
        if (str_starts_with($normalized, 'employee_') || str_contains($normalized, 'debt') || str_contains($normalized, 'withdrawal') || str_contains($normalized, 'credit_sale')) return 'employee';
        if (str_starts_with($normalized, 'product_')) return 'product';
        if (str_starts_with($normalized, 'category_')) return 'category';
        if (str_starts_with($normalized, 'store_') || $normalized === 'set_current') return 'store';

        return $normalized;
    }


    public static function isGeneralAction(string $value): bool
    {
        $normalized = self::normalizeGeneralAction($value);

        return isset(self::GENERAL_TO_SPECIFIC[$normalized]);
    }
    public static function labelFor(string $action): string
    {
        $normalized = self::normalizeGeneralAction($action);

        if (isset(self::GENERAL_LABELS[$normalized])) {
            return self::GENERAL_LABELS[$normalized];
        }

        return self::SPECIFIC_LABELS[$normalized] ?? str_replace('_', ' ', $normalized);
    }
}
