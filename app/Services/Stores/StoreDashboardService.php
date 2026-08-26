<?php

namespace App\Services\Stores;

use App\Models\Accountant;
use App\Models\Category;
use App\Models\Employee;
use App\Models\Log;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Store;
use App\Services\Accounting\FinancialSummaryService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class StoreDashboardService
{
    private const INCLUDED_SALE_TYPES = ['cash', 'card', 'credit', 'mixed'];

    public function summary(Store $store): array
    {
        $now = now();

        return array_merge([
            'accountantsCount' => Accountant::where('store_id', $store->id)->count(),
            'employeesCount' => Employee::where('store_id', $store->id)->count(),
            'categoriesCount' => Category::where('store_id', $store->id)->count(),
            'productsCount' => Product::where('store_id', $store->id)->sellable()->count(),
            'consumptionCount' => $this->consumptionCount($store),
            'todaySales' => $this->billableSales($store)->forAccountingDate(today()->toDateString())->sum('paid_amount'),
            'monthSales' => $this->monthlyBillableSales($store, $now)->sum('paid_amount'),
            'invoicesCount' => $this->monthlyBillableSales($store, $now)->count(),
            'totalProfit' => $this->periodProfit($store, $now->copy()->startOfMonth(), $now->copy()->endOfMonth()),
            'topProducts' => $this->topProducts($store, $now),
            'operations' => Log::where('store_id', $store->id)
                ->with('user')
                ->latest()
                ->limit(30)
                ->get(),
        ], $this->sevenDaysChart($store));
    }

    private function billableSales(Store $store)
    {
        return $this->salesWithoutManualInvoiceEntries($store)
            ->whereIn('sale_type', self::INCLUDED_SALE_TYPES);
    }

    private function monthlyBillableSales(Store $store, Carbon $now)
    {
        return $this->billableSales($store)
            ->betweenAccountingDates($now->copy()->startOfMonth(), $now->copy()->endOfMonth());
    }

    private function salesWithoutManualInvoiceEntries(Store $store)
    {
        return Sale::where('store_id', $store->id)
            ->excludeManualInvoiceEntries();
    }

    private function consumptionCount(Store $store): int
    {
        return $this->salesWithoutManualInvoiceEntries($store)
            ->where('sale_type', 'internal_use')
            ->count();
    }


    private function periodProfit(Store $store, $periodStart, $periodEnd): float
    {
        return app(FinancialSummaryService::class)
            ->storeSummariesForPeriod(collect([$store->id]), $periodStart, $periodEnd, self::INCLUDED_SALE_TYPES)
            ->totals()
            ->profit();
    }

    private function topProducts(Store $store, Carbon $now)
    {
        return Product::where('store_id', $store->id)
            ->sellable()
            ->withCount(['saleItems as total_sold' => function ($query) use ($now) {
                $query->select(DB::raw('SUM(quantity)'))
                    ->whereHas('sale', function ($saleQuery) use ($now) {
                        $saleQuery->betweenAccountingDates($now->copy()->startOfMonth(), $now->copy()->endOfMonth());
                    });
            }])
            ->orderBy('total_sold', 'desc')
            ->take(10)
            ->get();
    }

    private function sevenDaysChart(Store $store): array
    {
        $sevenDaysStart = now()->subDays(6)->startOfDay();
        $sevenDaysEnd = now()->endOfDay();
        $sevenDaysStats = $this->billableSales($store)
            ->betweenAccountingDates($sevenDaysStart, $sevenDaysEnd)
            ->select(
                DB::raw('COALESCE(business_date, DATE(created_at)) as date'),
                DB::raw('SUM(paid_amount) as total_sales')
            )
            ->groupBy('date')
            ->pluck('total_sales', 'date');

        $chartLabels = [];
        $chartData = [];
        $profitData = [];

        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $dayName = now()->subDays($i)->translatedFormat('l');

            $chartLabels[] = $dayName;
            $chartData[] = (float) ($sevenDaysStats[$date] ?? 0);
            $profitData[] = $this->periodProfit($store, $date, $date);
        }

        return compact('chartLabels', 'chartData', 'profitData');
    }

    public function advancedStats(Store $store): array
    {
        $lowStockProducts = Product::where('store_id', $store->id)
            ->sellable()
            ->lowStock()
            ->orderBy('quantity')
            ->limit(10)
            ->get(['id', 'name', 'quantity', 'min_stock', 'price', 'product_type', 'roll_length', 'is_splittable', 'items_per_unit', 'quick_sale_default_unit']);

        return [
            'monthly_sales' => $this->advancedMonthlySales($store),
            'product_stats' => $this->productStats($store),
            'employee_stats' => $this->employeeStats($store),
            'low_stock_products' => $lowStockProducts,
            'low_stock_count' => Product::where('store_id', $store->id)->sellable()->lowStock()->count(),
        ];
    }

    private function advancedMonthlySales(Store $store)
    {
        return Sale::where('store_id', $store->id)
            ->excludeManualInvoiceEntries()
            ->select(
                DB::raw('MONTH(COALESCE(business_date, DATE(created_at))) as month'),
                DB::raw('YEAR(COALESCE(business_date, DATE(created_at))) as year'),
                DB::raw('SUM(paid_amount) as total_sales'),
                DB::raw('COUNT(*) as sales_count')
            )
            ->whereRaw('YEAR(COALESCE(business_date, DATE(created_at))) = ?', [date('Y')])
            ->groupBy('year', 'month')
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->get()
            ->map(function ($monthStats) use ($store) {
                $monthStart = Carbon::create((int) $monthStats->year, (int) $monthStats->month, 1)->startOfMonth();
                $monthStats->total_profit = $this->periodProfit($store, $monthStart, $monthStart->copy()->endOfMonth());

                return $monthStats;
            });
    }

    private function productStats(Store $store)
    {
        return Product::where('store_id', $store->id)
            ->sellable()
            ->selectRaw("
                COUNT(*) as total_products,
                SUM(quantity) as total_quantity,
                SUM(CASE
                    WHEN product_type = 'fractional' AND roll_length > 0 THEN (quantity / roll_length) * price
                    ELSE (quantity * price)
                END) as total_value,
                AVG(price) as average_price
            ")
            ->first();
    }

    private function employeeStats(Store $store)
    {
        return Employee::where('store_id', $store->id)
            ->selectRaw("
                COUNT(*) as total_employees,
                SUM(salary) as total_salary,
                AVG(salary) as average_salary
            ")
            ->first();
    }
}
