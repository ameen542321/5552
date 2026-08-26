<?php

namespace App\Services\Shifts;

use App\Models\Log;
use App\Models\Store;
use App\Services\LogService;
use Carbon\Carbon;

/**
 * يسجل ويقرأ تاريخ تغييرات إعدادات شفتات المتجر.
 *
 * الهدف: منع تطبيق نظام الشفتين بأثر رجعي على أيام كانت تعمل كشفت واحد،
 * مع إبقاء أثر تدقيقي واضح لأي تغيير مستقبلي.
 */
class ShiftSettingsHistoryService
{
    public function recordShiftCountChange(Store $store, int $previousNumberOfShifts, int $newNumberOfShifts, ?int $changedBy = null): void
    {
        if ($previousNumberOfShifts === $newNumberOfShifts) {
            return;
        }

        app(LogService::class)->add(
            'store_shift_settings_changed',
            'تغيير عدد شفتات المتجر من ' . $previousNumberOfShifts . ' إلى ' . $newNumberOfShifts,
            $store,
            [
                'previous_number_of_shifts' => $previousNumberOfShifts,
                'new_number_of_shifts' => $newNumberOfShifts,
                'changed_by' => $changedBy,
                'changed_at' => now()->toDateTimeString(),
            ]
        );
    }

    public function firstUpgradeToTwoShiftsDate(Store $store): ?string
    {
        $changeLog = Log::query()
            ->where('action', 'store_shift_settings_changed')
            ->where('model_type', Store::class)
            ->where('model_id', $store->id)
            ->oldest()
            ->limit(20)
            ->get(['details', 'created_at'])
            ->first(function (Log $log): bool {
                return (int) data_get($log->details, 'previous_number_of_shifts', 1) < 2
                    && (int) data_get($log->details, 'new_number_of_shifts', 1) >= 2;
            });

        if ($changeLog?->created_at) {
            return Carbon::parse($changeLog->created_at)->toDateString();
        }

        // توافق خلفي للمتاجر التي عُدّلت قبل إضافة سجل تغيير إعداد الشفتات.
        return ((int) ($store->number_of_shifts ?: 1) >= 2 && $store->updated_at)
            ? Carbon::parse($store->updated_at)->toDateString()
            : null;
    }
}
