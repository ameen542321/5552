<?php

namespace App\Services\Shifts;

use App\Models\Expense;
use App\Models\Sale;
use App\Models\Store;
use App\Models\User;
use App\Models\Withdrawal;
use App\Services\ShiftLifecycleService;
use App\Services\Stores\ActiveAccountantService;
use App\Services\Stores\StoreAccessService;

class ShiftGapOverviewService
{
    /**
     * يجهز بيانات صفحة مراجعة الشفتات الناقصة للمالك بدل بنائها داخل StoreController.
     */
    public function ownerOverview(Store $store, User $owner): array
    {
        if (! app(StoreAccessService::class)->isUsableForShiftWorkflow($store)) {
            return [
                'gapRows' => collect(),
                'activeAccountants' => collect(),
            ];
        }

        $activeAccountants = app(ActiveAccountantService::class)->activeAccountantsForStore($store, $owner);
        $onlyAccountant = $activeAccountants->count() === 1 ? $activeAccountants->first() : null;
        $missingDates = app(ShiftLifecycleService::class)->missingBusinessDates($store->id);
        $gapRows = collect($missingDates)->map(function (string $businessDate) use ($store, $onlyAccountant) {
            $shiftInfo = app(ShiftGapInfoService::class)->shiftInfo($store, $businessDate);
            $missingShiftNumber = (int) $shiftInfo['missing_shift_number'];
            $activeRequest = app(ShiftGapRequestService::class)->activeDetails($store->id, $businessDate, $missingShiftNumber);

            return array_merge(
                [
                    'date' => $businessDate,
                    'request_status' => data_get($activeRequest, 'status'),
                    'accountant_name' => data_get($activeRequest, 'accountant_name') ?: $onlyAccountant?->name,
                ],
                $shiftInfo,
                $this->operationCounts($store, $businessDate)
            );
        });

        return [
            'gapRows' => $gapRows,
            'activeAccountants' => $activeAccountants,
        ];
    }

    /**
     * يحسب عمليات يوم محاسبي ناقص غير مربوطة بموازنة مغلقة بعد.
     */
    public function operationCounts(Store $store, string $businessDate): array
    {
        return [
            'sales_count' => Sale::where('store_id', $store->id)
                ->forAccountingDate($businessDate)
                ->whereNull('daily_balance_id')
                ->count(),
            'expenses_count' => Expense::where('store_id', $store->id)
                ->forAccountingDate($businessDate)
                ->whereNull('daily_balance_id')
                ->count(),
            'withdrawals_count' => Withdrawal::where('store_id', $store->id)
                ->forAccountingDate($businessDate)
                ->whereNull('daily_balance_id')
                ->count(),
        ];
    }

}
