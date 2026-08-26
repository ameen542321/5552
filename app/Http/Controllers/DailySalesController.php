<?php

namespace App\Http\Controllers;

use App\Models\Absence;
use App\Models\Accountant;
use App\Models\Sale;
use App\Models\Store;
use App\Models\Product;
use App\Models\CreditSale;
use App\Models\Debt;
use App\Models\Expense;
use App\Models\Withdrawal;
use App\Models\User;
use App\Support\ProductProfitCostCalculator;
use App\Support\PaymentTypeLabel;
use App\Services\Shifts\ShiftWindowService;
use App\Services\ShiftLifecycleService;
use App\Services\EmployeeLogService;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DailySalesController extends Controller
{
    private array $processedSalesCache = [];

    public function index(Store $store, Request $request)
    {
        $selectedDate = $request->filled('date')
            ? Carbon::parse($request->date)->startOfDay()
            : Carbon::today()->startOfDay();

        if (! $request->filled('date') && $request->filled('edit_sale')) {
            $saleForEditDate = Sale::query()
                ->where('store_id', $store->id)
                ->find($request->integer('edit_sale'));

            if ($saleForEditDate) {
                $selectedDate = Carbon::parse($saleForEditDate->business_date ?: $saleForEditDate->created_at)->startOfDay();
            }
        }

        $shiftWindowService = app(ShiftWindowService::class);
        $shiftWindows = $shiftWindowService->forDate($store->id, $selectedDate);

        // fallback آمن: إذا لا توجد شفتات مغلقة/حالية نرجع لفترة يومية تقويمية
        if ($shiftWindows->isEmpty()) {
            $shiftWindows = $shiftWindowService->calendarFallback($selectedDate);
        }

        $startTime = $shiftWindows->first()['start'];
        $endTime = $shiftWindows->last()['end'];
        $selectedShift = $shiftWindows->contains(fn($shiftWindow) => ($shiftWindow['source'] ?? null) === 'balance') ? 'shift_based' : null;

        $buildSalesWithItemsQuery = function () use ($store) {
            return Sale::where('sales.store_id', $store->id)
            ->where(function ($salesDescriptionQuery) {
                $salesDescriptionQuery->whereNull('sales.description')
                    ->orWhere('sales.description', '!=', 'manual_invoice_entry');
            })
            ->leftJoin('sale_items', 'sales.id', '=', 'sale_items.sale_id')
            ->leftJoin('products', 'sale_items.product_id', '=', 'products.id')
            ->select(
                'sales.*',
                'sale_items.id as item_id',
                'sale_items.product_id',
                'sale_items.quantity as item_quantity',
                'sale_items.price as item_price',
                'sale_items.total as item_total',
                'sale_items.cost_price as item_cost_price',
                'sale_items.total_cost as item_total_cost',
                'sale_items.is_custom',
                'sale_items.custom_name',
                'sale_items.custom_consumption',
                'sale_items.custom_meters',
                'sale_items.unit_type as item_unit_type',
                DB::raw('COALESCE(sale_items.product_name_snapshot, sale_items.custom_name, products.name) as product_name'),
                'products.quantity as product_quantity',
                DB::raw('COALESCE(sale_items.sale_price_snapshot, sale_items.price, products.price) as product_price'),
                DB::raw('COALESCE(sale_items.cost_price_snapshot, sale_items.cost_price, products.cost_price) as product_cost_price'),
                DB::raw('COALESCE(sale_items.product_type_snapshot, products.product_type) as product_type'),
                DB::raw('COALESCE(sale_items.is_splittable_snapshot, products.is_splittable, 0) as product_is_splittable'),
                DB::raw('COALESCE(sale_items.items_per_unit_snapshot, products.items_per_unit, 1) as product_items_per_unit'),
                'products.piece_price as product_piece_price',
                DB::raw('COALESCE(sale_items.roll_length_snapshot, sale_items.roll_length_at_sale, products.roll_length) as product_roll_length'),
                'sale_items.unit_label_snapshot as item_unit_label_snapshot'
            )
            ->with(['employee', 'accountant']);
        };

        // استعلام المبيعات مع المنتجات
        $salesWithItemsQuery = $buildSalesWithItemsQuery();

        $shiftWindowService->applySalePeriodFilter($salesWithItemsQuery, $shiftWindows);

        // فلترة البحث (رقم العملية / اسم المنتج / اسم العنصر المخصص / وصف العملية)
        if ($request->filled('search')) {
            $search = trim((string) $request->search);

            $salesWithItemsQuery->where(function ($searchQuery) use ($search) {
                if (is_numeric($search)) {
                    $searchQuery->orWhere('sales.id', (int) $search);
                }

                $searchQuery->orWhere('sale_items.product_name_snapshot', 'like', "%{$search}%")
                    ->orWhere('products.name', 'like', "%{$search}%")
                    ->orWhere('sale_items.custom_name', 'like', "%{$search}%")
                    ->orWhere('sales.description', 'like', "%{$search}%");
            });
        }

        // تنفيذ الاستعلام
        $saleRows = $salesWithItemsQuery->orderBy('sales.created_at', 'desc')->get();

        // fallback: إذا الشفتات المغلقة أعادت نتائج صفرية نرجع للفترة اليومية لنفس التاريخ
        if ($saleRows->isEmpty() && $shiftWindows->contains(fn($shiftWindow) => ($shiftWindow['source'] ?? null) === 'balance')) {
            $shiftWindows = $shiftWindowService->calendarFallback($selectedDate, 'calendar_fallback');

            $startTime = $shiftWindows->first()['start'];
            $endTime = $shiftWindows->last()['end'];
            $selectedShift = null;

            $salesWithItemsQuery = $buildSalesWithItemsQuery();
            $shiftWindowService->applySalePeriodFilter($salesWithItemsQuery, $shiftWindows);

            if ($request->filled('search')) {
                $search = trim((string) $request->search);

                $salesWithItemsQuery->where(function ($searchQuery) use ($search) {
                    if (is_numeric($search)) {
                        $searchQuery->orWhere('sales.id', (int) $search);
                    }

                    $searchQuery->orWhere('products.name', 'like', "%{$search}%")
                        ->orWhere('sale_items.custom_name', 'like', "%{$search}%")
                        ->orWhere('sales.description', 'like', "%{$search}%");
                });
            }

            $saleRows = $salesWithItemsQuery->orderBy('sales.created_at', 'desc')->get();
        }

        // إعادة تجميع النتائج حسب كل عملية بيع
        $sales = collect();
        $groupedSale = null;

        foreach ($saleRows as $saleItemRow) {
            if (!$groupedSale || $groupedSale->id != $saleItemRow->id) {
                if ($groupedSale) {
                    $processed = $this->processSale($groupedSale);
                    $processed->shift_key = $shiftWindowService->resolveShiftKey($processed, $shiftWindows);
                    $sales->push($processed);
                }

                $groupedSale = clone $saleItemRow;
                $groupedSale->items = collect();
                $groupedSale->total_cost = 0;
                $groupedSale->total_profit = 0;
                $groupedSale->products_total_value = 0;
            }

            if ($saleItemRow->item_id) {
                $groupedSale->items->push((object)[
                    'id' => $saleItemRow->item_id,
                    'product_id' => $saleItemRow->product_id,
                    'quantity' => $saleItemRow->item_quantity,
                    'price' => $saleItemRow->item_price,
                    'total' => $saleItemRow->item_total,
                    'cost_price_at_sale' => $saleItemRow->item_cost_price,
                    'total_cost_at_sale' => $saleItemRow->item_total_cost,
                    'is_custom' => $saleItemRow->is_custom,
                    'custom_name' => $saleItemRow->custom_name,
                    'custom_consumption' => $saleItemRow->custom_consumption,
                    'custom_meters' => $saleItemRow->custom_meters,
                    'unit_type' => $saleItemRow->item_unit_type,
                    'unit_label' => $saleItemRow->item_unit_label_snapshot,
                    'product_name' => $saleItemRow->product_name ?? 'منتج غير معروف',
                    'product_quantity' => $saleItemRow->product_quantity ?? 0,
                    'product_price' => (float) ($saleItemRow->product_price ?? 0),
                    'cost_price' => $saleItemRow->product_cost_price ?? 0,
                    'product_type' => $saleItemRow->product_type,
                    'is_splittable' => (bool) ($saleItemRow->product_is_splittable ?? false),
                    'items_per_unit' => (float) ($saleItemRow->product_items_per_unit ?? 0),
                    'piece_price' => (float) ($saleItemRow->product_piece_price ?? 0),
                    'roll_length' => (float) ($saleItemRow->product_roll_length ?? 0)
                ]);

                $groupedSale->products_total_value += $saleItemRow->item_total ?? 0;
            }
        }

        if ($groupedSale) {
            $processed = $this->processSale($groupedSale);
            $processed->shift_key = $shiftWindowService->resolveShiftKey($processed, $shiftWindows);
            $sales->push($processed);
        }

        $visibleSaleIds = $sales->pluck('id')->filter()->map(fn ($id) => (int) $id)->values()->all();
        $creditContextsBySaleId = $this->getCreditContextsBySaleId($store->id, $visibleSaleIds);
        $sales = $sales->map(function ($sale) use ($creditContextsBySaleId) {
            $creditContext = $creditContextsBySaleId[(int) $sale->id] ?? [];
            $sale->credit_note = $creditContext['note'] ?? null;
            $sale->has_linked_credit = (bool) ($creditContext['has_credit'] ?? false);
            $sale->has_linked_credit_collections = (bool) ($creditContext['has_collections'] ?? false);
            return $sale;
        });
        $collectionOperations = $this->getCreditCollectionOperations($store->id, $shiftWindows, $visibleSaleIds);
        $sales = $sales
            ->concat($collectionOperations)
            ->sortByDesc(fn ($entry) => $entry->display_time ?? $entry->created_at ?? now())
            ->values();

        $forcedEditSaleForModal = null;
        if ($request->filled('edit_sale') && ! $sales->contains(fn ($sale) => (int) $sale->id === $request->integer('edit_sale'))) {
            $forcedEditSaleForModal = $this->getProcessedSaleById($store->id, $request->integer('edit_sale'));
            if ($forcedEditSaleForModal) {
                $creditContext = $this->getCreditContextsBySaleId($store->id, [(int) $forcedEditSaleForModal->id])[(int) $forcedEditSaleForModal->id] ?? [];
                $forcedEditSaleForModal->credit_note = $creditContext['note'] ?? null;
                $forcedEditSaleForModal->has_linked_credit = (bool) ($creditContext['has_credit'] ?? false);
                $forcedEditSaleForModal->has_linked_credit_collections = (bool) ($creditContext['has_collections'] ?? false);
            }
        }

        $employees = $store->employees()->select('id', 'name')->orderBy('name')->get();
        // منتجات الإضافة/الاستبدال في نافذة تعديل المبيعات: النشطة فقط وبدون التضليل/الرول.
        $editableProducts = Product::where('store_id', $store->id)
            ->where('status', 'active')
            ->sellable()
            ->where('product_type', '!=', 'fractional')
            ->withoutTintCategory()
            ->select('id', 'name', 'price', 'quantity', 'cost_price', 'product_type', 'is_splittable', 'items_per_unit', 'piece_price', 'quick_sale_default_unit')
            ->orderBy('name')
            ->get();

        $shiftSummaries = $shiftWindows->map(function ($shiftWindow) use ($sales, $store, $shiftWindowService) {
            return $this->buildShiftSummary($shiftWindow, $sales, $store, $shiftWindowService);
        })->sort(function ($firstShiftSummary, $secondShiftSummary) {
            $firstIsOpen = ($firstShiftSummary['source'] ?? null) === 'open_shift';
            $secondIsOpen = ($secondShiftSummary['source'] ?? null) === 'open_shift';

            if ($firstIsOpen !== $secondIsOpen) {
                return $firstIsOpen ? -1 : 1;
            }

            return $secondShiftSummary['start']->getTimestamp() <=> $firstShiftSummary['start']->getTimestamp();
        })->values();

        // الإحصائيات العامة عبر كل الشفتات ضمن الفترة المختارة
        $stats = [
            'total' => $shiftSummaries->sum(fn($shiftSummary) => $shiftSummary['stats']['total']),
            'total_cost' => $shiftSummaries->sum(fn($shiftSummary) => $shiftSummary['stats']['total_cost']),
            'recognized_cost' => $shiftSummaries->sum(fn($shiftSummary) => $shiftSummary['stats']['recognized_cost']),
            'uncovered_cost' => $shiftSummaries->sum(fn($shiftSummary) => $shiftSummary['stats']['uncovered_cost']),
            'total_profit' => $shiftSummaries->sum(fn($shiftSummary) => $shiftSummary['stats']['total_profit']),
            'deferred_profit' => $shiftSummaries->sum(fn($shiftSummary) => $shiftSummary['stats']['deferred_profit']),
            'cash_sales' => $shiftSummaries->sum(fn($shiftSummary) => $shiftSummary['stats']['cash_sales']),
            'card_sales' => $shiftSummaries->sum(fn($shiftSummary) => $shiftSummary['stats']['card_sales']),
            'credit_operations_count' => $shiftSummaries->sum(fn($shiftSummary) => $shiftSummary['stats']['credit_operations_count'] ?? 0),
            'credit_collections' => $shiftSummaries->sum(fn($shiftSummary) => $shiftSummary['stats']['credit_collections'] ?? 0),
            'credit_collections_cash' => $shiftSummaries->sum(fn($shiftSummary) => $shiftSummary['stats']['credit_collections_cash'] ?? 0),
            'credit_collections_card' => $shiftSummaries->sum(fn($shiftSummary) => $shiftSummary['stats']['credit_collections_card'] ?? 0),
            'debt_collections' => $shiftSummaries->sum(fn($shiftSummary) => $shiftSummary['stats']['debt_collections'] ?? 0),
            'debt_collections_cash' => $shiftSummaries->sum(fn($shiftSummary) => $shiftSummary['stats']['debt_collections_cash'] ?? 0),
            'debt_collections_card' => $shiftSummaries->sum(fn($shiftSummary) => $shiftSummary['stats']['debt_collections_card'] ?? 0),
            'tadlil_total' => $shiftSummaries->sum(fn($shiftSummary) => $shiftSummary['stats']['tadlil_total'] ?? 0),
            'tadlil_count' => $shiftSummaries->sum(fn($shiftSummary) => $shiftSummary['stats']['tadlil_count'] ?? 0),
            'tadlil_names' => $shiftSummaries
                ->flatMap(fn($shiftSummary) => $shiftSummary['stats']['tadlil_names'] ?? collect())
                ->filter()
                ->unique()
                ->values(),
            'collected_total' => $shiftSummaries->sum(fn($shiftSummary) => $shiftSummary['stats']['collected_total']),
            'expenses' => $shiftSummaries->sum(fn($shiftSummary) => $shiftSummary['stats']['expenses']),
            'withdrawals' => $shiftSummaries->sum(fn($shiftSummary) => $shiftSummary['stats']['withdrawals']),
            'outgoing_total' => $shiftSummaries->sum(fn($shiftSummary) => $shiftSummary['stats']['outgoing_total']),
            'debts' => $shiftSummaries->sum(fn($shiftSummary) => $shiftSummary['stats']['debts'] ?? 0),
            'absences' => $shiftSummaries->sum(fn($shiftSummary) => $shiftSummary['stats']['absences'] ?? 0),
            'count' => $sales->count(),
            'products_count' => $sales->filter(fn($sale) => ($sale->operation_kind ?? null) !== 'collection' && $sale->items->isNotEmpty())->count(),
            'labor_count' => $sales->filter(fn($sale) => ($sale->operation_kind ?? null) !== 'collection' && $sale->items->isEmpty())->count(),
            'shift_count' => $shiftSummaries->count(),
        ];

        return view('user.stores.daily', compact('store', 'sales', 'stats', 'startTime', 'endTime', 'selectedShift', 'shiftSummaries', 'employees', 'editableProducts', 'forcedEditSaleForModal'));
    }

    private function buildShiftSummary(array $shiftWindow, $sales, Store $store, ShiftWindowService $shiftWindowService): array
    {
        $operationsInShift = $sales->filter(fn($sale) => ($sale->shift_key ?? 'default_shift') === $shiftWindow['key']);
        $saleOperationsInShift = $operationsInShift->filter(fn($sale) => ($sale->operation_kind ?? null) !== 'collection');
        $revenueSaleOperationsInShift = $saleOperationsInShift->reject(
            fn ($sale) => ($sale->sale_type ?? null) === 'internal_use'
        );
        $collectionOperationsInShift = $operationsInShift->filter(fn($sale) => ($sale->operation_kind ?? null) === 'collection');
        $financialOperationsInShift = $this->getFinancialOperationsForShift($store, $shiftWindow, $shiftWindowService);
        $tadlilOperationsInShift = $saleOperationsInShift->filter(
            fn ($sale) => !empty($sale->tint_operation_name)
        );
        $creditOperationsInShift = $saleOperationsInShift->filter(
            fn ($sale) => ($sale->sale_type ?? null) === 'credit' || (bool) ($sale->has_partial_credit ?? false)
        );

        $cashSalesAmount = $saleOperationsInShift->sum(fn ($sale) => $this->cashAmountForSale($sale));
        $cardSalesAmount = $saleOperationsInShift->sum(fn ($sale) => $this->cardAmountForSale($sale));
        $collectionCashAmount = (float) $collectionOperationsInShift->sum(fn($sale) => (float) ($sale->cash_paid ?? 0));
        $collectionCardAmount = (float) $collectionOperationsInShift->sum(fn($sale) => (float) ($sale->card_paid ?? 0));
        $creditCollectionAmount = $collectionCashAmount + $collectionCardAmount;

        $shiftExpenseQuery = Expense::where('store_id', $store->id)
            ->where('actor_type', '!=', 'owner_purchase');
        $shiftWindowService->applyOperationWindowFilter($shiftExpenseQuery, $shiftWindow);
        $shiftExpensesAmount = (float) $shiftExpenseQuery->sum('amount');

        $shiftWithdrawalQuery = Withdrawal::where('store_id', $store->id);
        $shiftWindowService->applyOperationWindowFilter($shiftWithdrawalQuery, $shiftWindow);
        $shiftWithdrawalsAmount = (float) $shiftWithdrawalQuery->sum('amount');

        $shiftDebtsAmount = (float) $financialOperationsInShift
            ->filter(fn ($operation) => ($operation->operation_kind ?? null) === 'debt' && (float) ($operation->amount ?? 0) > 0)
            ->sum('amount');
        $debtCollectionRows = $financialOperationsInShift
            ->filter(fn ($operation) => ($operation->operation_kind ?? null) === 'debt_collection');
        $debtCollectionAmount = (float) $debtCollectionRows->sum('amount');
        $debtCollectionCashAmount = (float) $debtCollectionRows->sum(fn ($operation) => (float) ($operation->cash_amount ?? 0));
        $debtCollectionCardAmount = (float) $debtCollectionRows->sum(fn ($operation) => (float) ($operation->card_amount ?? 0));
        $shiftAbsencesCount = $financialOperationsInShift
            ->filter(fn ($operation) => ($operation->operation_kind ?? null) === 'absence')
            ->count();
        $totalReceived = $cashSalesAmount + $cardSalesAmount + $creditCollectionAmount + $debtCollectionAmount;
        $summaryCost = (float) $revenueSaleOperationsInShift->sum('recognized_cost')
            + (float) $collectionOperationsInShift->sum('recognized_cost');
        // تحصيل المديونية يزيد المستلم والكاش/الشبكة فقط، ولا يدخل في ربح أو تكلفة الشفت.
        $profitReceived = $cashSalesAmount + $cardSalesAmount + $creditCollectionAmount;

        $summaryStats = [
            'total' => $totalReceived,
            'total_cost' => $summaryCost,
            'recognized_cost' => $revenueSaleOperationsInShift->sum('recognized_cost')
                + $collectionOperationsInShift->sum('recognized_cost'),
            'uncovered_cost' => $revenueSaleOperationsInShift->sum('uncovered_cost'),
            'total_profit' => $profitReceived - $summaryCost,
            'deferred_profit' => $revenueSaleOperationsInShift->sum('deferred_profit'),
            'cash_sales' => $cashSalesAmount + $collectionCashAmount + $debtCollectionCashAmount,
            'card_sales' => $cardSalesAmount + $collectionCardAmount + $debtCollectionCardAmount,
            'sales_cash_only' => $cashSalesAmount,
            'sales_card_only' => $cardSalesAmount,
            'credit_collections_cash' => $collectionCashAmount,
            'credit_collections_card' => $collectionCardAmount,
            'debt_collections_cash' => $debtCollectionCashAmount,
            'debt_collections_card' => $debtCollectionCardAmount,
            'credit_operations_count' => $creditOperationsInShift->count(),
            'credit_collections' => $creditCollectionAmount,
            'debt_collections' => $debtCollectionAmount,
            'tadlil_total' => $tadlilOperationsInShift->sum(fn ($sale) => $this->tadlilWorkAmount($sale)),
            'tadlil_count' => $tadlilOperationsInShift->count(),
            'tadlil_names' => $tadlilOperationsInShift
                ->pluck('tint_operation_name')
                ->filter()
                ->unique()
                ->values(),
            'collected_total' => $totalReceived,
            'expenses' => $shiftExpensesAmount,
            'withdrawals' => $shiftWithdrawalsAmount,
            'debts' => $shiftDebtsAmount,
            'absences' => $shiftAbsencesCount,
            'outgoing_total' => $shiftExpensesAmount + $shiftWithdrawalsAmount,
            'count' => $operationsInShift->count(),
            'financial_count' => $financialOperationsInShift->count(),
        ];

        return [
            'key' => $shiftWindow['key'],
            'label' => $shiftWindow['label'],
            'start' => $shiftWindow['start'],
            'end' => $shiftWindow['end'],
            'source' => $shiftWindow['source'] ?? null,
            'notes' => $shiftWindow['notes'] ?? null,
            'stats' => $summaryStats,
            'financial_operations' => $financialOperationsInShift,
        ];
    }

    private function getFinancialOperationsForShift(Store $store, array $shiftWindow, ShiftWindowService $shiftWindowService)
    {
        $expenseQuery = Expense::query()
            ->where('store_id', $store->id)
            ->where('actor_type', '!=', 'owner_purchase')
            ->with(['employee', 'user']);
        $shiftWindowService->applyOperationWindowFilter($expenseQuery, $shiftWindow);

        $expenses = $expenseQuery->get()->map(fn (Expense $expense) => (object) [
            'id' => $expense->id,
            'operation_kind' => 'expense',
            'type_label' => 'مصروف',
            'title' => $expense->type ?: 'مصروف',
            'amount' => (float) $expense->amount,
            'date' => optional($expense->business_date)->toDateString() ?: $expense->created_at->toDateString(),
            'description' => $expense->description,
            'actor_name' => $this->expenseActorName($expense),
            'created_at' => $expense->created_at,
            'route_key' => 'expense',
            'badge_class' => 'text-rose-300 bg-rose-500/10 border-rose-500/30',
            'icon_class' => 'fa-solid fa-receipt',
            'icon_shell_class' => 'text-rose-300 bg-rose-500/10 border-rose-500/30',
            'amount_class' => 'text-rose-300',
        ]);

        $withdrawalQuery = Withdrawal::query()
            ->where('store_id', $store->id)
            ->with('person');
        $shiftWindowService->applyOperationWindowFilter($withdrawalQuery, $shiftWindow);

        $withdrawals = $withdrawalQuery->get()->map(fn (Withdrawal $withdrawal) => (object) [
            'id' => $withdrawal->id,
            'operation_kind' => 'withdrawal',
            'type_label' => 'سحب',
            'title' => optional($withdrawal->person)->name ?: 'سحب موظف',
            'amount' => (float) $withdrawal->amount,
            'date' => optional($withdrawal->business_date)->toDateString() ?: optional($withdrawal->date)->toDateString() ?: $withdrawal->created_at->toDateString(),
            'description' => $withdrawal->description,
            'created_at' => $withdrawal->created_at,
            'route_key' => 'withdrawal',
            'badge_class' => 'text-amber-300 bg-amber-500/10 border-amber-500/30',
            'icon_class' => 'fa-solid fa-hand-holding-dollar',
            'icon_shell_class' => 'text-amber-300 bg-amber-500/10 border-amber-500/30',
            'amount_class' => 'text-amber-300',
        ]);

        // نعرض تحصيلات المديونية كمعلومة تشغيلية فقط ولا ندخلها في إجماليات الربح/التكلفة.
        // أما إضافة المديونية نفسها فلا تظهر هنا إلا من شاشة الإغلاق/الموازنة عند اختيار المحاسب لها.
        $debts = Debt::query()
            ->where('store_id', $store->id)
            ->with('person')
            ->where('amount', '<', 0)
            ->whereBetween('date', [
                $shiftWindow['start']->toDateString(),
                $shiftWindow['end']->toDateString(),
            ])
            ->get()
            ->map(function (Debt $debt) {
                $amount = abs((float) $debt->amount);
                $cashAmount = (float) ($debt->cash_amount ?? 0);
                $cardAmount = (float) ($debt->card_amount ?? 0);

                if ($cashAmount <= 0 && $cardAmount <= 0) {
                    if (($debt->payment_method ?? 'cash') === 'card') {
                        $cardAmount = $amount;
                    } else {
                        $cashAmount = $amount;
                    }
                }

                $paymentLabel = $debt->payment_method_label ?: match ($debt->payment_method ?? 'cash') {
                    'card' => 'شبكة',
                    'mixed' => 'ميكس',
                    default => 'كاش',
                };

                return (object) [
                    'id' => $debt->id,
                    'operation_kind' => 'debt_collection',
                    'type_label' => 'تحصيل مديونية',
                    'title' => optional($debt->person)->name ?: 'تحصيل مديونية',
                    'amount' => $amount,
                    'cash_amount' => $cashAmount,
                    'card_amount' => $cardAmount,
                    'payment_label' => $paymentLabel,
                    'payment_breakdown' => trim($paymentLabel . ' - ' . number_format($cashAmount, 2) . ' كاش / ' . number_format($cardAmount, 2) . ' شبكة'),
                    'date' => optional($debt->date)->toDateString() ?: $debt->created_at->toDateString(),
                    'description' => $debt->description,
                    'created_at' => $debt->created_at,
                    'route_key' => 'debt',
                    'badge_class' => 'text-emerald-300 bg-emerald-500/10 border-emerald-500/30',
                    'icon_class' => 'fa-solid fa-hand-holding-dollar',
                    'icon_shell_class' => 'text-emerald-300 bg-emerald-500/10 border-emerald-500/30',
                    'amount_class' => 'text-emerald-300',
                ];
            });


        $absenceQuery = Absence::query()
            ->where('store_id', $store->id)
            ->with('person')
            ->whereBetween('date', [
                $shiftWindow['start']->toDateString(),
                $shiftWindow['end']->toDateString(),
            ]);

        $absences = $absenceQuery->get()->map(fn (Absence $absence) => (object) [
            'id' => $absence->id,
            'operation_kind' => 'absence',
            'type_label' => 'غياب',
            'title' => optional($absence->person)->name ?: optional($absence->employee)->name ?: 'غياب موظف',
            'amount' => (float) ($absence->penalty_amount ?? 0),
            'date' => optional($absence->date)->toDateString() ?: $absence->created_at->toDateString(),
            'description' => $absence->description,
            'created_at' => $absence->created_at,
            'route_key' => 'absence',
            'badge_class' => 'text-sky-300 bg-sky-500/10 border-sky-500/30',
            'icon_class' => 'fa-solid fa-user-clock',
            'icon_shell_class' => 'text-sky-300 bg-sky-500/10 border-sky-500/30',
            'amount_class' => 'text-sky-300',
        ]);

        return $expenses
            ->concat($withdrawals)
            ->concat($debts)
            ->concat($absences)
            ->sortByDesc(fn ($operation) => $operation->created_at)
            ->values();
    }


    private function expenseActorName(Expense $expense): string
    {
        if (($expense->actor_type ?? null) === 'accountant_expense' && $expense->user_id) {
            return Accountant::find($expense->user_id)?->name ?: 'منفذ المصروف';
        }

        if (($expense->actor_type ?? null) === 'owner_expense' && $expense->user_id) {
            return $expense->user?->name ?: User::find($expense->user_id)?->name ?: 'منفذ المصروف';
        }

        return $expense->user?->name ?: $expense->employee?->name ?: 'منفذ المصروف';
    }

    private function cashAmountForSale(object $sale): float
    {
        $cashPaidAmount = (float) ($sale->cash_paid ?? 0);
        if ($cashPaidAmount > 0) {
            return $cashPaidAmount;
        }

        if (($sale->sale_type ?? null) === 'cash') {
            return (float) ($sale->paid_amount ?? 0);
        }

        if (($sale->sale_type ?? null) === 'mixed') {
            return (float) ($sale->cash_amount ?? 0);
        }

        return 0;
    }

    private function cardAmountForSale(object $sale): float
    {
        $cardPaidAmount = (float) ($sale->card_paid ?? 0);
        if ($cardPaidAmount > 0) {
            return $cardPaidAmount;
        }

        if (($sale->sale_type ?? null) === 'card') {
            return (float) ($sale->paid_amount ?? 0);
        }

        if (($sale->sale_type ?? null) === 'mixed') {
            return (float) ($sale->card_amount ?? 0);
        }

        return 0;
    }

    private function operationTotalForSale(object $sale): float
    {
        $splitPaymentTotal = (float) ($sale->cash_paid ?? 0) + (float) ($sale->card_paid ?? 0);
        if ($splitPaymentTotal <= 0) {
            $splitPaymentTotal = (float) ($sale->cash_amount ?? 0) + (float) ($sale->card_amount ?? 0);
        }

        return max($splitPaymentTotal, (float) ($sale->paid_amount ?? 0));
    }

    private function tadlilWorkAmount(object $sale): float
    {
        $operationTotal = (float) ($sale->operation_total ?? $sale->final_total ?? 0);
        $productsTotal = (float) ($sale->products_total_value ?? 0);

        return max(0, $operationTotal - $productsTotal);
    }


    private function getCreditContextsBySaleId(int $storeId, array $saleIds): array
    {
        if (empty($saleIds)) {
            return [];
        }

        return CreditSale::query()
            ->where('store_id', $storeId)
            ->where(function ($query) use ($saleIds) {
                $query->whereIn('sale_id', array_map('intval', $saleIds));

                foreach ($saleIds as $saleId) {
                    $query->orWhere('description', 'like', '%#' . (int) $saleId . '%');
                }
            })
            ->orderBy('id')
            ->get()
            ->groupBy(fn (CreditSale $creditSale) => $creditSale->resolveLinkedSaleId())
            ->filter(fn ($creditSales, $saleId) => (int) $saleId > 0)
            ->map(function ($creditSales) {
                $note = $creditSales
                    ->pluck('credit_note')
                    ->first(fn ($value) => trim((string) $value) !== '');

                return [
                    'note' => $note,
                    'has_credit' => true,
                    'has_collections' => $creditSales->contains(
                        fn (CreditSale $creditSale) => (float) $creditSale->remaining_amount < (float) $creditSale->amount
                    ),
                ];
            })
            ->all();
    }

    private function getCreditCollectionOperations(int $storeId, $shiftWindows, array $visibleSaleIds = [])
    {
        $shiftWindowService = app(ShiftWindowService::class);
        $startTime = $shiftWindows->first()['start'] ?? now()->startOfDay();
        $endTime = $shiftWindows->last()['end'] ?? now();
        $businessDates = $shiftWindows
            ->pluck('business_date')
            ->filter()
            ->map(fn ($date) => Carbon::parse($date)->toDateString())
            ->unique()
            ->values();

        $collectionRows = DB::table('employee_credit_collections')
            ->join('credit_sales', 'credit_sales.id', '=', 'employee_credit_collections.credit_sale_id')
            ->leftJoin('employees', 'employee_credit_collections.person_id', '=', 'employees.id')
            ->where('employee_credit_collections.store_id', $storeId)
            ->whereNull('credit_sales.deleted_at')
            ->when($businessDates->isNotEmpty(), function ($query) use ($businessDates) {
                $query->whereIn('employee_credit_collections.collection_date', $businessDates->all());
            }, function ($query) use ($startTime, $endTime) {
                $query->whereBetween('employee_credit_collections.collection_date', [
                    Carbon::parse($startTime)->toDateString(),
                    Carbon::parse($endTime)->toDateString(),
                ]);
            })
            ->select(
                'employee_credit_collections.id as collection_id',
                'employee_credit_collections.credit_sale_id',
                'employee_credit_collections.sale_id',
                'employee_credit_collections.amount as collected_amount',
                'employee_credit_collections.payment_method',
                'employee_credit_collections.cash_amount',
                'employee_credit_collections.card_amount',
                'employee_credit_collections.collection_date',
                'employee_credit_collections.created_at as collection_created_at',
                'credit_sales.id',
                'credit_sales.amount',
                'credit_sales.remaining_amount',
                'credit_sales.updated_at',
                'credit_sales.created_at',
                'credit_sales.description',
                'credit_sales.credit_note',
                'employees.name as employee_name'
            )
            ->orderBy('employee_credit_collections.collection_date')
            ->orderBy('employee_credit_collections.id')
            ->get();

        $paymentsByCreditSale = $collectionRows->groupBy('credit_sale_id')->map(function ($rows) {
            return $rows->map(fn ($row) => [
                'collection_id' => (int) $row->collection_id,
                'amount' => (float) $row->collected_amount,
                'date' => Carbon::parse($row->collection_date),
                'payment_method' => $row->payment_method ?: 'cash',
                'cash_amount' => (float) $row->cash_amount,
                'card_amount' => (float) $row->card_amount,
            ])->values()->all();
        });

        return $collectionRows->map(function ($collection) use ($paymentsByCreditSale, $shiftWindows, $storeId, $shiftWindowService) {
            $collection->collection_rows = $paymentsByCreditSale->get($collection->credit_sale_id, []);
            $payment = [
                'collection_id' => (int) $collection->collection_id,
                'amount' => (float) $collection->collected_amount,
                'date' => Carbon::parse($collection->collection_date),
                'payment_method' => $collection->payment_method ?: 'cash',
                'cash_amount' => (float) $collection->cash_amount,
                'card_amount' => (float) $collection->card_amount,
            ];
            $profitBreakdown = $this->calculateCollectionProfitBreakdown($storeId, $collection, $payment);

            $paymentMethod = $payment['payment_method'];
            $cashPaid = (float) $payment['cash_amount'];
            $cardPaid = (float) $payment['card_amount'];
            $paymentLabel = match ($paymentMethod) {
                'card' => 'تحصيل شبكة',
                'mixed' => 'تحصيل ميكس',
                default => 'تحصيل كاش',
            };

            $operation = (object) [
                'id' => 'collection-' . $collection->collection_id,
                'store_id' => null,
                'items' => collect(),
                'description' => $collection->credit_note ?: $collection->description,
                'credit_note' => $collection->credit_note,
                'internal_notes' => null,
                'employee_name' => $collection->employee_name ?: 'غير معروف',
                'employee_id' => null,
                'operation_kind' => 'collection',
                'sale_type' => 'collection',
                'has_partial_credit' => false,
                'paid_amount' => (float) $payment['amount'],
                'remaining_amount' => 0,
                'cash_amount' => $cashPaid,
                'card_amount' => $cardPaid,
                'cash_paid' => $cashPaid,
                'card_paid' => $cardPaid,
                'labor_total' => 0,
                'total' => (float) $payment['amount'],
                'final_total' => (float) $payment['amount'],
                'operation_total' => (float) $payment['amount'],
                'total_cost' => 0,
                'recognized_cost' => (float) ($profitBreakdown['cost_component'] ?? 0),
                'uncovered_cost' => 0,
                'products_profit' => 0,
                'total_profit' => (float) ($profitBreakdown['recognized_profit'] ?? 0),
                'recognized_profit' => (float) ($profitBreakdown['recognized_profit'] ?? max(0, (float) $payment['amount'] - (float) ($profitBreakdown['cost_component'] ?? 0))),
                'deferred_profit' => (float) ($profitBreakdown['deferred_profit_remaining'] ?? 0),
                'profit_is_deferred' => (bool) ($profitBreakdown['has_deferred_profit'] ?? false),
                'payment_label' => $paymentLabel,
                'employee' => null,
                'accountant' => null,
                'created_at' => Carbon::parse($collection->collection_date),
                'updated_at' => Carbon::parse($collection->collection_date),
                'display_time' => Carbon::parse($collection->collection_date),
            ];

            $operation->shift_key = $this->resolveCollectionShiftKey(Carbon::parse($collection->collection_date), $shiftWindows)
                ?? $shiftWindowService->resolveShiftKey($operation, $shiftWindows);

            return $operation;
        })->values();
    }

    private function extractCollectionPayments($collection): array
    {
        if (!empty($collection->collection_rows) && is_array($collection->collection_rows)) {
            return array_values(array_filter($collection->collection_rows, fn ($payment) => ($payment['amount'] ?? 0) > 0));
        }

        return [];
    }

    private function resolveCollectionShiftKey(Carbon $paymentDate, $shiftWindows): ?string
    {
        $paymentBusinessDate = $paymentDate->toDateString();

        foreach ($shiftWindows as $shiftWindow) {
            if (!empty($shiftWindow['business_date']) && $shiftWindow['business_date'] === $paymentBusinessDate) {
                return $shiftWindow['key'];
            }
        }

        foreach ($shiftWindows as $shiftWindow) {
            if (!empty($shiftWindow['business_date'])) {
                continue;
            }

            if ($paymentBusinessDate >= $shiftWindow['start']->toDateString()
                && $paymentBusinessDate <= $shiftWindow['end']->toDateString()) {
                return $shiftWindow['key'];
            }
        }

        return null;
    }

    private function calculateCollectionProfitBreakdown(int $storeId, $collection, array $targetPayment): array
    {
        $linkedSaleId = $collection->sale_id ?? $this->extractLinkedSaleId((string) ($collection->description ?? ''));
        if (!$linkedSaleId) {
            return [
                'cost_component' => 0,
                'recognized_profit' => 0,
                'deferred_profit_remaining' => 0,
                'has_deferred_profit' => false,
            ];
        }

        $sale = $this->getProcessedSaleById($storeId, $linkedSaleId);
        if (!$sale) {
            return [
                'cost_component' => 0,
                'recognized_profit' => 0,
                'deferred_profit_remaining' => 0,
                'has_deferred_profit' => false,
            ];
        }

        $allPayments = $this->extractCollectionPayments($collection);
        $totalCost = max(0, (float) ($sale->total_cost ?? 0));
        $operationTotal = max((float) ($sale->operation_total ?? 0), (float) ($sale->final_total ?? 0));
        // لا نعتمد على paid_amount في العملية المرتبطة لأنه يتزامن بعد التحصيل؛
        // أساس ما دُفع وقت البيع = إجمالي العملية - قيمة سجل الأجل الأصلي.
        $initialCollectedAmount = max(0, $operationTotal - (float) ($collection->amount ?? 0));
        $finalProfit = max(0, $operationTotal - $totalCost);

        $collectedBefore = $initialCollectedAmount;
        foreach ($allPayments as $payment) {
            $samePayment = !empty($payment['collection_id']) && !empty($targetPayment['collection_id'])
                ? (int) $payment['collection_id'] === (int) $targetPayment['collection_id']
                : ((float) ($payment['amount'] ?? 0) === (float) ($targetPayment['amount'] ?? 0)
                    && Carbon::parse($payment['date'])->equalTo(Carbon::parse($targetPayment['date'])));

            if ($samePayment) {
                break;
            }

            $collectedBefore += (float) ($payment['amount'] ?? 0);
        }

        $collectedAfter = $collectedBefore + (float) ($targetPayment['amount'] ?? 0);

        $coveredCostBefore = min($totalCost, $collectedBefore);
        $coveredCostAfter = min($totalCost, $collectedAfter);
        $costComponent = max(0, $coveredCostAfter - $coveredCostBefore);

        $recognizedProfitBefore = max(0, $collectedBefore - $totalCost);
        $recognizedProfitAfter = max(0, $collectedAfter - $totalCost);
        $recognizedProfit = max(0, min($finalProfit, $recognizedProfitAfter) - min($finalProfit, $recognizedProfitBefore));
        $deferredProfitRemaining = max(0, $finalProfit - min($finalProfit, $recognizedProfitAfter));

        return [
            'cost_component' => $costComponent,
            'recognized_profit' => $recognizedProfit,
            'deferred_profit_remaining' => $deferredProfitRemaining,
            'has_deferred_profit' => $deferredProfitRemaining > 0,
        ];
    }

    private function extractLinkedSaleId(string $description): ?int
    {
        if (preg_match('/#(\d+)/', $description, $matches) === 1) {
            return (int) $matches[1];
        }

        return null;
    }

    private function getProcessedSaleById(int $storeId, int $saleId)
    {
        if (array_key_exists($saleId, $this->processedSalesCache)) {
            return $this->processedSalesCache[$saleId];
        }

        $saleItemRows = Sale::where('sales.store_id', $storeId)
            ->where('sales.id', $saleId)
            ->where(function ($salesDescriptionQuery) {
                $salesDescriptionQuery->whereNull('sales.description')
                    ->orWhere('sales.description', '!=', 'manual_invoice_entry');
            })
            ->leftJoin('sale_items', 'sales.id', '=', 'sale_items.sale_id')
            ->leftJoin('products', 'sale_items.product_id', '=', 'products.id')
            ->select(
                'sales.*',
                'sale_items.id as item_id',
                'sale_items.product_id',
                'sale_items.quantity as item_quantity',
                'sale_items.price as item_price',
                'sale_items.total as item_total',
                'sale_items.cost_price as item_cost_price',
                'sale_items.total_cost as item_total_cost',
                'sale_items.is_custom',
                'sale_items.custom_name',
                'sale_items.custom_consumption',
                'sale_items.custom_meters',
                'sale_items.unit_type as item_unit_type',
                DB::raw('COALESCE(sale_items.product_name_snapshot, sale_items.custom_name, products.name) as product_name'),
                'products.quantity as product_quantity',
                DB::raw('COALESCE(sale_items.sale_price_snapshot, sale_items.price, products.price) as product_price'),
                DB::raw('COALESCE(sale_items.cost_price_snapshot, sale_items.cost_price, products.cost_price) as product_cost_price'),
                DB::raw('COALESCE(sale_items.product_type_snapshot, products.product_type) as product_type'),
                DB::raw('COALESCE(sale_items.is_splittable_snapshot, products.is_splittable, 0) as product_is_splittable'),
                DB::raw('COALESCE(sale_items.items_per_unit_snapshot, products.items_per_unit, 1) as product_items_per_unit'),
                'products.piece_price as product_piece_price',
                DB::raw('COALESCE(sale_items.roll_length_snapshot, sale_items.roll_length_at_sale, products.roll_length) as product_roll_length'),
                'sale_items.unit_label_snapshot as item_unit_label_snapshot'
            )
            ->orderBy('sale_items.id')
            ->get();

        if ($saleItemRows->isEmpty()) {
            return $this->processedSalesCache[$saleId] = null;
        }

        $processedSaleWithItems = clone $saleItemRows->first();
        $processedSaleWithItems->items = collect();
        $processedSaleWithItems->total_cost = 0;
        $processedSaleWithItems->total_profit = 0;
        $processedSaleWithItems->products_total_value = 0;

        foreach ($saleItemRows as $saleItemRow) {
            if (!$saleItemRow->item_id) {
                continue;
            }

            $processedSaleWithItems->items->push((object) [
                'id' => $saleItemRow->item_id,
                'product_id' => $saleItemRow->product_id,
                'quantity' => $saleItemRow->item_quantity,
                'price' => $saleItemRow->item_price,
                'total' => $saleItemRow->item_total,
                'cost_price_at_sale' => $saleItemRow->item_cost_price,
                'total_cost_at_sale' => $saleItemRow->item_total_cost,
                'is_custom' => $saleItemRow->is_custom,
                'custom_name' => $saleItemRow->custom_name,
                'custom_consumption' => $saleItemRow->custom_consumption,
                'custom_meters' => $saleItemRow->custom_meters,
                'unit_type' => $saleItemRow->item_unit_type,
                'unit_label' => $saleItemRow->item_unit_label_snapshot,
                'product_name' => $saleItemRow->product_name ?? 'منتج غير معروف',
                'product_quantity' => $saleItemRow->product_quantity ?? 0,
                'product_price' => (float) ($saleItemRow->product_price ?? 0),
                'cost_price' => $saleItemRow->product_cost_price ?? 0,
                'product_type' => $saleItemRow->product_type,
                'is_splittable' => (bool) ($saleItemRow->product_is_splittable ?? false),
                'items_per_unit' => (float) ($saleItemRow->product_items_per_unit ?? 0),
                'piece_price' => (float) ($saleItemRow->product_piece_price ?? 0),
                'roll_length' => (float) ($saleItemRow->product_roll_length ?? 0),
            ]);
        }

        return $this->processedSalesCache[$saleId] = $this->processSale($processedSaleWithItems);
    }

    public function update(Store $store, Sale $sale, Request $request)
    {
        if ($sale->store_id !== $store->id) {
            abort(403, 'هذه العملية لا تنتمي لهذا المتجر');
        }

        $validated = $request->validate([
            'sale_type'   => 'required|in:cash,card,credit,mixed',
            'paid_amount' => 'required|numeric|min:0',
            'labor_total' => 'nullable|numeric|min:0',
            'description' => 'nullable|string|max:1000',
            'operation_name' => 'nullable|string|max:1000',
            'credit_note' => 'nullable|string|max:1000',
            'cash_amount' => 'required_if:sale_type,mixed|nullable|numeric|gt:0',
            'card_amount' => 'required_if:sale_type,mixed|nullable|numeric|gt:0',
            'employee_id' => 'nullable|exists:employees,id',
            'debt_amount' => 'nullable|numeric|min:0',
            'item_product_ids' => 'nullable|array',
            'item_product_ids.*' => 'required|integer|exists:products,id',
            'item_ids' => 'nullable|array',
            'item_ids.*' => 'required|integer|min:0',
            'item_quantities' => 'nullable|array',
            'item_quantities.*' => 'required|numeric|min:0.01',
            'item_prices' => 'nullable|array',
            'item_prices.*' => 'required|numeric|min:0',
            'item_unit_types' => 'nullable|array',
            'item_unit_types.*' => 'nullable|in:unit,piece',
            'record_remaining_as_credit' => 'nullable|boolean',
        ], [
            'cash_amount.required_if' => 'مبلغ الكاش مطلوب في عملية الميكس.',
            'cash_amount.gt' => 'مبلغ الكاش في عملية الميكس يجب أن يكون أكبر من صفر.',
            'card_amount.required_if' => 'مبلغ الشبكة مطلوب في عملية الميكس.',
            'card_amount.gt' => 'مبلغ الشبكة في عملية الميكس يجب أن يكون أكبر من صفر.',
        ]);

        $creditRowsQuery = CreditSale::where('store_id', $store->id)
            ->where(function ($query) use ($sale) {
                $query->where('sale_id', $sale->id)
                    ->orWhere('description', 'like', '%' . '#' . $sale->id . '%');
            });
        $registeredBusinessDate = Carbon::parse($sale->business_date ?: $sale->created_at);
        $currentShiftContext = app(ShiftLifecycleService::class)->currentShiftContext($store->id);
        $currentAccountingDate = Carbon::parse($currentShiftContext['business_date'] ?? now()->toDateString());
        $isOutsideCurrentAccountingMonth = $registeredBusinessDate->format('Y-m') !== $currentAccountingDate->format('Y-m');

        $hasLinkedCredit = ($sale->sale_type === 'credit')
            || (bool) ($sale->has_partial_credit ?? false)
            || (float) ($sale->remaining_amount ?? 0) > 0
            || (clone $creditRowsQuery)->exists();

        if ($isOutsideCurrentAccountingMonth && ! $hasLinkedCredit) {
            return back()->withErrors([
                'sale_type' => 'لا يمكن تعديل المنتجات أو المبالغ أو بيانات العملية بعد انتهاء شهرها. يسمح بالتعديل فقط داخل شهر العملية الحالي.',
            ])->withInput()->with('edit_sale_modal', $sale->id);
        }

        if ($hasLinkedCredit) {
            if ($isOutsideCurrentAccountingMonth) {
                $selectedEmployeeId = $validated['employee_id'] ?? $sale->employee_id;

                if (!$selectedEmployeeId) {
                    return back()->withErrors([
                        'employee_id' => 'هذه عملية آجل من شهر سابق؛ لا يمكن تعديلها إلا بتغيير الموظف المرتبط بها.',
                    ])->withInput()->with('edit_sale_modal', $sale->id);
                }

                $employeeBelongsToStore = $store->employees()->where('id', $selectedEmployeeId)->exists();
                if (!$employeeBelongsToStore) {
                    return back()->withErrors(['employee_id' => 'الموظف المختار لا يتبع هذا المتجر.'])->withInput()->with('edit_sale_modal', $sale->id);
                }

                DB::transaction(function () use ($sale, $selectedEmployeeId, $creditRowsQuery) {
                    $sale->update(['employee_id' => $selectedEmployeeId]);

                    (clone $creditRowsQuery)->update([
                        'person_id' => $selectedEmployeeId,
                        'person_type' => \App\Models\Employee::class,
                    ]);
                });

                return $this->redirectAfterMutation($request, 'تم تحديث الموظف المرتبط بعملية الآجل فقط؛ لا يمكن تعديل النوع أو المبالغ بعد تجاوز شهر تسجيل العملية.');
            }
        }

        $submittedOperationName = trim((string) $request->input('operation_name', ''));
        $submittedSaleType = (string) $request->input('sale_type', $sale->sale_type);
        $submittedPaidAmount = (float) $request->input('paid_amount', $sale->paid_amount ?? 0);
        $submittedLaborTotal = (float) $request->input('labor_total', $sale->labor_total ?? 0);
        $submittedDebtAmount = (float) $request->input('debt_amount', $sale->remaining_amount ?? 0);
        $cashInputIsEmptyOrZero = ! $request->filled('cash_amount') || abs((float) $request->input('cash_amount', 0)) <= 0.01;
        $cardInputIsEmptyOrZero = ! $request->filled('card_amount') || abs((float) $request->input('card_amount', 0)) <= 0.01;

        $isOperationNameOnlyEdit = $hasLinkedCredit
            && $submittedOperationName !== ''
            && in_array($submittedSaleType, ['credit', (string) $sale->sale_type], true)
            && abs($submittedPaidAmount - (float) ($sale->paid_amount ?? 0)) <= 0.01
            && abs($submittedLaborTotal - (float) ($sale->labor_total ?? 0)) <= 0.01
            && abs($submittedDebtAmount - (float) ($sale->remaining_amount ?? 0)) <= 0.01
            && $cashInputIsEmptyOrZero
            && $cardInputIsEmptyOrZero
            && empty($validated['item_ids'])
            && empty($validated['item_product_ids']);

        if ($isOperationNameOnlyEdit) {
            $operationName = trim((string) $validated['operation_name']);
            DB::transaction(function () use ($sale, $creditRowsQuery, $operationName) {
                (clone $creditRowsQuery)->update(['credit_note' => $operationName !== '' ? $operationName : null]);
            });

            return $this->redirectAfterMutation($request, 'تم تعديل اسم عملية الأجل بنجاح.');
        }

        if ((float) $request->input('debt_amount', 0) > 0
            && trim((string) $request->input('operation_name', $request->input('credit_note', ''))) === '') {
            return back()->withErrors([
                'credit_note' => 'اسم العملية إلزامي عند وجود أجل كامل أو جزئي.',
            ])->withInput()->with('edit_sale_modal', $sale->id);
        }

        if ($request->input('sale_type') === 'credit' && trim((string) $request->input('operation_name', $request->input('credit_note', ''))) === '') {
            return back()->withErrors([
                'credit_note' => 'اسم العملية إلزامي عند اختيار البيع الآجل.',
            ])->withInput()->with('edit_sale_modal', $sale->id);
        }

        $originalSaleType = $sale->sale_type;
        $submittedItemIds = array_values($validated['item_ids'] ?? []);
        $submittedProductIds = array_values($validated['item_product_ids'] ?? []);
        $submittedQuantities = array_values($validated['item_quantities'] ?? []);
        $submittedPrices = array_values($validated['item_prices'] ?? []);
        $submittedUnitTypes = array_values($validated['item_unit_types'] ?? []);
        $hasItemEdits = count($submittedProductIds) > 0 || count($submittedItemIds) > 0;
        $itemEditPlan = collect();
        $itemDeletePlan = collect();
        $productsTotal = (float) ($sale->products_total ?? 0);
        // sales.profit يمثل ربح العملية المستحق من قيمة البيع الأساسية (المنتجات + شغل اليد)
        // ولا يدخل فيه مبلغ الضريبة أو مقدار المبلغ المستلم فعليًا. لذلك نستنتج تكلفة المنتجات
        // من القيم المحفوظة عند عدم تعديل أسطر المنتجات، ونستبدلها بتكلفة الأسطر عند تعديلها.
        $productsCost = max(
            ((float) ($sale->products_total ?? 0) + (float) ($sale->labor_total ?? 0)) - (float) ($sale->profit ?? 0),
            0.0
        );

        if ($hasItemEdits) {
            if (count($submittedItemIds) !== count($submittedQuantities)
                || count($submittedItemIds) !== count($submittedPrices)
                || (count($submittedProductIds) > 0 && count($submittedItemIds) !== count($submittedProductIds))) {
                return back()->withErrors([
                    'item_ids' => 'بيانات المنتجات المرسلة غير مكتملة. أعد فتح نافذة التعديل وحاول مرة أخرى.',
                ])->withInput()->with('edit_sale_modal', $sale->id);
            }

            $existingSubmittedItemIds = collect($submittedItemIds)
                ->map(fn ($itemId) => (int) $itemId)
                ->filter(fn ($itemId) => $itemId > 0)
                ->values();

            $saleItems = $sale->items()
                ->with('product')
                ->get()
                ->keyBy('id');

            if ($existingSubmittedItemIds->isNotEmpty()
                && $saleItems->only($existingSubmittedItemIds->all())->count() !== $existingSubmittedItemIds->count()) {
                return back()->withErrors([
                    'item_ids' => 'يوجد منتج لا يتبع هذه العملية أو لم يعد موجودًا.',
                ])->withInput()->with('edit_sale_modal', $sale->id);
            }

            $itemDeletePlan = $saleItems
                ->reject(fn ($item) => $existingSubmittedItemIds->contains((int) $item->id))
                ->values();

            foreach ($submittedItemIds as $index => $itemId) {
                $item = ((int) $itemId) > 0 ? $saleItems->get((int) $itemId) : null;
                $requestedProductId = (int) ($submittedProductIds[$index] ?? $item?->product_id);
                $product = Product::whereKey($requestedProductId)->sellable()->first();
                $oldProduct = $item?->product;
                $newQuantity = (float) $submittedQuantities[$index];
                $newPrice = round((float) $submittedPrices[$index], 2);
                $isFractional = ($oldProduct?->product_type ?? null) === 'fractional';
                $newProductIsFractional = ($product?->product_type ?? null) === 'fractional';
                $isNewOrReplacement = !$oldProduct || (int) ($product?->id ?? 0) !== (int) $oldProduct->id;
                // وحدة البيع تحدد هل الطقم يُباع كاملاً أم بالحبة، ومنها نحسب المخزون والتكلفة.
                $submittedUnitType = $submittedUnitTypes[$index] ?? ($item->unit_type ?? 'unit');
                $unitType = ($product?->is_splittable && $submittedUnitType === 'piece') ? 'piece' : 'unit';

                if (!$product || (int) $product->store_id !== (int) $store->id || ($oldProduct && (int) $oldProduct->store_id !== (int) $store->id)) {
                    return back()->withErrors([
                        'item_ids' => 'تعذر العثور على المنتج المرتبط بأحد أسطر العملية داخل هذا المتجر.',
                    ])->withInput()->with('edit_sale_modal', $sale->id);
                }

                if ($isNewOrReplacement) {
                    $isTintProduct = $product->category && in_array($product->category->name, ['تضليل', 'تظليل'], true);
                    if (($product->status ?? 'active') !== 'active' || $newProductIsFractional || $isTintProduct) {
                        return back()->withErrors([
                            'item_product_ids' => 'لا يمكن إضافة أو استبدال المنتج بمنتج موقوف/مخفي أو منتج رول/تضليل من هذه النافذة.',
                        ])->withInput()->with('edit_sale_modal', $sale->id);
                    }
                }

                if ($isFractional && ((int) $product->id !== (int) $oldProduct?->id || abs($newQuantity - (float) $item->quantity) > 0.0001)) {
                    return back()->withErrors([
                        'item_quantities' => 'لا يمكن تغيير كمية منتج رول/تضليل من هذه النافذة لأن استهلاكه محفوظ بالأمتار. يمكن تعديل سعر البيع فقط.',
                    ])->withInput()->with('edit_sale_modal', $sale->id);
                }

                if ($oldProduct && (int) ($product?->id ?? 0) !== (int) ($oldProduct?->id ?? 0)
                    && abs($newPrice - (float) ($item->price ?? 0)) <= 0.0001) {
                    $newPrice = round((float) ($product->price ?? 0), 2);
                }

                if (!$isFractional && ($newProductIsFractional || abs($newQuantity - round($newQuantity)) > 0.0001)) {
                    return back()->withErrors([
                        'item_quantities' => 'كمية المنتج العادي يجب أن تكون عددًا صحيحًا.',
                    ])->withInput()->with('edit_sale_modal', $sale->id);
                }

                $storedQuantity = $isFractional ? (float) $item->quantity : (int) round($newQuantity);
                $oldStoredQuantity = max((float) ($item->quantity ?? 0), 0.0001);
                $oldStockQuantity = $item ? (float) ($item->custom_consumption ?? $item->quantity) : 0.0;
                $stockPerSaleUnit = $item ? ($oldStockQuantity / $oldStoredQuantity) : 1.0;
                $stockQuantity = $isFractional
                    ? $oldStockQuantity
                    : ($product->is_splittable
                        ? $product->calculateFinalDeduction($storedQuantity, $unitType)
                        : $stockPerSaleUnit * $storedQuantity);
                $lineTotal = round($newPrice * $storedQuantity, 2);
                $lineCost = round(ProductProfitCostCalculator::calculateItemCost($product, [
                    'quantity' => $storedQuantity,
                    'custom_consumption' => $isFractional ? $stockQuantity : null,
                    'unit_type' => $unitType,
                ]), 2);

                $itemEditPlan->push([
                    'item' => $item,
                    'product' => $product,
                    'old_product' => $oldProduct,
                    'is_new' => $item === null,
                    'product_changed' => $oldProduct && (int) $product->id !== (int) $oldProduct->id,
                    'quantity' => $storedQuantity,
                    'old_stock_quantity' => $oldStockQuantity,
                    'new_stock_quantity' => $stockQuantity,
                    'unit_type' => $unitType,
                    'price' => $newPrice,
                    'total' => $lineTotal,
                    'cost_price' => (float) ($product->cost_price ?? 0),
                    'total_cost' => $lineCost,
                ]);
            }

            $productsTotal = round($itemEditPlan->sum('total'), 2);
            $productsCost = round($itemEditPlan->sum('total_cost'), 2);
        }

        $taxRate = (float) ($sale->tax_rate ?? 0);
        $laborTotal = (float) ($validated['labor_total'] ?? 0);

        $taxAmount = $productsTotal * ($taxRate / 100);
        $finalTotal = $productsTotal + $taxAmount + $laborTotal;

        $enteredAmount = (float) $validated['paid_amount'];
        $enteredDebtAmount = (float) ($validated['debt_amount'] ?? 0);
        $recordRemainingAsCredit = $request->boolean('record_remaining_as_credit');
        $selectedEmployeeId = $validated['employee_id'] ?? $sale->employee_id;
        $paidAmount = $enteredAmount;
        $cashAmount = 0.0;
        $cardAmount = 0.0;
        $storedOperationAmount = (float) (($sale->paid_amount ?? 0) + ($sale->remaining_amount ?? 0));
        $operationAmountBeforePaymentEdit = $hasItemEdits ? $finalTotal : max($finalTotal, $storedOperationAmount);
        $hasCollectedCreditConversion = $originalSaleType === 'credit'
            && (float) ($sale->paid_amount ?? 0) > 0
            && (float) ($sale->remaining_amount ?? 0) > 0
            && $validated['sale_type'] !== 'credit';
        $alreadyCollectedAmount = $hasCollectedCreditConversion ? (float) ($sale->paid_amount ?? 0) : 0.0;
        $editableOperationAmount = $hasCollectedCreditConversion ? (float) ($sale->remaining_amount ?? 0) : $operationAmountBeforePaymentEdit;
        $protectedCashAmount = $hasCollectedCreditConversion ? $alreadyCollectedAmount : 0.0;

        if (!empty($validated['employee_id'])) {
            $employeeBelongsToStore = $store->employees()->where('id', $validated['employee_id'])->exists();
            if (!$employeeBelongsToStore) {
                return back()->withErrors(['employee_id' => 'الموظف المختار لا يتبع هذا المتجر.'])->withInput()->with('edit_sale_modal', $sale->id);
            }
        }

        if ($validated['sale_type'] === 'cash') {
            $cashEditableAmount = $hasCollectedCreditConversion ? $editableOperationAmount : $enteredAmount;
            $paidAmount = $alreadyCollectedAmount + $cashEditableAmount;
            $remainingAmount = 0;
            $cashAmount = $protectedCashAmount + $cashEditableAmount;

            if ($recordRemainingAsCredit && !$hasCollectedCreditConversion) {
                $remainingAmount = max($enteredDebtAmount, $operationAmountBeforePaymentEdit - $cashEditableAmount, 0);
                $finalTotal = max($finalTotal, $cashEditableAmount + $remainingAmount);

                if (abs(($cashEditableAmount + $remainingAmount) - $operationAmountBeforePaymentEdit) > 0.01) {
                    return back()->withErrors([
                        'debt_amount' => 'عند تسجيل المتبقي كآجل يجب أن يساوي (الكاش + الأجل) قيمة العملية.'
                    ])->withInput()->with('edit_sale_modal', $sale->id);
                }
            }
        } elseif ($validated['sale_type'] === 'card') {
            $cardEditableAmount = $hasCollectedCreditConversion ? $editableOperationAmount : $enteredAmount;
            $paidAmount = $alreadyCollectedAmount + $cardEditableAmount;
            $remainingAmount = 0;
            $cashAmount = $protectedCashAmount;
            $cardAmount = $cardEditableAmount;

            if ($recordRemainingAsCredit && !$hasCollectedCreditConversion) {
                $remainingAmount = max($enteredDebtAmount, $operationAmountBeforePaymentEdit - $cardEditableAmount, 0);
                $finalTotal = max($finalTotal, $cardEditableAmount + $remainingAmount);

                if (abs(($cardEditableAmount + $remainingAmount) - $operationAmountBeforePaymentEdit) > 0.01) {
                    return back()->withErrors([
                        'debt_amount' => 'عند تسجيل المتبقي كآجل يجب أن يساوي (الشبكة + الأجل) قيمة العملية.'
                    ])->withInput()->with('edit_sale_modal', $sale->id);
                }
            }
        } elseif ($validated['sale_type'] === 'mixed') {
            $hasCashInput = $request->filled('cash_amount');
            $hasCardInput = $request->filled('card_amount');
            $hasDebtInput = $request->filled('debt_amount');
            $isCreditToMixedConversion = $originalSaleType === 'credit';
            $debtAmount = max(0, $enteredDebtAmount);

            if ($isCreditToMixedConversion && !($hasCashInput || $hasCardInput)) {
                return back()->withErrors([
                    'sale_type' => 'عند التحويل من آجل إلى ميكس يجب إدخال توزيع الكاش/الشبكة صراحة.'
                ])->withInput()->with('edit_sale_modal', $sale->id);
            }

            if ($hasCashInput || $hasCardInput) {
                $newCashAmount = (float) ($validated['cash_amount'] ?? 0);
                $newCardAmount = (float) ($validated['card_amount'] ?? 0);
                $cashAmount = $protectedCashAmount + $newCashAmount;
                $cardAmount = $newCardAmount;
                $splitPaymentAmount = $newCashAmount + $newCardAmount;
                $paidAmount = $alreadyCollectedAmount + $splitPaymentAmount;

                if ($splitPaymentAmount <= 0) {
                    return back()->withErrors([
                        'paid_amount' => 'في عملية الميكس يجب أن يكون مجموع الكاش والشبكة أكبر من صفر.'
                    ])->withInput()->with('edit_sale_modal', $sale->id);
                }

                // توضيح: في الميكس حقل المبلغ المدفوع هو مجموع الكاش والشبكة فقط؛ لا نسمح بقيمة مختلفة حتى لا يظهر أجل سالب أو تبقى أرقام المحاسب قديمة.
                if (abs($enteredAmount - $splitPaymentAmount) > 0.01) {
                    return back()->withErrors([
                        'paid_amount' => 'في عملية الميكس يجب أن يساوي المبلغ المدفوع مجموع الكاش والشبكة. المبلغ المدفوع: ' . number_format($enteredAmount, 2) . '، مجموع الكاش والشبكة: ' . number_format($splitPaymentAmount, 2) . '.'
                    ])->withInput()->with('edit_sale_modal', $sale->id);
                }
            } else {
                // fallback محافظ للحالات غير الآجلة القديمة فقط
                $cashAmount = $protectedCashAmount + $paidAmount;
                $cardAmount = 0;
            }

            if ($debtAmount < 0 || $debtAmount > $editableOperationAmount) {
                return back()->withErrors(['debt_amount' => 'قيمة الأجل يجب أن تكون بين صفر وقيمة العملية الأساسية.'])->withInput()->with('edit_sale_modal', $sale->id);
            }

            $enteredMixedTotal = max(0, $cashAmount - $protectedCashAmount) + max(0, $cardAmount);

            // توضيح: في تعديل الميكس قيمة العملية المالية تعتمد على المدفوع الفعلي (كاش + شبكة) مع أي أجل مصرح به، لا على إجمالي المنتجات فقط.
            $mixedOperationAmount = max($editableOperationAmount, $enteredMixedTotal + $debtAmount);

            if (!$hasDebtInput && $enteredMixedTotal + 0.01 < $mixedOperationAmount) {
                return back()->withErrors([
                    'debt_amount' => 'عند تعديل العملية إلى ميكس يجب أن يساوي (كاش + شبكة) قيمة العملية، أو يتم إدخال الأجل صراحة واختيار الموظف.'
                ])->withInput()->with('edit_sale_modal', $sale->id);
            }

            $remainingAmount = $debtAmount > 0 ? $debtAmount : max(0, $mixedOperationAmount - $enteredMixedTotal);
            $finalTotal = max($finalTotal, $mixedOperationAmount);

            if (abs(($enteredMixedTotal + $remainingAmount) - $mixedOperationAmount) > 0.01) {
                return back()->withErrors([
                    'debt_amount' => 'في الميكس يجب أن يساوي (كاش + شبكة + أجل) قيمة العملية الأساسية.'
                ])->withInput()->with('edit_sale_modal', $sale->id);
            }
        } else {
            $remainingAmount = max($enteredDebtAmount, $operationAmountBeforePaymentEdit);

            if (abs($remainingAmount - $operationAmountBeforePaymentEdit) > 0.01) {
                return back()->withErrors([
                    'debt_amount' => 'في الآجل الكامل يجب أن تساوي قيمة الأجل كامل العملية. إذا أردت آجلًا جزئيًا استخدم ميكس.'
                ])->withInput()->with('edit_sale_modal', $sale->id);
            }

            $paidAmount = 0;
            $remainingAmount = $operationAmountBeforePaymentEdit;
        }

        $hasPartialCredit = $remainingAmount > 0 && (in_array($validated['sale_type'], ['credit', 'mixed'], true) || $recordRemainingAsCredit);
        $creditDescriptionSuffix = $validated['sale_type'] === 'credit' ? '' : ' (آجل جزئي)';
        $creditNote = trim((string) ($validated['operation_name'] ?? $validated['credit_note'] ?? ''));
        $creditDescription = 'أجل من عملية مبيعات رقم #' . $sale->id . $creditDescriptionSuffix;
        $creditOperationDate = $sale->business_date
            ? Carbon::parse($sale->business_date)->toDateString()
            : now()->toDateString();

        if (!$hasPartialCredit && $validated['sale_type'] !== 'credit' && $finalTotal > ($paidAmount + 0.01)) {
            return back()->withErrors([
                'paid_amount' => 'لا يمكن أن يتجاوز إجمالي المنتجات وشغل اليد قيمة المبلغ المستلم. زِد المبلغ المستلم أو سجّل الفرق كأجل عبر عملية ميكس.'
            ])->withInput()->with('edit_sale_modal', $sale->id);
        }

        if (($validated['sale_type'] === 'credit' || $remainingAmount > 0) && !$selectedEmployeeId) {
            return back()->withErrors(['employee_id' => 'يجب اختيار الموظف الذي سيُسجل عليه الأجل.'])->withInput()->with('edit_sale_modal', $sale->id);
        }

        if ($hasPartialCredit) {
            $hasExistingCredit = CreditSale::where('store_id', $store->id)
                ->where(function ($query) use ($sale) {
                    $query->where('sale_id', $sale->id)
                        ->orWhere('description', 'like', '%#' . $sale->id . '%');
                })
                ->exists();

            if (!$selectedEmployeeId && !$hasExistingCredit) {
                return back()->withErrors(['sale_type' => 'لا يمكن التحويل إلى ميكس/آجل جزئي بدون موظف مرتبط بهذه العملية.'])->withInput()->with('edit_sale_modal', $sale->id);
            }
        }

        try {
            DB::transaction(function () use ($sale, $store, $validated, $laborTotal, $productsTotal, $productsCost, $finalTotal, $paidAmount, $remainingAmount, $cashAmount, $cardAmount, $hasPartialCredit, $selectedEmployeeId, $creditDescription, $creditNote, $creditOperationDate, $itemEditPlan, $itemDeletePlan, $hasItemEdits) {
            foreach ($itemDeletePlan as $deletedItem) {
                $deletedProduct = $deletedItem->product ? Product::whereKey($deletedItem->product->id)->lockForUpdate()->first() : null;
                $deletedStockQuantity = (float) ($deletedItem->custom_consumption ?? $deletedItem->quantity ?? 0);

                if ($deletedProduct && $deletedStockQuantity > 0) {
                    $deletedProduct->increment('quantity', $deletedStockQuantity);
                    $deletedProduct->stockMovements()->create([
                        'store_id' => $store->id,
                        'user_id' => auth()->id(),
                        'product_id' => $deletedProduct->id,
                        'type' => 'increase',
                        'quantity' => $deletedStockQuantity,
                        'note' => 'استرجاع مخزون منتج محذوف من عملية مبيعات #' . $sale->id,
                        'business_date' => $sale->business_date,
                    ]);
                }

                $deletedItem->delete();
            }

            foreach ($itemEditPlan as $plannedItem) {
                $item = $plannedItem['item'];
                $product = Product::whereKey($plannedItem['product']->id)->lockForUpdate()->first();

                if (!$product || (int) $product->store_id !== (int) $store->id) {
                    throw ValidationException::withMessages([
                        'item_ids' => 'تعذر قفل المنتج المرتبط بالعملية للتعديل.',
                    ]);
                }

                if ($plannedItem['is_new']) {
                    if ((float) $product->quantity + 0.0001 < (float) $plannedItem['new_stock_quantity']) {
                        throw ValidationException::withMessages([
                            'item_quantities' => 'الكمية المتاحة من المنتج «' . $product->name . '» لا تكفي لإضافة المنتج للعملية.',
                        ]);
                    }

                    $product->decrement('quantity', (float) $plannedItem['new_stock_quantity']);
                    $product->stockMovements()->create([
                        'store_id' => $store->id,
                        'user_id' => auth()->id(),
                        'product_id' => $product->id,
                        'type' => 'decrease',
                        'quantity' => (float) $plannedItem['new_stock_quantity'],
                        'note' => 'خصم مخزون منتج مضاف إلى عملية مبيعات #' . $sale->id,
                        'business_date' => $sale->business_date,
                    ]);

                    $sale->items()->create([
                        'product_id' => $product->id,
                        'quantity' => $plannedItem['quantity'],
                        'price' => $plannedItem['price'],
                        'total' => $plannedItem['total'],
                        'custom_consumption' => $plannedItem['new_stock_quantity'],
                        'cost_price' => $plannedItem['cost_price'],
                        'total_cost' => $plannedItem['total_cost'],
                        'unit_type' => $plannedItem['unit_type'],
                    ]);

                    continue;
                }

                if ($plannedItem['product_changed']) {
                    $oldProduct = Product::whereKey($plannedItem['old_product']->id)->lockForUpdate()->first();
                    if ($oldProduct) {
                        $oldProduct->increment('quantity', (float) $plannedItem['old_stock_quantity']);
                        $oldProduct->stockMovements()->create([
                            'store_id' => $store->id,
                            'user_id' => auth()->id(),
                            'product_id' => $oldProduct->id,
                            'type' => 'increase',
                            'quantity' => (float) $plannedItem['old_stock_quantity'],
                            'note' => 'استرجاع مخزون المنتج المستبدل في عملية مبيعات #' . $sale->id,
                            'business_date' => $sale->business_date,
                        ]);
                    }

                    if ((float) $product->quantity + 0.0001 < (float) $plannedItem['new_stock_quantity']) {
                        throw ValidationException::withMessages([
                            'item_product_ids' => 'الكمية المتاحة من المنتج «' . $product->name . '» لا تكفي لاستبدال المنتج في العملية.',
                        ]);
                    }

                    $product->decrement('quantity', (float) $plannedItem['new_stock_quantity']);
                    $product->stockMovements()->create([
                        'store_id' => $store->id,
                        'user_id' => auth()->id(),
                        'product_id' => $product->id,
                        'type' => 'decrease',
                        'quantity' => (float) $plannedItem['new_stock_quantity'],
                        'note' => 'خصم مخزون المنتج البديل في عملية مبيعات #' . $sale->id,
                        'business_date' => $sale->business_date,
                    ]);
                } else {
                    $stockDifference = round(
                        (float) $plannedItem['new_stock_quantity'] - (float) $plannedItem['old_stock_quantity'],
                        4
                    );

                    if ($stockDifference > 0 && (float) $product->quantity + 0.0001 < $stockDifference) {
                        throw ValidationException::withMessages([
                            'item_quantities' => 'الكمية المتاحة من المنتج «' . $product->name . '» لا تكفي لزيادة كمية العملية.',
                        ]);
                    }

                    if ($stockDifference > 0) {
                        $product->decrement('quantity', $stockDifference);
                        $movementType = 'decrease';
                        $movementQuantity = $stockDifference;
                    } elseif ($stockDifference < 0) {
                        $movementQuantity = abs($stockDifference);
                        $product->increment('quantity', $movementQuantity);
                        $movementType = 'increase';
                    } else {
                        $movementType = null;
                        $movementQuantity = 0;
                    }

                    if ($movementType) {
                        $product->stockMovements()->create([
                            'store_id' => $store->id,
                            'user_id' => auth()->id(),
                            'product_id' => $product->id,
                            'type' => $movementType,
                            'quantity' => $movementQuantity,
                            'note' => 'تعديل كمية منتج في عملية مبيعات #' . $sale->id,
                            'business_date' => $sale->business_date,
                        ]);
                    }
                }

                $item->update([
                    'product_id' => $product->id,
                    'quantity' => $plannedItem['quantity'],
                    'price' => $plannedItem['price'],
                    'total' => $plannedItem['total'],
                    'custom_consumption' => $plannedItem['new_stock_quantity'],
                    'cost_price' => $plannedItem['cost_price'],
                    'total_cost' => $plannedItem['total_cost'],
                    'unit_type' => $plannedItem['unit_type'],
                ]);
            }

            $saleProfit = round(($productsTotal + $laborTotal) - $productsCost, 2);

            $sale->update([
                'sale_type'          => $validated['sale_type'],
                'products_total'     => $productsTotal,
                'labor_total'        => $laborTotal,
                'description'        => $validated['description'] ?? null,
                'final_total'        => $finalTotal,
                'total'              => $finalTotal,
                'paid_amount'        => $paidAmount,
                'remaining_amount'   => $remainingAmount,
                'cash_amount'        => $cashAmount,
                'card_amount'        => $cardAmount,
                'has_partial_credit' => $hasPartialCredit,
                'profit'             => $saleProfit,
                'employee_id'        => ($validated['sale_type'] === 'credit' || $remainingAmount > 0)
                    ? $selectedEmployeeId
                    : null,
            ]);

            $creditRows = CreditSale::where('store_id', $store->id)
                ->where(function ($query) use ($sale) {
                    $query->where('sale_id', $sale->id)
                        ->orWhere('description', 'like', '%#' . $sale->id . '%');
                })
                ->orderBy('id')
                ->get();

            if ($hasPartialCredit) {
                $personId = $sale->employee_id ?: optional($creditRows->first())->person_id;

                if (!$personId) {
                    // حارس إضافي، من المفترض تم التحقق منه قبل المعاملة
                    return;
                }

                if ($creditRows->isNotEmpty()) {
                    $first = $creditRows->first();
                    $alreadyCollectedOnCredit = max(
                        0,
                        (float) ($first->amount ?? 0) - (float) ($first->remaining_amount ?? 0)
                    );
                    $updatedCreditAmount = $alreadyCollectedOnCredit + $remainingAmount;

                    $first->update([
                        'person_id' => $personId,
                        'sale_id' => $sale->id,
                        'person_type' => \App\Models\Employee::class,
                        'amount' => $updatedCreditAmount,
                        'remaining_amount' => $remainingAmount,
                        'description' => $creditDescription,
                        'credit_note' => $creditNote !== '' ? $creditNote : null,
                        'date' => $creditOperationDate,
                        'status' => 'pending',
                        'month' => Carbon::parse($creditOperationDate)->format('m-Y'),
                        'added_by' => $sale->accountant_id,
                    ]);

                    if ($creditRows->count() > 1) {
                        CreditSale::whereIn('id', $creditRows->slice(1)->pluck('id'))->delete();
                    }
                } else {
                    CreditSale::create([
                        'person_id' => $personId,
                        'sale_id' => $sale->id,
                        'person_type' => \App\Models\Employee::class,
                        'store_id' => $store->id,
                        'amount' => $remainingAmount,
                        'remaining_amount' => $remainingAmount,
                        'description' => $creditDescription,
                        'credit_note' => $creditNote !== '' ? $creditNote : null,
                        'date' => $creditOperationDate,
                        'status' => 'pending',
                        'month' => Carbon::parse($creditOperationDate)->format('m-Y'),
                        'added_by' => $sale->accountant_id,
                    ]);
                }
            } else {
                if ($creditRows->isNotEmpty()) {
                    CreditSale::whereIn('id', $creditRows->pluck('id'))->delete();
                }
            }
            });
        } catch (ValidationException $exception) {
            return back()
                ->withErrors($exception->errors())
                ->withInput()
                ->with('edit_sale_modal', $sale->id);
        }

        return $this->redirectAfterMutation($request, 'تم تعديل العملية بنجاح.');
    }

    public function destroy(Store $store, Sale $sale, Request $request)
    {
        if ($sale->store_id !== $store->id) {
            abort(403, 'هذه العملية لا تنتمي لهذا المتجر');
        }

        $linkedCreditRows = CreditSale::where('store_id', $store->id)
            ->where(function ($query) use ($sale) {
                $query->where('sale_id', $sale->id)
                    ->orWhere('description', 'like', '%#' . $sale->id . '%');
            })
            ->get();

        if ($linkedCreditRows->isNotEmpty() && ! $request->boolean('confirm_credit_delete')) {
            return back()->withErrors([
                'delete' => 'هذه العملية مرتبطة بأجل موظف. عند الحذف سيتم حذف الأجل المرتبط من سجل الموظف ومن كل القوائم والتقارير الجارية، وسيتم توثيق بيانات الحذف والتحصيلات في السجل. أعد المحاولة بعد تأكيد التنبيه.',
            ]);
        }

        DB::transaction(function () use ($sale, $store, $linkedCreditRows) {
            $sale->loadMissing(['items.product', 'invoice', 'employee', 'accountant']);
            $this->logDeletedLinkedCreditSales($sale, $linkedCreditRows);

            foreach ($sale->items as $item) {
                if (!$item->product || $item->product->store_id !== $store->id) {
                    continue;
                }

                $restoreQty = (float) ($item->custom_consumption ?? $item->quantity ?? 0);
                if ($restoreQty <= 0) {
                    continue;
                }

                $product = Product::query()
                    ->whereKey($item->product_id)
                    ->where('store_id', $store->id)
                    ->lockForUpdate()
                    ->first();

                if (! $product) {
                    continue;
                }

                $product->increaseStock(
                    $restoreQty,
                    'استرجاع مخزون بعد حذف عملية مبيعات #' . $sale->id,
                    auth()->id(),
                    'normalized'
                );
            }

            // حذف سجل المستند المرتبط إن وجد
            if ($sale->invoice) {
                $sale->invoice->delete();
            }

            // حذف ناعم للأجل المرتبط للحفاظ على سجل التحصيلات الجزئية والتاريخ المالي.
            CreditSale::where('store_id', $store->id)
                ->where(function ($query) use ($sale) {
                    $query->where('sale_id', $sale->id)
                        ->orWhere('description', 'like', '%#' . $sale->id . '%');
                })
                ->delete();

            $sale->items()->delete();
            // Sale لا يستخدم SoftDeletes حالياً؛ delete هنا حذف نهائي من جدول sales.
            $sale->delete();
        });

        return $this->redirectAfterMutation($request, 'تم حذف العملية واسترجاع المخزون بنجاح.');
    }



    private function logDeletedLinkedCreditSales(Sale $sale, $linkedCreditRows): void
    {
        if ($linkedCreditRows->isEmpty()) {
            return;
        }

        $actor = auth()->user();
        $actorName = $actor?->name ?? 'مالك المتجر';
        $saleItems = $sale->items->map(function ($item) {
            $name = $item->historical_product_name;
            return [
                'name' => $name,
                'quantity' => (float) ($item->quantity ?? 0),
                'price' => (float) ($item->price ?? 0),
                'total' => (float) ($item->total ?? 0),
            ];
        })->values()->all();

        foreach ($linkedCreditRows as $creditSale) {
            $person = $creditSale->person;
            if (! $person) {
                continue;
            }

            $payments = collect($creditSale->collection_payments ?? [])->values()->all();
            $collectedAmount = max(0, (float) ($creditSale->amount ?? 0) - (float) ($creditSale->remaining_amount ?? 0));
            $title = $creditSale->credit_note ?: ($creditSale->description ?: 'عملية أجل #' . $creditSale->id);

            EmployeeLogService::add(
                $person,
                'credit_sale_deleted_with_sale',
                "حذف {$actorName} عملية بيع مرتبطة بأجل: {$title}",
                (float) ($creditSale->amount ?? 0),
                [
                    'actor_id' => $actor?->id,
                    'actor_name' => $actorName,
                    'sale_id' => $sale->id,
                    'credit_sale_id' => $creditSale->id,
                    'credit_note' => $creditSale->credit_note,
                    'description' => $creditSale->description,
                    'operation_date' => optional($creditSale->date ?? $sale->business_date ?? $sale->created_at)->format('Y-m-d'),
                    'amount' => (float) ($creditSale->amount ?? 0),
                    'remaining_amount' => (float) ($creditSale->remaining_amount ?? 0),
                    'collected_amount' => $collectedAmount,
                    'status' => $creditSale->status,
                    'payments' => $payments,
                    'sale' => [
                        'sale_type' => $sale->sale_type,
                        'final_total' => (float) ($sale->final_total ?? 0),
                        'paid_amount' => (float) ($sale->paid_amount ?? 0),
                        'cash_amount' => (float) ($sale->cash_amount ?? 0),
                        'card_amount' => (float) ($sale->card_amount ?? 0),
                        'remaining_amount' => (float) ($sale->remaining_amount ?? 0),
                        'business_date' => optional($sale->business_date ?? $sale->created_at)->format('Y-m-d'),
                        'items' => $saleItems,
                    ],
                ]
            );
        }
    }

    private function redirectAfterMutation(Request $request, string $message)
    {
        $returnTo = (string) $request->query('return_to', $request->input('return_to', ''));

        if ($returnTo !== '' && str_starts_with($returnTo, url('/'))) {
            return redirect()->to($returnTo)->with('success', $message);
        }

        return back()->with('success', $message);
    }

    /**
     * معالجة عملية بيع وحساب التكاليف والأرباح بشكل صحيح
     */
    private function processSale($sale)
    {
        $totalCost = 0;
        $productsProfit = 0;
        $sale->tint_operation_name = $this->extractTintOperationName((string) ($sale->description ?? ''));

        foreach ($sale->items as $item) {
            // اسم المنتج
            if ($item->is_custom && $item->custom_name) {
                $item->display_name = $item->custom_name;
            } else {
                $item->display_name = $item->product_name ?? 'منتج غير معروف';
            }

            // الكمية الأساسية المحسوبة للمخزون
            $stockQuantity = (float) ($item->custom_consumption ?? $item->quantity ?? 0);

            // الكمية/الوحدة المعروضة للمستخدم
            $displayQuantity = (float) ($item->quantity ?? 0);
            $displayUnit = 'وحدة';

            if (!empty($item->custom_meters)) {
                $displayQuantity = (float) $item->custom_meters;
                $displayUnit = 'متر';
            } elseif (($item->product_type ?? null) === 'fractional') {
                $displayUnit = ((float) ($item->roll_length ?? 0) > 0) ? 'رول' : 'متر';
            } elseif (!empty($item->is_splittable)) {
                $itemsPerUnit = (float) ($item->items_per_unit ?? 0);
                $displayUnit = ($itemsPerUnit > 1 && abs($displayQuantity - $stockQuantity) > 0.0001) ? 'حبة' : 'طقم';
            }

            // إجمالي المنتج
            $itemTotal = $item->total ?? ($item->price * $item->quantity);

            // تكلفة المنتج
            $costPrice = (float) (((float) ($item->cost_price_at_sale ?? 0) > 0)
                ? $item->cost_price_at_sale
                : ($item->cost_price ?? 0));

            if ((float) ($item->total_cost_at_sale ?? 0) > 0) {
                // التكلفة حُسبت وحُفظت وقت البيع؛ لا نعيد تفسيرها في صفحة المبيعات.
                $itemCost = (float) $item->total_cost_at_sale;
            } elseif (($item->product_type ?? null) === 'fractional') {
                // fallback للعمليات القديمة فقط التي لا تحتوي total_cost.
                $itemCost = ProductProfitCostCalculator::calculateItemCost([
                    'cost_price' => $costPrice,
                    'product_type' => $item->product_type,
                    'roll_length' => $item->roll_length,
                ], [
                    'quantity' => $item->quantity,
                    'custom_consumption' => $stockQuantity,
                    'unit_type' => 'meter',
                ]);
            } else {
                // العمليات القديمة قبل أعمدة التكلفة تُحسب بالطريقة السابقة: تكلفة الوحدة × الكمية.
                $itemCost = $costPrice * $stockQuantity;
            }

            // ربح المنتج
            $itemProfit = $itemTotal - $itemCost;

            // تخزين القيم المحسوبة
            $item->calculated_cost = $itemCost;
            $item->calculated_profit = $itemProfit;
            $item->display_quantity = $displayQuantity;
            $item->display_unit = $displayUnit;
            $item->display_quantity_label = \App\Support\ProductQuantityFormatter::saleItemQuantity(
                (float) ($item->quantity ?? 0),
                (string) ($item->unit_type ?? ''),
                (string) ($item->product_type ?? ''),
                (bool) ($item->is_splittable ?? false),
                (float) ($item->items_per_unit ?? 0),
                (float) ($item->roll_length ?? 0),
                $stockQuantity,
                isset($item->custom_meters) ? (float) $item->custom_meters : null
            );

            $totalCost += $itemCost;
            $productsProfit += $itemProfit;
        }

        // ✅ حساب الربح بناءً على القيمة الأساسية الفعلية للعملية
        $operationTotal = max(
            (float) ($sale->final_total ?? 0),
            (float) (($sale->paid_amount ?? 0) + ($sale->remaining_amount ?? 0))
        );

        $hasOutstandingCredit = ((float) ($sale->remaining_amount ?? 0)) > 0
            && ($sale->sale_type === 'credit' || (int) ($sale->has_partial_credit ?? 0) === 1 || $sale->sale_type === 'mixed');
        $collectedAmount = max(
            (float) ($sale->paid_amount ?? 0),
            (float) ($sale->cash_amount ?? 0) + (float) ($sale->card_amount ?? 0)
        );
        $operationProfit = max(0, $operationTotal - $totalCost);
        $recognizedCost = max(0, min($totalCost, max(0, $collectedAmount)));
        $uncoveredCost = max(0, $totalCost - $recognizedCost);
        $recognizedProfit = max(0, min($operationProfit, max(0, $collectedAmount - $totalCost)));
        $deferredProfit = max(0, $operationProfit - $recognizedProfit);

        $sale->total_cost = $totalCost;
        $sale->recognized_cost = $recognizedCost;
        $sale->uncovered_cost = $uncoveredCost;
        $sale->products_profit = $productsProfit;
        $sale->operation_total = $operationTotal;
        $sale->total_profit = $operationProfit;
        $sale->recognized_profit = $recognizedProfit;
        $sale->deferred_profit = $deferredProfit;
        $sale->profit_is_deferred = $hasOutstandingCredit && ($deferredProfit > 0 || $uncoveredCost > 0);
        $sale->shift_key = 'default_shift';
        $sale->cash_paid = (float) ($sale->cash_amount ?? 0);
        $sale->card_paid = (float) ($sale->card_amount ?? 0);
        $sale->payment_label = PaymentTypeLabel::dailySalesLabel(
            $sale->sale_type,
            (float) ($sale->remaining_amount ?? 0)
        );

        // ✅ للتأكد: إجمالي المبيعات يجب أن يساوي (المنتجات + شغل اليد + الضريبة)
        // final_total = products_total + labor_total + tax

        return $sale;
    }

    /**
     * استخراج اسم عملية التضليل المحفوظ في وصف البيع لعرضه كاسم العملية.
     */
    private function extractTintOperationName(string $description): ?string
    {
        $tintParts = collect(explode(' - ', trim($description)))
            ->map(fn ($part) => trim($part))
            ->filter(function ($part) {
                return mb_stripos($part, 'تضليل') !== false
                    || mb_stripos($part, 'تظليل') !== false;
            })
            ->values();

        return $tintParts->isEmpty() ? null : $tintParts->implode(' - ');
    }

    public function updateFinancialOperation(Store $store, string $type, int $id, Request $request)
    {
        $this->authorizeStoreOwner($store);

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'date' => 'required|date',
            'description' => 'nullable|string|max:500',
        ]);

        $operation = $this->resolveFinancialOperation($store, $type, $id);
        $description = trim((string) ($validated['description'] ?? ''));

        if ($operation instanceof Debt) {
            return back()->withErrors([
                'amount' => 'لا يمكن تعديل المديونية أو تحصيلها من صفحة المبيعات اليومية. استخدم صفحة المديونية أو زر التصحيح المخصص قبل إغلاق الشفت حتى يبقى أثر الكاش/الشبكة والرصيد صحيحًا.',
            ]);
        }

        if ($operation instanceof Expense) {
            $operation->update([
                'amount' => $validated['amount'],
                'business_date' => $validated['date'],
                'description' => $description !== '' ? $description : ($operation->type ?: 'مصروف'),
            ]);
        } elseif ($operation instanceof Withdrawal) {
            $operation->update([
                'amount' => $validated['amount'],
                'date' => $validated['date'],
                'business_date' => $validated['date'],
                'month' => Carbon::parse($validated['date'])->format('m-Y'),
                'description' => $description,
            ]);
        } elseif ($operation instanceof Absence) {
            $operation->update([
                'penalty_amount' => $validated['amount'],
                'date' => $validated['date'],
                'month' => Carbon::parse($validated['date'])->format('m-Y'),
                'description' => $description,
            ]);
        }

        return back()->with('success', 'تم تعديل السجل المالي بنجاح.');
    }

    public function destroyFinancialOperation(Store $store, string $type, int $id)
    {
        $this->authorizeStoreOwner($store);
        $operation = $this->resolveFinancialOperation($store, $type, $id);

        if ($operation instanceof Debt && ! $this->canDeleteDebtOperation($operation, $store)) {
            return back()->withErrors([
                'delete' => 'المديونية نظام تراكمي ولا يتم حذفها يدويًا من اليوميات. لتسويتها استخدم التحصيل الكامل أو الجزئي، وعندما يصبح المتبقي صفرًا تختفي تلقائيًا من قوائم المديونيات القائمة.',
            ]);
        }

        $operation->delete();

        $message = match ($type) {
            'expense' => 'تم حذف المصروف بنجاح.',
            'withdrawal' => 'تم حذف السحب بنجاح.',
            'debt' => 'تم حذف المديونية بنجاح.',
            'absence' => 'تم حذف الغياب بنجاح.',
            default => 'تم حذف السجل بنجاح.',
        };

        return back()->with('success', $message);
    }


    private function canDeleteDebtOperation(Debt $debt, Store $store): bool
    {
        // المديونية ليست عملية يومية قابلة للحذف مثل المصروف أو السحب؛ هي رصيد تراكمي.
        // عند التسوية يتم تخفيض المبلغ عبر سجل تحصيل سالب، وعند الوصول للصفر تختفي من القوائم النشطة بدون حذف فعلي.
        return false;
    }

    private function authorizeStoreOwner(Store $store): void
    {
        $owner = auth()->user();
        if (!$owner || (int) $store->user_id !== (int) $owner->id) {
            abort(403, 'لا تملك صلاحية تعديل هذه الصفحة');
        }
    }

    private function resolveFinancialOperation(Store $store, string $type, int $id)
    {
        return match ($type) {
            'expense' => Expense::where('store_id', $store->id)->findOrFail($id),
            'withdrawal' => Withdrawal::where('store_id', $store->id)->findOrFail($id),
            'debt' => Debt::where('store_id', $store->id)->findOrFail($id),
            'absence' => Absence::where('store_id', $store->id)->findOrFail($id),
            default => abort(404),
        };
    }
}
