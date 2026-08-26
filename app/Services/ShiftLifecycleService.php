<?php

namespace App\Services;

use App\Models\DailyBalance;
use App\Models\Log;
use App\Models\Store;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use App\Services\Shifts\ShiftSettingsHistoryService;

class ShiftLifecycleService
{
    public const MISSING_DAYS_LOOKBACK = 15;

    /**
     * Resolve the current open shift window and accounting date for a store.
     *
     * The real shift start remains the previous closed balance end_time, while
     * business_date advances by closed shift count and the store's configured
     * number_of_shifts.
     */
    public function currentShiftContext(Store|int $store, ?Carbon $operationTime = null, bool $includeMissingDates = false): array
    {
        $store = $store instanceof Store ? $store : Store::findOrFail($store);
        $operationTime ??= now();

        if ($gapDate = $this->activeAccountantGapDate($store, $operationTime)) {
            return [
                'business_date' => $gapDate,
                'daily_balance_id' => null,
                'shift_start' => Carbon::parse($gapDate)->startOfDay(),
                'last_closed_balance' => $this->lastClosedBalance($store->id),
                'max_shifts_per_business_date' => $this->maxShiftsPerBusinessDate($store),
                'shift_number' => $this->closedShiftsCount($store->id, $gapDate) + 1,
                'requires_second_shift_confirmation' => false,
                'can_choose_next_shift_business_date' => false,
                'missing_business_dates' => $includeMissingDates ? $this->missingBusinessDates($store->id, $operationTime) : [],
                'is_shift_gap_processing' => true,
            ];
        }

        $lastClosedBalance = $this->lastClosedBalance($store->id);
        $shiftStart = $lastClosedBalance?->end_time
            ? Carbon::parse($lastClosedBalance->end_time)
            : $operationTime->copy()->startOfDay();

        $lastBusinessDate = $lastClosedBalance
            ? $this->balanceBusinessDate($lastClosedBalance)
            : null;

        $maxShifts = $this->maxShiftsPerBusinessDate($store);
        $closedShiftsForLastDate = $lastBusinessDate
            ? $this->closedShiftsCount($store->id, $lastBusinessDate)
            : 0;

        $decidedNextBusinessDate = $lastClosedBalance?->next_shift_business_date
            ? Carbon::parse($lastClosedBalance->next_shift_business_date)->toDateString()
            : null;

        if (! $lastBusinessDate) {
            // الشفت الحالي الطبيعي يتبع تاريخ التشغيل الحالي؛ التواريخ المرتجعة لا تفعل إلا من طلب شفت ناقص.
            $businessDate = $operationTime->toDateString();
            $shiftNumber = 1;
        } elseif ($decidedNextBusinessDate) {
            $businessDate = $decidedNextBusinessDate;
            $shiftNumber = $this->closedShiftsCount($store->id, $businessDate) + 1;
        } elseif ($closedShiftsForLastDate < $maxShifts) {
            $businessDate = $lastBusinessDate;
            $shiftNumber = $closedShiftsForLastDate + 1;
        } else {
            $businessDate = Carbon::parse($lastBusinessDate)->addDay()->toDateString();
            $shiftNumber = 1;
        }

        // إذا كان آخر إقفال قديمًا وبقي نفس اليوم مؤهلاً كشفت ثانٍ، لا ننقل الشفت الحالي
        // إلى تاريخ سابق. التواريخ السابقة تستخدم فقط عند تفعيل طلب شفت ناقص من المحاسب.
        if (Carbon::parse($businessDate)->lt($operationTime->copy()->startOfDay())) {
            $businessDate = $operationTime->toDateString();
            $shiftNumber = $this->closedShiftsCount($store->id, $businessDate) + 1;
        }

        return [
            'business_date' => $businessDate,
            'daily_balance_id' => null,
            'shift_start' => $shiftStart,
            'last_closed_balance' => $lastClosedBalance,
            'max_shifts_per_business_date' => $maxShifts,
            'shift_number' => $shiftNumber,
            'requires_second_shift_confirmation' => $maxShifts > 1 && $lastBusinessDate === $businessDate && $shiftNumber > 1,
            'can_choose_next_shift_business_date' => $shiftNumber < $maxShifts,
            'missing_business_dates' => $includeMissingDates ? $this->missingBusinessDates($store->id, $operationTime) : [],
            'is_shift_gap_processing' => false,
        ];
    }

