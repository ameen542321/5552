<?php

namespace App\Services\Reports;

use App\Data\Finance\StoreFinancialSummary;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\Store;
use App\Models\StoreTransfer;
use App\Models\StockMovement;
use App\Modules\PurchaseOrders\Models\StorePurchaseOrder;
use App\Services\Accounting\FinancialSummaryService;
use App\Services\Employees\EmployeePayrollService;

class MonthlyStoreReportService
{
    public function netRecognizedProfit(
        float $recognizedSalesProfit,
        float $internalUse,
        float $ownerPurchases,
        float $expenses
    ): float {
        return $recognizedSalesProfit - $internalUse - $ownerPurchases - $expenses;
    }

    public function unallocatedCollectedSales(float $totalSales, float $cashSales, float $cardSales): float
    {
        return round($totalSales - $cashSales - $cardSales, 2);
    }

    /**
     * بناء اسم واضح للتقرير الشهري بنفس أسلوب التقرير اليومي مع توضيح نوع التقرير.
     */
    public function buildMonthlyReportTitle(string $storeName, string $month, bool $isDetailed): string
    {
        $reportType = $isDetailed ? 'مفصل' : 'مختصر';

        return "تقرير شهري {$reportType} متجر {$storeName} {$month}";
    }

    /**
     * تحويل اسم التقرير إلى اسم ملف آمن مع الحفاظ على الأحرف العربية قدر الإمكان.
     */
    public function buildSafeReportFileName(string $reportTitle, int $storeId): string
    {
        $safeReportTitle = preg_replace('/[^\p{Arabic}\p{L}\p{N}\-_ ]+/u', '', $reportTitle) ?: 'تقرير_شهري_متجر';
        $safeReportTitle = trim(preg_replace('/\s+/u', ' ', $safeReportTitle));
        $safeReportTitle = str_replace(' ', '_', $safeReportTitle);

        return 'Report_' . $safeReportTitle . '_' . time() . '_' . $storeId . '.pdf';
    }

