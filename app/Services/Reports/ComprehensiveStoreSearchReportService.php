<?php

namespace App\Services\Reports;

use App\Models\Absence;
use App\Models\CreditSale;
use App\Models\Debt;
use App\Models\Expense;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\Store;
use App\Models\Withdrawal;
use App\Services\Accounting\FinancialSummaryService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ComprehensiveStoreSearchReportService
{
    private const DISPLAY_ORDER = [
        'sale' => 1,
        'withdrawal' => 2,
        'debt' => 3,
        'credit_sale' => 4,
        'debt_collection' => 5,
        'credit_collection' => 6,
        'expense' => 7,
        'absence' => 8,
        'internal' => 9,
        'purchase' => 10,
        'product' => 11,
    ];

    public function build(Store $store, array $validated): array
    {
        $search = trim((string) ($validated['q'] ?? ''));
        $from = $validated['from'] ?? now()->startOfMonth()->format('Y-m-d');
        $to = $validated['to'] ?? now()->format('Y-m-d');
        $scope = $validated['scope'] ?? 'all';
        $startDate = $from . ' 00:00:00';
        $endDate = $to . ' 23:59:59';

        $sales = Sale::query()
            ->where('store_id', $store->id)
            ->collectedDashboardSales()
            ->betweenAccountingDates($startDate, $endDate)
            ->with(['accountant:id,name', 'employee:id,name', 'items.product:id,name,description,barcode'])
            ->latest()
            ->get()
            ->map(fn (Sale $sale) => $this->saleOperation($sale))
            ->filter(fn (array $row) => $this->matchesSearch($row, $search))
            ->values();

        $internal = Sale::query()
            ->where('store_id', $store->id)
            ->where('sale_type', 'internal_use')
            ->betweenAccountingDates($startDate, $endDate)
            ->excludeManualInvoiceEntries()
            ->with(['accountant:id,name', 'items.product:id,name,description,barcode'])
            ->latest()
            ->get()
            ->map(fn (Sale $sale) => $this->internalUseOperation($sale))
            ->filter(fn (array $row) => $this->matchesSearch($row, $search))
            ->values();

        $ownerPurchasesQuery = Purchase::query()
            ->where('store_id', $store->id)
            ->with('product:id,name,description,barcode');
        app(FinancialSummaryService::class)->applyAccountingPeriodToTable($ownerPurchasesQuery, 'purchases', $startDate, $endDate);
        $ownerPurchases = $ownerPurchasesQuery
            ->latest()
            ->get()
            ->map(fn (Purchase $purchase) => $this->ownerPurchaseOperation($purchase))
            ->filter(fn (array $row) => $this->matchesSearch($row, $search))
            ->values();

        $expenses = Expense::query()
            ->where('store_id', $store->id)
            ->betweenAccountingDates($startDate, $endDate)
            ->with(['user:id,name', 'employee:id,name'])
            ->latest()
            ->get()
            ->map(fn (Expense $expense) => $this->expenseOperation($expense))
            ->filter(fn (array $row) => $this->matchesSearch($row, $search))
            ->values();

        $withdrawals = Withdrawal::query()
            ->where('store_id', $store->id)
            ->betweenAccountingDates($startDate, $endDate)
            ->with(['addedBy:id,name', 'person'])
            ->latest()
            ->get()
            ->map(fn (Withdrawal $withdrawal) => $this->withdrawalOperation($withdrawal))
            ->filter(fn (array $row) => $this->matchesSearch($row, $search))
            ->values();

        $allDebtRows = Debt::query()
            ->where('store_id', $store->id)
            ->where(function ($query) {
                $query->where('amount', '!=', 0)
                    ->orWhere('status', Debt::STATUS_DEDUCTED);
            })
            ->with(['addedBy:id,name', 'person'])
            ->latest()
            ->get()
            ->map(fn (Debt $debt) => (float) $debt->amount < 0 ? $this->debtCollectionOperation($debt) : $this->debtOperation($debt))
            ->filter(fn (array $row) => $this->matchesSearch($row, $search))
            ->values();
        $debts = $allDebtRows->where('type', 'debt')->values();
        $debtCollections = $allDebtRows->where('type', 'debt_collection')->values();

        $creditSalesRaw = CreditSale::query()
            ->where('store_id', $store->id)
            ->with(['addedBy:id,name', 'person'])
            ->orderByRaw('COALESCE(date, DATE(created_at)) DESC')
            ->orderByDesc('id')
            ->get()
            ->filter(fn (CreditSale $creditSale) => $this->creditSaleTouchesReportPeriod($creditSale, $from, $to));
        $creditSales = $creditSalesRaw
            ->map(fn (CreditSale $creditSale) => $this->creditSaleOperation($creditSale, $from, $to))
            ->filter(fn (array $row) => $this->matchesSearch($row, $search))
            ->values();
        $creditCollections = CreditSale::query()
            ->where('store_id', $store->id)
            ->with(['addedBy:id,name', 'person'])
            ->get()
            ->flatMap(fn (CreditSale $creditSale) => $this->creditCollectionOperations($creditSale, $from, $to))
            ->filter(fn (array $row) => $this->matchesSearch($row, $search))
            ->values();

        $absences = Absence::query()
            ->where('store_id', $store->id)
            ->betweenOperationDates($startDate, $endDate)
            ->with(['addedBy:id,name', 'person'])
            ->latest()
            ->get()
            ->map(fn (Absence $absence) => $this->absenceOperation($absence))
            ->filter(fn (array $row) => $this->matchesSearch($row, $search))
            ->values();

        $products = Product::query()
            ->where('store_id', $store->id)
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($productQuery) use ($search) {
                    $productQuery->where('name', 'like', '%' . $search . '%')
                        ->orWhere('description', 'like', '%' . $search . '%')
                        ->orWhere('barcode', 'like', '%' . $search . '%');
                });
            })
            ->latest()
            ->limit(100)
            ->get()
            ->map(fn (Product $product) => $this->productOperation($product))
            ->filter(fn (array $row) => $this->matchesSearch($row, $search))
            ->values();

        $groups = [
            'sales' => $sales,
            'withdrawals' => $withdrawals,
            'debts' => $debts,
            'credit_sales' => $creditSales,
            'debt_collections' => $debtCollections,
            'credit_collections' => $creditCollections,
            'expenses' => $expenses,
            'absences' => $absences,
            'internal' => $internal,
            'purchases' => $ownerPurchases,
            'products' => $products,
        ];

        $selectedGroups = match ($scope) {
            'sales' => ['sales'],
            'withdrawals' => ['withdrawals'],
            'debts' => ['debts'],
            'credit_sales' => ['credit_sales'],
            'debt_collections' => ['debt_collections'],
            'credit_collections' => ['credit_collections'],
            'expenses' => ['expenses'],
            'absences' => ['absences'],
            'internal' => ['internal'],
            'purchases' => ['purchases'],
            'products' => ['products'],
            default => array_keys($groups),
        };

        $unifiedOperations = collect($selectedGroups)
            ->flatMap(fn (string $key) => $groups[$key])
            ->sortBy([
                fn (array $a, array $b) => (self::DISPLAY_ORDER[$a['type']] ?? 99) <=> (self::DISPLAY_ORDER[$b['type']] ?? 99),
                fn (array $a, array $b) => (optional($b['date'])->timestamp ?? 0) <=> (optional($a['date'])->timestamp ?? 0),
            ])
            ->take(250)
            ->values();

        $summary = [
            'sales_total' => $sales->sum('amount'),
            'sales_count' => $sales->count(),
            'withdrawals_total' => $withdrawals->sum('amount'),
            'withdrawals_count' => $withdrawals->count(),
            'debts_total' => $debts->sum('amount'),
            'debts_count' => $debts->count(),
            'credit_sales_total' => $creditSales->sum('amount'),
            'credit_sales_count' => $creditSales->count(),
            'debt_collections_total' => $debtCollections->sum('amount'),
            'debt_collections_count' => $debtCollections->count(),
            'credit_collections_total' => $creditCollections->sum('amount'),
            'credit_collections_count' => $creditCollections->count(),
            'expenses_total' => $expenses->sum('amount'),
            'expenses_count' => $expenses->count(),
            'absences_total' => $absences->sum('amount'),
            'absences_count' => $absences->count(),
            'internal_total' => $internal->sum('amount'),
            'internal_count' => $internal->count(),
            'owner_purchases_total' => $ownerPurchases->sum('amount'),
            'owner_purchases_count' => $ownerPurchases->count(),
            'products_total' => $products->sum('amount'),
            'products_count' => $products->count(),
        ];
        $summary['selected_operations_count'] = $unifiedOperations->count();
        return compact('store', 'search', 'from', 'to', 'scope', 'summary', 'unifiedOperations');
    }

    private function saleOperation(Sale $sale): array
    {
        $itemsTitle = $sale->items->map(fn ($item) => $item->historical_product_name)->filter()->implode('، ');
        $itemsDetails = $sale->items->map(fn ($item) => implode(' | ', array_filter([
            $item->historical_product_name,
            'الكمية: ' . number_format((float) $item->quantity, 2),
            'السعر: ' . number_format((float) $item->price, 2),
            'الإجمالي: ' . number_format((float) $item->total, 2),
            $item->product?->barcode ? 'باركود: ' . $item->product->barcode : null,
            $item->product?->description ? 'وصف المنتج: ' . $item->product->description : null,
        ])))->implode(PHP_EOL);

        return $this->operationRow('sale', 'مبيعات', 'bg-emerald-500/10 text-emerald-300 border-emerald-500/30', (int) $sale->id, $sale->business_date ?? $sale->created_at, $this->displayDate($sale), $itemsTitle ?: ($sale->description ?: $sale->internal_notes ?: 'عملية بيع'), $itemsDetails ?: ($sale->description ?: 'لا يوجد تفاصيل'), (float) ($sale->final_total ?? $sale->total ?? 0), $sale->accountant?->name ?: 'المحاسب', '💰 ' . number_format((float) $sale->paid_amount, 2) . ' | 🧾 كاش ' . number_format((float) $sale->cash_amount, 2) . ' | 💳 شبكة ' . number_format((float) $sale->card_amount, 2) . ' | 🔀 مكس ' . number_format((float) $sale->cash_amount + (float) $sale->card_amount, 2) . ' | 🛠️ شغل يد/ملاحظات: ' . ($sale->description ?: 'لا يوجد'));
    }

    private function internalUseOperation(Sale $sale): array
    {
        return $this->operationRow('internal', 'استهلاك داخلي', 'bg-yellow-500/10 text-yellow-300 border-yellow-500/30', (int) $sale->id, $sale->business_date ?? $sale->created_at, $this->displayDate($sale), $sale->internal_notes ?: ($sale->description ?: 'استهلاك داخلي'), $sale->items->map(fn ($item) => $item->historical_product_name)->filter()->implode('، ') ?: 'لا يوجد تفاصيل', (float) ($sale->total ?? $sale->final_total ?? 0), $sale->accountant?->name ?: 'المحاسب', 'ملاحظات: ' . ($sale->internal_notes ?: $sale->description ?: 'لا يوجد'));
    }

    private function ownerPurchaseOperation(Purchase $purchase): array
    {
        $productName = $purchase->product_name_snapshot ?? $purchase->purchase_name ?? $purchase->product?->name ?? 'مشتريات مالك';

        return $this->operationRow('purchase', 'مشتريات مالك', 'bg-orange-500/10 text-orange-300 border-orange-500/30', (int) $purchase->id, $purchase->created_at, optional($purchase->created_at)->format('Y-m-d H:i'), $productName, $purchase->description ?: $productName, (float) ($purchase->cost ?? 0), 'المالك', 'الكمية: ' . number_format((float) $purchase->quantity, 2));
    }

    private function expenseOperation(Expense $expense): array
    {
        return $this->operationRow('expense', 'مصروفات', 'bg-red-500/10 text-red-300 border-red-500/30', (int) $expense->id, $expense->business_date ?? $expense->created_at, $this->displayDate($expense), $expense->description ?: ($expense->type ?: 'مصروف'), $expense->description ?: ($expense->type ?: 'لا يوجد تفاصيل'), (float) $expense->amount, $expense->user?->name ?: $expense->employee?->name ?: 'منفذ المصروف', 'نوع المصروف: ' . ($expense->type ?: 'غير محدد'));
    }

    private function withdrawalOperation(Withdrawal $withdrawal): array
    {
        return $this->operationRow('withdrawal', 'سحوبات', 'bg-pink-500/10 text-pink-300 border-pink-500/30', (int) $withdrawal->id, $withdrawal->business_date ?? $withdrawal->date ?? $withdrawal->created_at, $this->displayDate($withdrawal), $withdrawal->description ?: ($withdrawal->person?->name ?: 'سحب'), $withdrawal->description ?: 'لا يوجد تفاصيل', (float) $withdrawal->amount, $withdrawal->addedBy?->name ?: $withdrawal->person?->name ?: 'منفذ السحب', 'الشخص: ' . ($withdrawal->person?->name ?: 'غير محدد'));
    }

    private function debtOperation(Debt $debt): array
    {
        return $this->operationRow('debt', 'مديونيات', 'bg-purple-500/10 text-purple-300 border-purple-500/30', (int) $debt->id, $debt->created_at, optional($debt->date ?? $debt->created_at)->format('Y-m-d H:i'), $debt->description ?: ($debt->person?->name ?: 'مديونية'), $debt->description ?: 'لا يوجد تفاصيل', abs((float) $debt->amount), $debt->addedBy?->name ?: $debt->person?->name ?: 'منفذ المديونية', 'الموظف: ' . ($debt->person?->name ?: 'غير محدد') . ' | الحالة: ' . ($debt->status ?: 'غير محدد'));
    }

    private function debtCollectionOperation(Debt $debt): array
    {
        return $this->operationRow('debt_collection', 'تحصيلات مديونية', 'bg-cyan-500/10 text-cyan-300 border-cyan-500/30', (int) $debt->id, $debt->created_at, optional($debt->date ?? $debt->created_at)->format('Y-m-d H:i'), $debt->description ?: 'تحصيل مديونية', $debt->description ?: 'تحصيل مديونية', abs((float) $debt->amount), $debt->addedBy?->name ?: $debt->person?->name ?: 'منفذ التحصيل', 'الموظف: ' . ($debt->person?->name ?: 'غير محدد'));
    }

    private function creditSaleOperation(CreditSale $creditSale, string $from, string $to): array
    {
        $creditTitle = $creditSale->credit_note ?: ($creditSale->description ?: ($creditSale->person?->name ?: 'بيع آجل'));
        $periodCollections = $this->creditSalePeriodCollectionsTotal($creditSale, $from, $to);
        $statusText = ((float) ($creditSale->remaining_amount ?? 0) <= 0 || $creditSale->status === CreditSale::STATUS_DEDUCTED)
            ? 'تمت تسويتها'
            : 'قائمة';
        $meta = 'الموظف: ' . ($creditSale->person?->name ?: 'غير محدد')
            . ' | المحصل في التقرير: ' . number_format($periodCollections, 2)
            . ' | المتبقي: ' . number_format((float) $creditSale->remaining_amount, 2)
            . ' | الحالة: ' . $statusText;

        return $this->operationRow('credit_sale', 'بيع آجل', 'bg-amber-500/10 text-amber-300 border-amber-500/30', (int) $creditSale->id, $creditSale->created_at, optional($creditSale->date ?? $creditSale->created_at)->format('Y-m-d H:i'), $creditTitle, $creditSale->credit_note ?: ($creditSale->description ?: 'لا يوجد تفاصيل'), (float) $creditSale->amount, $creditSale->addedBy?->name ?: $creditSale->person?->name ?: 'منفذ البيع الآجل', $meta);
    }

    private function creditSaleTouchesReportPeriod(CreditSale $creditSale, string $from, string $to): bool
    {
        if ((float) ($creditSale->remaining_amount ?? 0) > 0 && $creditSale->status !== CreditSale::STATUS_DEDUCTED) {
            return true;
        }

        $operationDate = Carbon::parse($creditSale->date ?? $creditSale->created_at);
        if ($operationDate->betweenIncluded(Carbon::parse($from)->startOfDay(), Carbon::parse($to)->endOfDay())) {
            return true;
        }

        return $this->creditSalePeriodCollectionsTotal($creditSale, $from, $to) > 0;
    }

    private function creditSalePeriodCollectionsTotal(CreditSale $creditSale, string $from, string $to): float
    {
        $start = Carbon::parse($from)->startOfDay();
        $end = Carbon::parse($to)->endOfDay();

        return (float) collect($creditSale->collection_payments ?? [])->sum(function (array $payment) use ($start, $end) {
            $date = isset($payment['date']) ? Carbon::parse($payment['date']) : null;

            return $date && $date->betweenIncluded($start, $end)
                ? (float) ($payment['amount'] ?? 0)
                : 0.0;
        });
    }

    private function creditCollectionOperations(CreditSale $creditSale, string $from, string $to): Collection
    {
        return collect($creditSale->collection_payments ?? [])->map(function (array $payment, int $index) use ($creditSale) {
            $date = Carbon::parse($payment['date'] ?? $creditSale->updated_at ?? now());
            $creditTitle = $creditSale->credit_note ?: ($payment['description'] ?? 'تحصيل بيع آجل');
            return $this->operationRow('credit_collection', 'تحصيلات آجل', 'bg-blue-500/10 text-blue-300 border-blue-500/30', (int) $creditSale->id, $date, $date->format('Y-m-d H:i'), $creditTitle, $creditSale->credit_note ?: ($creditSale->description ?: 'تحصيل بيع آجل'), (float) ($payment['amount'] ?? 0), $payment['added_by_name'] ?? $creditSale->addedBy?->name ?? 'منفذ التحصيل', 'الموظف: ' . ($creditSale->person?->name ?: 'غير محدد') . ' | أصل البيع: ' . number_format((float) $creditSale->amount, 2));
        })->filter(function (array $row) use ($from, $to) {
            $date = Carbon::parse($row['date']);
            return $date->betweenIncluded(Carbon::parse($from)->startOfDay(), Carbon::parse($to)->endOfDay());
        })->values();
    }

    private function absenceOperation(Absence $absence): array
    {
        return $this->operationRow('absence', 'غيابات', 'bg-rose-500/10 text-rose-300 border-rose-500/30', (int) $absence->id, $absence->created_at, optional($absence->date ?? $absence->created_at)->format('Y-m-d H:i'), $absence->description ?: ($absence->person?->name ?: 'غياب'), $absence->description ?: 'غياب بدون ملاحظات', (float) ($absence->penalty_amount ?? 0), $absence->addedBy?->name ?: $absence->person?->name ?: 'منفذ الغياب', 'الموظف: ' . ($absence->person?->name ?: 'غير محدد') . ' | الحالة: ' . ($absence->status ?: 'غير محدد'));
    }

    private function productOperation(Product $product): array
    {
        return $this->operationRow('product', 'منتجات', 'bg-slate-500/10 text-slate-300 border-slate-500/30', (int) $product->id, $product->updated_at ?? $product->created_at, optional($product->updated_at ?? $product->created_at)->format('Y-m-d H:i'), $product->name, $product->description ?: 'لا يوجد وصف', (float) ($product->price ?? 0), 'المخزون', 'باركود: ' . ($product->barcode ?: '—') . ' | الكمية: ' . number_format((float) $product->quantity, 2) . ' | التكلفة: ' . number_format((float) $product->cost_price, 2));
    }

    private function operationRow(string $type, string $typeLabel, string $badgeClass, int $id, $date, ?string $displayDate, string $title, string $details, float $amount, string $actor, string $meta): array
    {
        return [
            'type' => $type,
            'type_label' => $typeLabel,
            'badge_class' => $badgeClass,
            'id' => $id,
            'date' => $date ? Carbon::parse($date) : now(),
            'display_date' => $displayDate ?: optional($date)->format('Y-m-d H:i'),
            'title' => $title,
            'details' => $details,
            'amount' => $amount,
            'actor' => $actor,
            'meta' => $meta,
        ];
    }

    private function displayDate(object $operation): string
    {
        $date = $operation->business_date ?? $operation->date ?? $operation->created_at;
        $time = $operation->created_at ? Carbon::parse($operation->created_at)->format('H:i') : '00:00';
        return Carbon::parse($date)->format('Y-m-d') . ' ' . $time;
    }

    private function matchesSearch(array $row, string $search): bool
    {
        if ($search === '') {
            return true;
        }

        $haystack = implode(' ', array_filter([
            $row['type_label'] ?? null,
            $row['title'] ?? null,
            $row['details'] ?? null,
            $row['actor'] ?? null,
            $row['meta'] ?? null,
        ]));

        return str_contains($this->normalizeSearchText($haystack), $this->normalizeSearchText($search));
    }

    private function normalizeSearchText(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = str_replace(['أ', 'إ', 'آ', 'ى', 'ة', 'ؤ', 'ئ'], ['ا', 'ا', 'ا', 'ي', 'ه', 'و', 'ي'], $value);
        $value = preg_replace('/(.)\1+/u', '$1', $value) ?? $value;
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return $value;
    }
}
