<?php

namespace App\Support;

/**
 * مصدر موحد لتسميات طرق الدفع في الواجهات والتقارير.
 *
 * الهدف: منع تكرار match/case داخل الكنترولرات وملفات Blade، وتسهيل تعديل
 * التسمية أو إخفاء القيم غير المعروفة من مكان واحد.
 */
class PaymentTypeLabel
{
    /** @return array<string,string> */
    public static function labels(): array
    {
        return [
            'cash' => 'نقداً',
            'card' => 'شبكة',
            'mixed' => 'مختلط',
            'credit' => 'آجل',
            'internal_use' => 'نقداً',
        ];
    }

    /** @return array<string,string> */
    public static function invoiceOptions(): array
    {
        // صياغة صفحة الفاتورة مختصرة ومرتبة تشغيليًا: كاش، شبكة، ميكس، ثم آجل.
        return [
            'cash' => 'كاش',
            'card' => 'شبكة',
            'mixed' => 'ميكس',
            'credit' => 'آجل',
        ];
    }

    public static function invoiceLabel(?string $saleType): ?string
    {
        return self::labels()[$saleType] ?? null;
    }

    public static function dashboardLabel(?string $saleType): string
    {
        return match ($saleType) {
            'cash' => 'كاش',
            'card' => 'شبكة',
            'mixed' => 'مكس',
            'credit' => 'آجل',
            'internal_use' => 'استهلاك داخلي',
            default => 'غير محدد',
        };
    }

    public static function quickSaleMessageLabel(?string $saleType): string
    {
        return match ($saleType) {
            'cash' => 'نقدي',
            'card' => 'شبكة',
            'credit' => 'آجل',
            'mixed' => 'مختلط',
            default => (string) $saleType,
        };
    }

    public static function dailySalesLabel(?string $saleType, float $remainingAmount = 0): string
    {
        return match (true) {
            $saleType === 'mixed' && $remainingAmount > 0 => 'ميكس + آجل',
            $saleType === 'mixed' => 'ميكس',
            $saleType === 'cash' && $remainingAmount > 0 => 'نقداً + آجل',
            $saleType === 'card' && $remainingAmount > 0 => 'بطاقة + آجل',
            $saleType === 'cash' => 'نقداً',
            $saleType === 'card' => 'بطاقة',
            $saleType === 'credit' && $remainingAmount <= 0 => 'تم التحصيل',
            $saleType === 'credit' => 'آجل',
            $saleType === 'internal_use' => 'استهلاك',
            default => 'آجل',
        };
    }

    /** @return array{label:string,class:string} */
    public static function reportBadge(?string $saleType): array
    {
        return match ((string) $saleType) {
            'cash' => ['label' => 'نقد', 'class' => 'b-cash'],
            'card' => ['label' => 'شبكة', 'class' => 'b-card'],
            'mixed' => ['label' => 'ميكس', 'class' => 'b-mixed'],
            'credit' => ['label' => 'آجل', 'class' => 'b-credit'],
            'internal_use' => ['label' => 'استهلاك داخلي', 'class' => 'b-other'],
            default => ['label' => ((string) $saleType ?: 'غير محدد'), 'class' => 'b-other'],
        };
    }
}