    /**
     * تجهيز بيانات التقرير الشهري في مكان واحد للصفحة والـ PDF.
     */
    public function buildMonthlyReportData(Store $store, string $month, $start, $end, bool $withDetails): array
    {
        $includedSaleTypes = ['cash', 'card', 'credit', 'mixed'];
        $salesQuery = Sale::where('store_id', $store->id)
            ->collectedDashboardSales()
            ->betweenAccountingDates($start, $end);

        $storeFinancialMetrics = app(FinancialSummaryService::class)->storeSummariesForPeriod(
            collect([$store->id]),
            $start,
            $end,
            $includedSaleTypes
        )->summariesByStore->get($store->id) ?? $this->emptyFinancialSummary($store->id);

        $internalUseSales = $storeFinancialMetrics->internalUse;
        $ownerPurchases = $storeFinancialMetrics->ownerPurchases;
        $monthlySoldProductsCost = $storeFinancialMetrics->productsCost;
        $profitRecognitionStats = app(\App\Services\Accounting\ProfitRecognitionService::class)->forStoreAccountingPeriod($store->id, $start, $end, $includedSaleTypes);
        $recognizedProductsCost = $profitRecognitionStats['recognized_cost'];
        $uncoveredProductsCost = $profitRecognitionStats['uncovered_cost'];
        $recognizedSalesProfit = $profitRecognitionStats['recognized_profit'];
        $deferredProfit = $profitRecognitionStats['deferred_profit'];
        $profitDeductionTotal = $recognizedProductsCost;
        $totalConsumption = $storeFinancialMetrics->purchasesAndInternalUse();
        $expensesTotal = $storeFinancialMetrics->expenses;
        $withdrawalsQuery = \App\Models\Withdrawal::where('store_id', $store->id);
        app(FinancialSummaryService::class)->applyAccountingPeriodToTable($withdrawalsQuery, 'employee_withdrawals', $start, $end);
        $withdrawalsTotal = (float) $withdrawalsQuery->sum('amount');
        $monthlySalaries = app(EmployeePayrollService::class)->proratedSalariesTotalForStore($store->id, $start, $end);
        $inventoryValuation = $this->monthlyInventoryValuation($store->id, $start, $end);
        $inventoryValueStart = $inventoryValuation['opening_value'];
        $inventoryValueEnd = $inventoryValuation['closing_value'];
        $inventoryValueDifference = $inventoryValueEnd - $inventoryValueStart;
        $totalSales = $storeFinancialMetrics->sales;
        $netAfterCosts = $this->netRecognizedProfit(
            $recognizedSalesProfit,
            $internalUseSales,
            $ownerPurchases,
            $expensesTotal
        );
        $mixedSalesQuery = (clone $salesQuery)
            ->where('cash_amount', '>', 0)
            ->where('card_amount', '>', 0);
        $cashSales = (float) (clone $salesQuery)->sum('cash_amount');
        $cardSales = (float) (clone $salesQuery)->sum('card_amount');
        $salesReconciliationRows = (clone $salesQuery)
            ->whereRaw('ABS(COALESCE(paid_amount, 0) - COALESCE(cash_amount, 0) - COALESCE(card_amount, 0)) >= 0.01')
            ->orderBy('business_date')
            ->orderBy('id')
            ->get(['id', 'business_date', 'created_at', 'paid_amount', 'cash_amount', 'card_amount'])
            ->map(fn (Sale $sale) => [
                'id' => $sale->id,
                'date' => optional($sale->business_date ?? $sale->created_at)->format('Y-m-d'),
                'paid' => (float) $sale->paid_amount,
                'cash' => (float) $sale->cash_amount,
                'card' => (float) $sale->card_amount,
                'difference' => round((float) $sale->paid_amount - (float) $sale->cash_amount - (float) $sale->card_amount, 2),
            ]);
        $employeeRows = app(EmployeePayrollService::class)->monthlyRowsForStore($store->id, $month, $start, $end);
        $transferRows = $this->monthlyTransferRows($store->id, $start, $end);
        $approvedSupplySummary = $this->monthlyApprovedSupplySummary($store->id, $start, $end);

        $data = [
            'store' => $store,
            'month' => $month,
            'start' => $start,
            'end' => $end,
            'totalSales' => $totalSales,
            'operationsCount' => (int) (clone $salesQuery)->count(),
            // المكس موزع أصلًا بين cash_amount وcard_amount؛ mixedSales قيمة توضيحية ولا تجمع مرة ثالثة.
            'cashSales' => $cashSales,
            'cardSales' => $cardSales,
            'mixedSales' => (float) (clone $mixedSalesQuery)->sum('paid_amount'),
            'mixedSalesCount' => (int) (clone $mixedSalesQuery)->count(),
            'unallocatedCollectedSales' => $this->unallocatedCollectedSales($totalSales, $cashSales, $cardSales),
            'salesReconciliationRows' => $salesReconciliationRows,
            'internalUseSales' => $internalUseSales,
            'ownerPurchases' => $ownerPurchases,
            'monthlySoldProductsCost' => $monthlySoldProductsCost,
            'recognizedProductsCost' => $recognizedProductsCost,
            'uncoveredProductsCost' => $uncoveredProductsCost,
            'recognizedSalesProfit' => $recognizedSalesProfit,
            'recognizedProfit' => $netAfterCosts,
            'deferredProfit' => $deferredProfit,
            'profitDeductionTotal' => $profitDeductionTotal,
            'totalConsumption' => $totalConsumption,
            'expensesTotal' => $expensesTotal,
            'withdrawalsTotal' => $withdrawalsTotal,
            'monthlySalaries' => $monthlySalaries,
            'inventoryValueStart' => $inventoryValueStart,
            'inventoryValueEnd' => $inventoryValueEnd,
            'inventoryValueDifference' => $inventoryValueDifference,
            'inventoryValueDifferenceAbs' => abs($inventoryValueDifference),
            'inventoryValueDifferenceLabel' => $inventoryValueDifference > 0 ? 'زيادة' : ($inventoryValueDifference < 0 ? 'نقصان' : 'بدون تغيير'),
            'netAfterCosts' => $netAfterCosts,
            'dailyRows' => $this->monthlyDailyRows($store->id, $start, $end),
            'transferSummary' => $this->monthlyTransferSummary($store->id, $start, $end),
            'employeeRows' => $employeeRows,
            'transferRows' => $transferRows,
            'approvedSupplySummary' => $approvedSupplySummary,
        ];

        if ($withDetails) {
            $data['ownerPurchaseRows'] = $this->monthlyOwnerPurchaseRows($store->id, $start, $end);
            $data['accountantConsumptionRows'] = $this->monthlyAccountantConsumptionRows($store->id, $start, $end);
            $data['expenseRows'] = $this->monthlyExpenseRows($store->id, $start, $end);
        }

        return $data;
    }