    private function activeAccountantGapDate(Store $store, Carbon $operationTime): ?string
    {
        if (app()->runningInConsole() || ! auth('accountant')->check()) {
            return null;
        }

        $sessionStoreId = (int) session('accountant_shift_gap_store_id');
        $sessionDate = session('accountant_shift_gap_business_date');

        if ($sessionStoreId !== (int) $store->id || ! $sessionDate) {
            return null;
        }

        $businessDate = Carbon::parse($sessionDate)->toDateString();

        $isMissingBusinessDate = in_array($businessDate, $this->missingBusinessDates($store->id, $operationTime), true);

        if (! $isMissingBusinessDate && ! $this->hasActiveDeferredShiftGap($store, $businessDate)) {
            session()->forget([
                'accountant_shift_gap_store_id',
                'accountant_shift_gap_business_date',
                'accountant_shift_gap_log_id',
            ]);

            return null;
        }

        return $businessDate;
    }

    private function hasActiveDeferredShiftGap(Store $store, string $businessDate): bool
    {
        $logId = session('accountant_shift_gap_log_id');
        if (! $logId) {
            return false;
        }

        $log = Log::query()
            ->where('id', $logId)
            ->where('store_id', $store->id)
            ->where('action', 'shift_gap_accountant_request')
            ->first();

        if (! $log) {
            return false;
        }

        $details = is_array($log->details) ? $log->details : [];
        $logBusinessDate = data_get($details, 'business_date');
        if (! $logBusinessDate || Carbon::parse($logBusinessDate)->toDateString() !== $businessDate) {
            return false;
        }

        $status = data_get($details, 'status', 'pending');
        $isDeferredOrResumable = data_get($details, 'source') === 'accountant_deferred_shift'
            || filled(data_get($details, 'accountant_paused_at'))
            || ($status === 'in_progress' && filled(data_get($details, 'accountant_started_at')));

        if (! $isDeferredOrResumable || ! in_array($status, ['pending', 'in_progress'], true)) {
            return false;
        }

        $requiredShifts = max((int) data_get($details, 'max_shifts', 0), $this->maxShiftsPerBusinessDate($store));

        return $this->closedShiftsCount($store->id, $businessDate) < $requiredShifts;
    }

    public function missingBusinessDates(int $storeId, ?Carbon $referenceDate = null): array
    {
        $store = Store::withTrashed()->find($storeId);
        if (! $store) {
            return [];
        }

        $referenceDate ??= now();
        // تنبيهات الشفتات تعرض الأيام المكتملة فقط؛ تاريخ اليوم الحالي لا يعتبر ناقصًا قبل نهايته.
        $endDate = $referenceDate->copy()->subDay()->startOfDay();
        $startDate = $endDate->copy()->subDays(self::MISSING_DAYS_LOOKBACK - 1);
        $storeCreatedAt = Carbon::parse($store->created_at)->startOfDay();
        if ($storeCreatedAt->greaterThan($startDate)) {
            $startDate = $storeCreatedAt;
        }

        if ($startDate->greaterThan($endDate)) {
            return [];
        }

        $inactiveDates = $this->inactiveBusinessDates($store, $startDate, $endDate);

        $closedShiftCounts = DailyBalance::query()
            ->where('store_id', $storeId)
            ->whereNotNull('end_time')
            ->where(function ($query) {
                $query->whereNull('notes')
                    ->orWhere('notes', 'not like', '%إغلاق تلقائي لشفت مكتمل بدون بيانات%');
            })
            ->where(function ($query) use ($startDate, $endDate) {
                $query->whereBetween('business_date', [$startDate->toDateString(), $endDate->toDateString()])
                    ->orWhere(function ($legacyQuery) use ($startDate, $endDate) {
                        $legacyQuery->whereNull('business_date')
                            ->whereBetween('created_at', [$startDate, $endDate->copy()->endOfDay()]);
                    });
            })
            ->get(['business_date', 'start_time', 'created_at'])
            ->map(fn (DailyBalance $balance) => $this->balanceBusinessDate($balance))
            ->filter()
            ->countBy();

        return collect(CarbonPeriod::create($startDate, $endDate))
            ->map(fn (Carbon $date) => $date->toDateString())
            ->reject(fn (string $date) => $inactiveDates->contains($date))
            ->reject(fn (string $date) => (int) ($closedShiftCounts[$date] ?? 0) >= $this->requiredShiftsForBusinessDate($store, $date))
            ->values()
            ->all();
    }

