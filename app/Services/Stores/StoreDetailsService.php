<?php

namespace App\Services\Stores;

use App\Data\Finance\StoreFinancialSummary;
use App\Models\Absence;
use App\Models\DailyBalance;
use App\Models\Debt;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\Withdrawal;
use App\Services\Accounting\FinancialSummaryService;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class StoreDetailsService
{
    /**
     * يبني بيانات صفحة تفاصيل المتجر بعيدًا عن StoreController.
     */
    public function build(Store $store): array
    {
        $now = now();
        $today = today();
        $monthStart = $now->copy()->startOfMonth();
        $monthEnd = $now->copy()->endOfMonth();

        $financialSummaryService = app(FinancialSummaryService::class);

        // ===== 1. إحصائيات المخزون =====
        $categoriesCount = $store->categories()->count();
        $productsCount = $store->products()->sellable()->count();

        $lowStockProducts = $store->products()->sellable()->lowStock()->get();
        $lowStockCount = $lowStockProducts->count();
        $trashedCount = $store->products()->onlyTrashed()->count();
        $latestProducts = $store->products()->sellable()->latest()->take(5)->get();

        $latestMovements = StockMovement::where('store_id', $store->id)->latest()->take(5)->get();

        // قيمة المخزون: (الكمية / الطول) * السعر للمتري، أو (الكمية * السعر) للعادي
        $totalInventoryValue = $store->products()->sellable()->selectRaw('SUM(
            CASE
                WHEN product_type = "fractional" AND roll_length > 0 THEN (quantity / roll_length) * price
                ELSE (quantity * price)
            END
        ) as total_value')->value('total_value') ?? 0;

        $metersAvailable = $store->products()->sellable()->sum('quantity');

        // ===== 2. إحصائيات الموظفين والديون =====
        $totalEmployees = $store->employees()->count();
        $totalAccountants = $store->accountants()->count();
        $totalMonthlySalaries = $store->employees()->sum('salary') ?? 0;

        $monthlyWithdrawals = $this->monthlyPendingWithdrawals($store, $monthStart, $monthEnd);
        $monthlyAbsences = $this->monthlyPendingAbsences($store, $monthStart, $monthEnd);

        $activeEmployees = $store->employees()->where('status', 'active')->count();
        $suspendedEmployees = $store->employees()->where('status', 'suspended')->count();
        $topSalaries = $store->employees()->orderBy('salary', 'desc')->take(5)->get(['name', 'salary', 'status']);

        // الموظفون الأكثر مديونية وغياباً
        $mostDebtEmployees = $this->rankEmployeesByAggregate(
            Debt::query()
                ->where('store_id', $store->id)
                ->where('status', 'pending')
                ->where('person_type', Employee::class),
            'SUM(amount)',
            'total_debt'
        );
        $mostAbsentEmployees = $this->rankEmployeesByAggregate(
            Absence::query()
                ->where('store_id', $store->id)
                ->where('status', 'pending')
                ->where('person_type', Employee::class),
            'COUNT(*)',
            'absence_count'
        );

        // ===== 3. إحصائيات المبيعات والمصروفات (بتاريخ موحد) =====
        $monthlyFinancialSummary = $financialSummaryService->storeSummariesForPeriod(
            collect([$store->id]),
            $monthStart,
            $monthEnd,
            ['cash', 'card', 'credit', 'mixed']
        );
        $todayFinancialSummary = $financialSummaryService->storeSummariesForPeriod(
            collect([$store->id]),
            $today,
            $today,
            ['cash', 'card', 'credit', 'mixed']
        );

        $monthlyStoreMetrics = $monthlyFinancialSummary->summariesByStore->get($store->id) ?? $this->emptyFinancialSummary($store->id);
        $todayStoreMetrics = $todayFinancialSummary->summariesByStore->get($store->id) ?? $this->emptyFinancialSummary($store->id);
        $monthlySales = $monthlyStoreMetrics->sales;
        $todaySales = $todayStoreMetrics->sales;
        $monthlyDebts = $monthlyStoreMetrics->employeeDebtBalance;
        $creditSales = $monthlyStoreMetrics->employeeCreditOutstanding;
        $monthlyCollections = $monthlyStoreMetrics->employeeCreditCollections;

        $cashSales = $this->salesTotalByTypeForPeriod($store, 'cash', $monthStart, $monthEnd);
        $cardSales = $this->salesTotalByTypeForPeriod($store, 'card', $monthStart, $monthEnd);
        $creditSalesToday = $this->salesTotalByTypeForPeriod($store, 'credit', $monthStart, $monthEnd);

        $monthlyExpenses = $monthlyStoreMetrics->expenses;
        $todayExpenses = $todayStoreMetrics->expenses;

        // ===== 4. إحصائيات الربحية =====
        $monthlyProfit = $monthlyStoreMetrics->profit();
        $monthlyOperatingExpenses = $monthlyExpenses;
        $totalMonthlyCosts = $totalMonthlySalaries + $monthlyOperatingExpenses;
        $monthlyNetProfit = $monthlyProfit - $totalMonthlySalaries;

        $profitMargin = ($monthlySales > 0) ? ($monthlyNetProfit / $monthlySales) * 100 : 0;
        $dailyAverageProfit = ($now->day > 0) ? ($monthlyNetProfit / $now->day) : $monthlyNetProfit;
        $costToRevenueRatio = ($monthlySales > 0) ? ($totalMonthlyCosts / $monthlySales) * 100 : 0;

        // ===== 5. الموازنات والبيانات الأخرى =====
        $lastBalance = DailyBalance::where('store_id', $store->id)->latest()->first();
        $monthlyBalancesQuery = DailyBalance::where('store_id', $store->id);
        $financialSummaryService->applyAccountingPeriodToTable($monthlyBalancesQuery, 'daily_balances', $monthStart, $monthEnd);
        $monthlyDifferences = (clone $monthlyBalancesQuery)->sum('difference');
        $monthlyShifts = (clone $monthlyBalancesQuery)->count();

        $averageProductPrice = $store->products()->avg('price') ?? 0;
        $productsWithoutImages = $store->products()->where(fn($q) => $q->whereNull('image')->orWhere('image', ''))->count();
        $lowStockPercentage = $productsCount > 0 ? round(($lowStockCount / $productsCount) * 100, 2) : 0;
        $monthlyMovementsQuery = StockMovement::where('store_id', $store->id);
        $financialSummaryService->applyAccountingPeriodToTable($monthlyMovementsQuery, 'stock_movements', $monthStart, $monthEnd);
        $monthlyMovements = $monthlyMovementsQuery->count();
        $mostActiveProducts = $store->products()->withCount('stockMovements')->orderBy('stock_movements_count', 'desc')->take(5)->get();
        $categoryStats = $store->categories()->withCount('products')->orderBy('products_count', 'desc')->take(5)->get();
        $todayInvoices = Invoice::whereHas('sale', function ($saleQuery) use ($store, $today) {
            $saleQuery->where('store_id', $store->id)
                ->collectedDashboardSales()
                ->forAccountingDate($today->toDateString());
        })->count();
        $averageInvoiceValue = $todayInvoices > 0 ? $todaySales / $todayInvoices : 0;

        return compact(
            'store', 'categoriesCount', 'productsCount', 'lowStockProducts', 'lowStockCount', 'trashedCount', 'latestProducts', 'latestMovements',
            'totalInventoryValue', 'averageProductPrice', 'productsWithoutImages', 'lowStockPercentage', 'monthlyMovements', 'mostActiveProducts', 'categoryStats',
            'totalEmployees', 'totalAccountants', 'totalMonthlySalaries', 'monthlyWithdrawals', 'monthlyDebts', 'monthlyAbsences', 'creditSales', 'monthlyCollections',
            'activeEmployees', 'suspendedEmployees', 'topSalaries', 'mostDebtEmployees', 'mostAbsentEmployees',
            'todaySales', 'monthlySales', 'todayInvoices', 'averageInvoiceValue', 'cashSales', 'cardSales', 'creditSalesToday',
            'monthlyProfit', 'monthlyOperatingExpenses', 'monthlyNetProfit', 'profitMargin', 'dailyAverageProfit', 'totalMonthlyCosts', 'costToRevenueRatio',
            'monthlyExpenses', 'todayExpenses', 'lastBalance', 'monthlyDifferences', 'monthlyShifts', 'metersAvailable'
        );
    }


    private function monthlyPendingWithdrawals(Store $store, CarbonInterface $monthStart, CarbonInterface $monthEnd): float
    {
        // السحوبات مرتبطة بالشفت ماليًا، لذلك نستخدم التاريخ عند توفره.
        return (float) Withdrawal::query()
            ->where('store_id', $store->id)
            ->where('status', 'pending')
            ->betweenAccountingDates($monthStart, $monthEnd)
            ->sum('amount');
    }

    private function emptyFinancialSummary(int $storeId): StoreFinancialSummary
    {
        return new StoreFinancialSummary(
            storeId: $storeId,
            sales: 0.0,
            productsCost: 0.0,
            expenses: 0.0,
            ownerPurchases: 0.0,
            internalUse: 0.0,
        );
    }

    private function monthlyPendingAbsences(Store $store, CarbonInterface $monthStart, CarbonInterface $monthEnd): int
    {
        // الغياب يحسب بعدد أيام العملية الفعلية داخل الشهر المعروض.
        return (int) Absence::query()
            ->where('store_id', $store->id)
            ->where('status', 'pending')
            ->betweenOperationDates($monthStart, $monthEnd)
            ->count();
    }

    private function salesTotalByTypeForPeriod(Store $store, string $saleType, CarbonInterface $periodStart, CarbonInterface $periodEnd): float
    {
        return (float) Sale::query()
            ->where('store_id', $store->id)
            ->where('sale_type', $saleType)
            ->excludeManualInvoiceEntries()
            ->betweenAccountingDates($periodStart, $periodEnd)
            ->sum('paid_amount');
    }

    private function rankEmployeesByAggregate(Builder $baseQuery, string $aggregateExpression, string $resultKey): Collection
    {
        $rankedRows = $baseQuery
            ->selectRaw("person_id, {$aggregateExpression} as aggregate_value")
            ->groupBy('person_id')
            ->orderByDesc('aggregate_value')
            ->take(5)
            ->get();

        $employeeNamesById = Employee::query()
            ->whereIn('id', $rankedRows->pluck('person_id')->filter())
            ->pluck('name', 'id');

        return $rankedRows->map(fn ($rankedRow) => [
            'name' => $employeeNamesById[$rankedRow->person_id] ?? 'غير معروف',
            $resultKey => $rankedRow->aggregate_value,
        ]);
    }
}