    /**
     * يقيم أول وآخر الشهر من أرصدة حدود الحركة، لا من جمع كامل تاريخ الحركات.
     * الرصيد محفوظ بوحدة المخزون الأساسية؛ لذلك يبقى صحيحًا عند البيع بالحبة/الطقم/المتر.
     */
    private function monthlyInventoryValuation(int $storeId, $start, $end): array
    {
        $products = Product::withTrashed()
            ->where('store_id', $storeId)
            ->get(['id', 'quantity', 'cost_price', 'usage_type', 'created_at', 'deleted_at']);

        if ($products->isEmpty()) {
            return ['opening_value' => 0.0, 'closing_value' => 0.0];
        }

        $movements = StockMovement::query()
            ->where('store_id', $storeId)
            ->whereIn('product_id', $products->pluck('id'))
            ->where(function ($query) use ($start, $end): void {
                $query->whereBetween('business_date', [$start->toDateString(), $end->toDateString()])
                    ->orWhere(function ($legacyQuery) use ($start, $end): void {
                        $legacyQuery->whereNull('business_date')->whereBetween('created_at', [$start, $end]);
                    });
            })
            ->orderByRaw('COALESCE(business_date, DATE(created_at))')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['id', 'product_id', 'balance_before', 'balance_after', 'cost_price_snapshot', 'business_date', 'created_at'])
            ->groupBy('product_id');

        $opening = 0.0;
        $closing = 0.0;

        foreach ($products as $product) {
            $productMovements = $movements->get($product->id, collect());
            $first = $productMovements->first();
            $last = $productMovements->last();
            $currentCost = (float) ($product->cost_price ?? 0);

            if (! $product->created_at || $product->created_at->lte($start)) {
                $openingQuantity = $first?->balance_before ?? (float) $product->quantity;
                $openingCost = $first?->cost_price_snapshot ?? $currentCost;
                $opening += max(0, (float) $openingQuantity) * max(0, (float) $openingCost);
            }

            // منتج مشتريات المالك لا يمثل مخزونًا في نهاية الفترة.
            if (! $product->isOwnerPurchaseOnly()) {
                $closingQuantity = $last?->balance_after ?? (float) $product->quantity;
                $closingCost = $last?->cost_price_snapshot ?? $currentCost;
                $closing += max(0, (float) $closingQuantity) * max(0, (float) $closingCost);
            }
        }