    private function inactiveBusinessDates(Store $store, Carbon $startDate, Carbon $endDate)
    {
        $statusBeforeStart = Log::query()
            ->where('action', 'status_change')
            ->where('model_type', Store::class)
            ->where('model_id', $store->id)
            ->where('created_at', '<', $startDate)
            ->latest()
            ->first();

        $statusChanges = Log::query()
            ->where('action', 'status_change')
            ->where('model_type', Store::class)
            ->where('model_id', $store->id)
            ->whereBetween('created_at', [$startDate, $endDate->copy()->endOfDay()])
            ->orderBy('created_at')
            ->get();

        $currentStatus = data_get(
            $statusBeforeStart?->details,
            'new_status',
            data_get($statusChanges->first()?->details, 'old_status', $store->status === 'suspended' ? 'suspended' : 'active')
        );

        $inactiveDates = collect();
        $cursor = $startDate->copy();

        foreach ($statusChanges as $change) {
            $changedAt = Carbon::parse($change->created_at)->startOfDay();
            if ($currentStatus === 'suspended') {
                $inactiveEnd = $changedAt->copy()->subDay();
                if ($cursor->lessThanOrEqualTo($inactiveEnd)) {
                    foreach (CarbonPeriod::create($cursor, $inactiveEnd) as $date) {
                        $inactiveDates->push($date->toDateString());
                    }
                }
            }

            $currentStatus = data_get($change->details, 'new_status', $currentStatus);
            $cursor = $changedAt;
        }

        if ($currentStatus === 'suspended') {
            foreach (CarbonPeriod::create($cursor, $endDate) as $date) {
                $inactiveDates->push($date->toDateString());
            }
        }

        return $inactiveDates->unique()->values();
    }


    public function requiredShiftsForBusinessDate(Store $store, string $businessDate): int
    {
        $maxShifts = $this->maxShiftsPerBusinessDate($store);

        if ($maxShifts === 1) {
            return 1;
        }

        $balances = DailyBalance::query()
            ->where('store_id', $store->id)
            ->whereNotNull('end_time')
            ->where(function ($query) {
                $query->whereNull('notes')
                    ->orWhere('notes', 'not like', '%إغلاق تلقائي لشفت مكتمل بدون بيانات%');
            })
            ->where(function ($query) use ($businessDate) {
                $query->whereDate('business_date', $businessDate)
                    ->orWhere(function ($legacyQuery) use ($businessDate) {
                        $legacyQuery->whereNull('business_date')
                            ->whereDate('start_time', $businessDate);
                    });
            })
            ->get(['next_shift_business_date', 'next_shift_decision']);

        $closedShifts = $balances->count();

        if ($closedShifts > 0 && $balances->contains(function (DailyBalance $balance) use ($businessDate) {
            return $balance->next_shift_decision === 'next_business_date'
                && $balance->next_shift_business_date
                && Carbon::parse($balance->next_shift_business_date)->toDateString() > $businessDate;
        })) {
            return max(1, min($closedShifts, $maxShifts));
        }

        // عند ترقية متجر من شفت واحد إلى شفتين لا نطبّق النظام الجديد بأثر رجعي
        // على الأيام السابقة لتاريخ التعديل؛ وإلا سيظهر طلب شفت ثانٍ لأيام كانت تعمل كشفت واحد.
        $shiftSettingsChangedAt = app(ShiftSettingsHistoryService::class)->firstUpgradeToTwoShiftsDate($store);

        if ($closedShifts <= 1
            && $shiftSettingsChangedAt
            && $businessDate < $shiftSettingsChangedAt
        ) {
            return 1;
        }

        return $maxShifts;
    }


    public function maxShiftsPerBusinessDate(Store $store): int
    {
        $configured = (int) ($store->number_of_shifts ?: 1);

        // نظام الشفتات في التطبيق يدعم شفتًا واحدًا أو شفتين فقط؛ أي قيمة أقدم أعلى من ذلك تُعامل كشفتين.
        return max(1, min(2, $configured));
    }

    private function lastClosedBalance(int $storeId): ?DailyBalance
    {
        return DailyBalance::query()
            ->where('store_id', $storeId)
            ->whereNotNull('end_time')
            ->latest('end_time')
            ->first();
    }

    private function balanceBusinessDate(DailyBalance $balance): ?string
    {
        if ($balance->business_date) {
            return Carbon::parse($balance->business_date)->toDateString();
        }

        if ($balance->start_time) {
            return Carbon::parse($balance->start_time)->toDateString();
        }

        return $balance->created_at ? Carbon::parse($balance->created_at)->toDateString() : null;
    }

    private function closedShiftsCount(int $storeId, string $businessDate): int
    {
        return DailyBalance::query()
            ->where('store_id', $storeId)
            ->whereNotNull('end_time')
            ->where(function ($query) {
                $query->whereNull('notes')
                    ->orWhere('notes', 'not like', '%إغلاق تلقائي لشفت مكتمل بدون بيانات%');
            })
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
