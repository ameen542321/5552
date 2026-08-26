<?php

namespace App\Services\Accounting;

use App\Models\Debt;
use App\Models\Expense;
use App\Models\Sale;
use App\Models\Withdrawal;
use App\Models\Accountant;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;
use Carbon\CarbonInterface;

class AccountingOperationFeedService
{
    public function __construct(private readonly AccountingOperationPresenter $presenter)
    {
    }

    public function latestForStore(int $storeId, int $perPage = 10, int $page = 1, ?string $path = null, array $query = []): LengthAwarePaginator
    {
        // سجل آخر العمليات في لوحة المحاسب مستقل عن الشفت ويعتمد على التاريخ لا تاريخ الإدخال.
        $sales = Sale::with(['items.product:id,name', 'accountant:id,name', 'employee:id,name'])
            ->where('store_id', $storeId)
            ->where(function ($query) {
                $query->whereNull('description')
                    ->orWhere('description', '!=', 'manual_invoice_entry');
            })
            ->orderByDesc(DB::raw('COALESCE(business_date, DATE(created_at))'))
            ->latest()
            ->take(120)
            ->get()
            ->map(fn ($sale) => $this->presenter->lastOperation($sale, 'sale'));

        $expenses = Expense::with(['user:id,name', 'employee:id,name'])
            ->where('store_id', $storeId)
            ->orderByDesc(DB::raw('COALESCE(business_date, DATE(created_at))'))
            ->latest()
            ->take(120)
            ->get()
            ->map(fn ($expense) => $this->presenter->lastOperation($expense, 'expense'));

        $withdrawals = Withdrawal::with(['addedBy:id,name', 'person'])
            ->where('store_id', $storeId)
            ->orderByDesc(DB::raw('COALESCE(business_date, date, DATE(created_at))'))
            ->latest()
            ->take(120)
            ->get()
            ->map(fn ($withdrawal) => $this->presenter->lastOperation($withdrawal, 'withdrawal'));

        $operationRows = $sales->concat($expenses)->concat($withdrawals)
            ->sortByDesc(fn ($operation) => $operation->sort_key)
            ->take($perPage)
            ->values();

        return new LengthAwarePaginator(
            $operationRows,
            $operationRows->count(),
            $perPage,
            $page,
            ['path' => $path, 'pageName' => 'operations_page', 'query' => $query]
        );
    }

    public function shiftDetails(int $storeId, CarbonInterface $shiftStart, array $creditCollections = [], ?string $businessDate = null): array
    {
        $sales = Sale::with(['items.product:id,name', 'accountant:id,name', 'employee:id,name'])
            ->where('store_id', $storeId)
            ->where(function ($query) {
                $query->whereNull('description')
                    ->orWhere('description', '!=', 'manual_invoice_entry');
            })
            ->forOpenAccountingShift($businessDate, $shiftStart)
            ->latest()
            ->get()
            ->map(fn ($sale) => $this->presenter->saleDetail($sale));

        $collections = collect($creditCollections['details'] ?? [])
            ->map(fn ($collection) => $this->presenter->creditCollectionDetail($collection));

        $expenses = Expense::with(['user:id,name', 'employee:id,name'])
            ->where('store_id', $storeId)
            ->forOpenAccountingShift($businessDate, $shiftStart)
            ->latest()
            ->get()
            ->map(fn ($expense) => $this->presenter->expenseDetail($expense));

        $withdrawals = Withdrawal::with(['addedBy:id,name', 'person'])
            ->where('store_id', $storeId)
            ->forOpenAccountingShift($businessDate, $shiftStart)
            ->latest()
            ->get()
            ->map(fn ($withdrawal) => $this->presenter->withdrawalDetail($withdrawal));

        $accountantIds = Accountant::where('store_id', $storeId)->pluck('id');

        $debts = Debt::where('store_id', $storeId)
            // نعرض في تفاصيل المحاسب المديونيات التي سجلها حساب محاسب فقط، لا ما أدخله المالك.
            ->whereIn('added_by', $accountantIds)
            ->when(
                $businessDate,
                fn ($query) => $query->whereDate('date', $businessDate),
                fn ($query) => $query->where('created_at', '>', $shiftStart)
            )
            ->where('amount', '>', 0)
            ->latest()
            ->get()
            ->map(fn ($debt) => $this->presenter->debtDetail($debt));

        $rows = $sales
            ->concat($collections)
            ->concat($expenses)
            ->concat($withdrawals)
            ->concat($debts)
            ->sortByDesc('time')
            ->values();

        return [
            'rows' => $rows,
            'count' => $rows->count(),
            'total_in' => (float) ($sales->sum('amount') + $collections->sum('amount')),
            'total_out' => (float) (
                $expenses->sum('amount')
                + $withdrawals->sum('amount')
                + $debts->sum('amount')
            ),
            'sales_total' => (float) $sales->sum('amount'),
            'cash_total' => (float) ($sales->sum('cash_amount') + $collections->sum('cash_amount')),
            'card_total' => (float) ($sales->sum('card_amount') + $collections->sum('card_amount')),
            'credit_total' => (float) $sales->where('payment_type', 'آجل')->sum('amount'),
            'collections_total' => (float) $collections->sum('amount'),
            'expenses_total' => (float) $expenses->sum('amount'),
            'withdrawals_total' => (float) $withdrawals->sum('amount'),
            'cost_total' => (float) $sales->sum('cost_amount'),
        ];
    }
}