        return [
            'opening_value' => round($opening, 2),
            'closing_value' => round($closing, 2),
        ];
    }

    /**
     * لا تدخل الطلبية التقرير إلا بعد الاعتماد المخزني، ويؤخذ تاريخ وقيمة الاعتماد المحفوظان.
     */
    private function monthlyApprovedSupplySummary(int $storeId, $start, $end): array
    {
        $orders = StorePurchaseOrder::with(['items.product', 'items.matchedProduct'])
            ->where('store_id', $storeId)
            ->where('status', 'approved')
            ->whereBetween('approved_business_date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('approved_business_date')
            ->orderBy('id')
            ->get();

        $rows = $orders->map(function (StorePurchaseOrder $order): array {
            $approvedItems = $order->items->filter(fn ($item): bool => (float) ($item->quantity_received ?? 0) > 0);
            $ownerItems = $approvedItems->filter(function ($item): bool {
                // نعتمد نتيجة الاعتماد المحفوظة، لا نوع المنتج الحالي الذي قد يتغير لاحقًا.
                return (bool) $item->add_to_owner_purchases
                    || ! is_null($item->owner_purchase_id)
                    || (is_null($item->stock_quantity_before) && ! is_null($item->cost_price_after));
            });
            $stockItems = $approvedItems->diff($ownerItems);
            $lineCost = static fn ($item): float => (float) ($item->cost_price_at_receipt ?? $item->cost_price_at_order ?? 0);

            return [
                'id' => $order->id,
                'date' => $order->approved_business_date?->format('Y-m-d'),
                'supplier' => $order->supplier_name ?: 'بدون اسم مورد',
                'owner_products_count' => $ownerItems->count(),
                'owner_products_cost' => round((float) $ownerItems->sum($lineCost), 2),
                'stock_products_count' => $stockItems->count(),
                'stock_products_cost' => round((float) $stockItems->sum($lineCost), 2),
            ];
        })->map(function (array $row): array {
            $row['total_cost'] = round($row['owner_products_cost'] + $row['stock_products_cost'], 2);
            return $row;
        });

        return [
            'orders_count' => $rows->count(),
            'total_cost' => round((float) $rows->sum('total_cost'), 2),
            'owner_products_count' => (int) $rows->sum('owner_products_count'),
            'owner_products_cost' => round((float) $rows->sum('owner_products_cost'), 2),
            'stock_products_count' => (int) $rows->sum('stock_products_count'),
            'stock_products_cost' => round((float) $rows->sum('stock_products_cost'), 2),
            'rows' => $rows,
        ];
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


    /**
     * ملخص النقل المخزني المكتمل داخل الشهر لعرضه ضمن التقرير الشهري المختصر.
     */
    private function monthlyTransferSummary(int $storeId, $start, $end): array
    {
        $transfers = StoreTransfer::with('items')
            ->where('status', 'completed')
            ->whereBetween('completed_at', [$start, $end])
            ->where(function ($query) use ($storeId) {
                $query->where('sender_store_id', $storeId)
                    ->orWhere('receiver_store_id', $storeId);
            })
            ->get(['id', 'sender_store_id', 'receiver_store_id', 'status', 'completed_at']);

        $outgoing = $transfers->where('sender_store_id', $storeId);
        $incoming = $transfers->where('receiver_store_id', $storeId);
        $valueFor = fn ($collection) => (float) $collection->sum(fn ($transfer) => $transfer->items->sum(
            fn ($item) => (float) $item->normalized_quantity * (float) $item->cost_price
        ));

        $outgoingCost = $valueFor($outgoing);
        $incomingCost = $valueFor($incoming);

        return [
            'outgoing_count' => $outgoing->count(),
            'incoming_count' => $incoming->count(),
            'outgoing_cost' => $outgoingCost,
            'incoming_cost' => $incomingCost,
            'note' => 'النقل المخزني كشف تشغيلي للصادر والوارد فقط، ولا يدخل في معادلة الربح.',
        ];
    }

    /**
     * تفاصيل عمليات النقل المخزني للتقرير الشهري المفصل.
     */
    private function monthlyTransferRows(int $storeId, $start, $end)
    {
        return StoreTransfer::with(['senderStore:id,name', 'receiverStore:id,name', 'items.senderProduct:id,name', 'items.receiverProduct:id,name', 'actionBy'])
            ->where(function ($query) use ($storeId) {
                $query->where('sender_store_id', $storeId)
                    ->orWhere('receiver_store_id', $storeId);
            })
            ->where(function ($query) use ($start, $end) {
                $query->whereBetween('created_at', [$start, $end])
                    ->orWhereBetween('completed_at', [$start, $end])
                    ->orWhereBetween('rejected_at', [$start, $end])
                    ->orWhereBetween('cancelled_at', [$start, $end]);
            })
            ->orderByRaw('COALESCE(completed_at, acted_at, created_at) asc')
            ->get()
            ->flatMap(function (StoreTransfer $transfer) use ($storeId) {
                $direction = (int) $transfer->sender_store_id === (int) $storeId ? 'صادر' : 'وارد';
                $otherStore = $direction === 'صادر' ? $transfer->receiverStore?->name : $transfer->senderStore?->name;

                return $transfer->items->map(function ($item) use ($transfer, $direction, $otherStore) {
                    return [
                        'date' => optional($transfer->completed_at ?? $transfer->acted_at ?? $transfer->created_at)->format('Y-m-d'),
                        'request_date' => optional($transfer->created_at)->format('Y-m-d'),
                        'transfer_id' => $transfer->id,
                        'direction' => $direction,
                        'other_store' => $otherStore,
                        'sender_product' => $item->product_name_snapshot ?? $item->senderProduct?->name,
                        'receiver_product' => $item->receiverProduct?->name,
                        'quantity' => (float) $item->requested_quantity,
                        'normalized_quantity' => (float) $item->normalized_quantity,
                        'unit_type' => $item->unit_label_snapshot ?? $item->unit_type,
                        'cost_price' => (float) $item->cost_price,
                        'total_cost' => (float) $item->normalized_quantity * (float) $item->cost_price,
                        'status' => $transfer->status,
                        'notes' => $transfer->notes ?: $transfer->rejection_reason,
                        'action_by' => $transfer->actionBy?->name,
                    ];
                });
            })
            ->values();
    }

    /**
     * ملخص المبيعات اليومية للتقرير المفصل مع الكاش والشبكة وترقيمه يتم في العرض.
     */

    private function monthlyDailyRows(int $storeId, $start, $end)
    {
        return Sale::where('store_id', $storeId)
            ->collectedDashboardSales()
            ->betweenAccountingDates($start, $end)
            ->get(['business_date', 'created_at', 'cash_amount', 'card_amount', 'paid_amount', 'products_total', 'labor_total', 'profit', 'final_total', 'remaining_amount'])
            ->groupBy(fn (Sale $sale) => $sale->business_date?->toDateString() ?? $sale->created_at->toDateString())
            ->map(function ($sales, $day) {
                $recognition = app(\App\Services\Accounting\ProfitRecognitionService::class)->fromSales($sales);

                return (object) [
                    'day' => $day,
                    'ops_count' => $sales->count(),
                    'cash_total' => $sales->sum('cash_amount'),
                    'card_total' => $sales->sum('card_amount'),
                    'sales_total' => $sales->sum('paid_amount'),
                    'recognized_cost' => $recognition['recognized_cost'],
                    'uncovered_cost' => $recognition['uncovered_cost'],
                    'recognized_profit' => $recognition['recognized_profit'],
                    'deferred_profit' => $recognition['deferred_profit'],
                ];
            })
            ->sortBy('day')
            ->values();
    }

    private function monthlyOwnerPurchaseRows(int $storeId, $start, $end)
    {
        $ownerPurchasesQuery = Purchase::with('product:id,name')
            ->where('store_id', $storeId);

        app(FinancialSummaryService::class)->applyAccountingPeriodToTable($ownerPurchasesQuery, 'purchases', $start, $end);

        return $ownerPurchasesQuery
            ->orderBy('created_at')
            ->get(['id', 'product_id', 'purchase_name', 'quantity', 'cost', 'description', 'created_at']);
    }

    private function monthlyAccountantConsumptionRows(int $storeId, $start, $end)
    {
        return Sale::with(['items.product:id,name'])
            ->where('store_id', $storeId)
            ->where('sale_type', 'internal_use')
            ->betweenAccountingDates($start, $end)
            ->excludeManualInvoiceEntries()
            ->orderBy('created_at')
            ->get(['id', 'description', 'total', 'created_at']);
    }

    private function monthlyExpenseRows(int $storeId, $start, $end)
    {
        return \App\Models\Expense::where('store_id', $storeId)
            ->betweenAccountingDates($start, $end)
            ->orderBy('created_at')
            ->get(['id', 'description', 'amount', 'created_at']);
    }

}
