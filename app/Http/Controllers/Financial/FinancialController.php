<?php

namespace App\Http\Controllers\Financial;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Services\Accounting\FinancialSummaryService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class FinancialController extends Controller
{
    private const COLLECTED_SALE_TYPES = ['cash', 'card', 'credit', 'mixed'];

    /**
     * لوحة التحكم المالية (إحصائيات عامة)
     */
    public function index()
    {
        $storeIds = auth()->user()->stores->pluck('id');
        $periodStart = Carbon::create(1970, 1, 1)->startOfDay();
        $periodEnd = now()->endOfDay();

        $financialSummaryService = app(FinancialSummaryService::class);
        $financialTotals = $financialSummaryService->storeSummariesForPeriod(
            $storeIds,
            $periodStart,
            $periodEnd,
            self::COLLECTED_SALE_TYPES
        )->totals();

        $todayFinancialTotals = $financialSummaryService->storeSummariesForPeriod(
            $storeIds,
            today(),
            today(),
            self::COLLECTED_SALE_TYPES
        )->totals();

        $totalSales = $financialTotals->sales;
        $todaySales = $todayFinancialTotals->sales;
        $totalCost = $financialTotals->productsCost;
        $profit = $totalSales - $totalCost;

        $salesByType = Sale::query()
            ->whereIn('store_id', $storeIds)
            ->whereIn('sale_type', self::COLLECTED_SALE_TYPES)
            ->excludeManualInvoiceEntries()
            ->betweenAccountingDates($periodStart, $periodEnd)
            ->selectRaw('sale_type, COALESCE(SUM(paid_amount), 0) as total')
            ->groupBy('sale_type')
            ->pluck('total', 'sale_type');

        return view('financial.index', compact(
            'totalSales',
            'todaySales',
            'totalCost',
            'profit',
            'salesByType'
        ));
    }

    /**
     * تقرير حسب التاريخ
     */
    public function reportByDate($from, $to)
    {
        $storeIds = auth()->user()->stores->pluck('id');

        $totalsByType = Sale::query()
            ->whereIn('store_id', $storeIds)
            ->whereIn('sale_type', self::COLLECTED_SALE_TYPES)
            ->excludeManualInvoiceEntries()
            ->betweenAccountingDates($from, $to)
            ->selectRaw('sale_type, COALESCE(SUM(paid_amount), 0) as total')
            ->groupBy('sale_type')
            ->pluck('total', 'sale_type');

        return response()->json([
            'total' => (float) $totalsByType->sum(),
            'cash' => (float) ($totalsByType['cash'] ?? 0),
            'card' => (float) ($totalsByType['card'] ?? 0),
            'credit' => (float) ($totalsByType['credit'] ?? 0),
        ]);
    }

    /**
     * تقرير الأرباح والخسائر
     */
    public function profitLoss()
    {
        $storeIds = auth()->user()->stores->pluck('id');
        $financialTotals = app(FinancialSummaryService::class)->storeSummariesForPeriod(
            $storeIds,
            Carbon::create(1970, 1, 1)->startOfDay(),
            now()->endOfDay(),
            self::COLLECTED_SALE_TYPES
        )->totals();

        $totalSales = $financialTotals->sales;
        $totalCost = $financialTotals->productsCost;
        $profit = $totalSales - $totalCost;

        return view('financial.profit-loss', compact(
            'totalSales',
            'totalCost',
            'profit'
        ));
    }

    /**
     * أعلى المنتجات مبيعًا
     */
    public function topProducts()
    {
        $storeIds = auth()->user()->stores->pluck('id');

        $topProducts = DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->leftJoin('products', 'products.id', '=', 'sale_items.product_id')
            ->whereIn('sales.store_id', $storeIds)
            ->whereIn('sales.sale_type', self::COLLECTED_SALE_TYPES)
            ->where(function ($q) {
                $q->whereNull('sales.description')
                    ->orWhere('sales.description', '!=', 'manual_invoice_entry');
            })
            ->where(function ($q) {
                $q->where('sale_items.product_usage_snapshot', \App\Models\Product::USAGE_TYPE_SALE)
                    ->orWhere(function ($legacy) {
                        $legacy->whereNull('sale_items.product_usage_snapshot')
                            ->where('products.usage_type', \App\Models\Product::USAGE_TYPE_SALE);
                    })
                    ->orWhere(function ($legacyNull) {
                        $legacyNull->whereNull('sale_items.product_usage_snapshot')
                            ->whereNull('products.usage_type');
                    });
            })
            ->select(
                'sale_items.product_id',
                DB::raw("COALESCE(sale_items.product_name_snapshot, products.name, 'منتج محذوف') as product_name"),
                DB::raw('SUM(COALESCE(sale_items.quantity_snapshot, sale_items.quantity)) as total_qty')
            )
            ->groupBy('sale_items.product_id', 'sale_items.product_name_snapshot', 'products.name')
            ->orderByDesc('total_qty')
            ->take(10)
            ->get();

        return response()->json($topProducts);
    }
}
