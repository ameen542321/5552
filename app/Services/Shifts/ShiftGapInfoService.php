<?php

namespace App\Services\Shifts;

use App\Models\DailyBalance;
use App\Models\Store;
use App\Services\ShiftLifecycleService;
use Illuminate\Support\Collection;

class ShiftGapInfoService
{
    public function __construct(private ShiftLifecycleService $shiftLifecycleService)
    {
    }

    /**
     * يحسب معلومات الشفت الناقص لتاريخ محدد دون إنشاء طلب أو تعديل بيانات.
     */
    public function shiftInfo(Store $store, string $businessDate): array
    {
        $closedShiftsCount = $this->closedShiftsCount($store, $businessDate);
        $maxShifts = $this->shiftLifecycleService->requiredShiftsForBusinessDate($store, $businessDate);
        $missingShiftNumber = min($closedShiftsCount + 1, $maxShifts);

        return [
            'closed_shifts_count' => $closedShiftsCount,
            'missing_shift_number' => $missingShiftNumber,
            'max_shifts' => $maxShifts,
            'shift_label' => 'الشفت ' . $missingShiftNumber . ' من ' . $maxShifts,
        ];
    }

    /**
     * يبني صف تنبيه شفت ناقص واحد؛ في نظام الشفتين لا يظهر الشفت الثاني قبل إغلاق الأول.
     */
    public function missingShiftRowsForDate(Store $store, string $businessDate): Collection
    {
        $shiftInfo = $this->shiftInfo($store, $businessDate);

        if ($shiftInfo['closed_shifts_count'] >= $shiftInfo['max_shifts']) {
            return collect();
        }

        $missingShiftNumber = (int) $shiftInfo['missing_shift_number'];

        return collect([array_merge(['date' => $businessDate], $shiftInfo, [
            'missing_shift_number' => $missingShiftNumber,
            'shift_label' => 'الشفت ' . $missingShiftNumber . ' من ' . $shiftInfo['max_shifts'],
        ])]);
    }

    /**
     * يعد الشفتات المغلقة لتاريخ العمل مع دعم الشفتات القديمة التي لا تملك business_date.
     */
    public function closedShiftsCount(Store $store, string $businessDate): int
    {
        return DailyBalance::query()
            ->where('store_id', $store->id)
            ->whereNotNull('end_time')
            ->where(function ($query) use ($businessDate) {
                $query->whereDate('business_date', $businessDate)
                    ->orWhere(function ($legacyQuery) use ($businessDate) {
                        $legacyQuery->whereNull('business_date')
                            ->whereDate('start_time', $businessDate);
                    });
            })
            ->count();
    }
}
