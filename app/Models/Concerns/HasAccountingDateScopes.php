<?php

namespace App\Models\Concerns;

use Carbon\Carbon;

trait HasAccountingDateScopes
{
    /**
     * نطاق التاريخ: يستخدم business_date عند توفره مع رجوع آمن إلى created_at للبيانات القديمة.
     */
    public function scopeForAccountingDate($query, $date, bool $includeLegacyCreatedAtDate = true)
    {
        return $query->where(function ($query) use ($date, $includeLegacyCreatedAtDate) {
            $query->whereDate('business_date', $date);

            if ($includeLegacyCreatedAtDate) {
                // Fallback للبيانات القديمة فقط: العمليات الجديدة يجب أن تحمل business_date صريحًا.
                $query->orWhere(function ($legacyQuery) use ($date) {
                    $legacyQuery->whereNull('business_date')
                        ->whereDate('created_at', $date);
                });
            }
        });
    }

    /**
     * نطاق الفترة المالية: يحمي التقارير من الشفتات الممتدة بعد منتصف الليل.
     */
    public function scopeBetweenAccountingDates($query, $startDate, $endDate)
    {
        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);

        return $query->where(function ($query) use ($start, $end) {
            $query->whereBetween('business_date', [$start->toDateString(), $end->toDateString()])
                ->orWhere(function ($legacyQuery) use ($start, $end) {
                    $legacyQuery->whereNull('business_date')
                        ->whereBetween('created_at', [
                            $start->copy()->startOfDay(),
                            $end->copy()->endOfDay(),
                        ]);
                });
        });
    }

    /**
     * نطاق الشفت المفتوح: عند توفر business_date نأخذ العمليات غير المرتبطة بموازنة مغلقة،
     * وإلا نرجع إلى العمليات التي حدثت بعد بداية الشفت الحالية.
     */
    public function scopeForOpenAccountingShift($query, ?string $businessDate = null, $shiftStart = null)
    {
        return $query->when(
            $businessDate,
            fn ($query) => $query->whereDate('business_date', $businessDate)->whereNull('daily_balance_id'),
            fn ($query) => $shiftStart ? $query->where('created_at', '>', $shiftStart) : $query
        );
    }
}
