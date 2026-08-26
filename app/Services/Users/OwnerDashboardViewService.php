<?php

namespace App\Services\Users;

use App\Services\Accounting\FinancialSummaryService;
use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;

class OwnerDashboardViewService
{
    public function storeBreakdowns(
        Collection $stores,
        array $monthlyMetrics,
        Collection $salariesByStore,
        array $includedSaleTypes,
        Carbon $dashboardDate
    ): array
    {
        $storeIds = $stores->pluck('id');
        $dailyFinancialSummary = app(FinancialSummaryService::class)->storeSummariesForPeriod(
            $storeIds,
            $dashboardDate->copy()->startOfDay(),
            $dashboardDate->copy()->endOfDay(),
            $includedSaleTypes
        );

        return $stores->map(function ($store) use ($dailyFinancialSummary, $salariesByStore, $monthlyMetrics) {
            $storeId = $store->id;
            $dailyMetrics = $dailyFinancialSummary->summariesByStore->get($storeId);
            $salesToday = (float) ($dailyMetrics?->sales ?? 0);
            $productsCostToday = (float) ($dailyMetrics?->productsCost ?? 0);
            $month = $monthlyMetrics[$storeId] ?? [];

            return array_merge([
                'store_id' => $storeId,
                'store_name' => $store->name,
                // المصروفات تعرض منفصلة ولا تخصم من ربح اليوم.
                'profit_today' => $salesToday - $productsCostToday,
                'sales_today' => $salesToday,
                'expenses_today' => (float) ($dailyMetrics?->expenses ?? 0),
                'products_cost_today' => $productsCostToday,
                'salaries_month' => (float) ($salariesByStore[$storeId] ?? 0),
            ], $month);
        })->values()->all();
    }
}
