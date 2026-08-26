<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CreditSale;
use Illuminate\Support\Facades\DB;

class CreditHealthCheckController extends Controller
{
    public function index()
    {
        $issues = [
            'missing_employee' => [
                'title' => 'أجل بدون موظف',
                'hint' => 'عمليات أجل لا يوجد لها موظف صالح أو تم حذف الموظف المرتبط بها.',
                'severity' => 'danger',
                'rows' => $this->missingEmployeeCredits(),
            ],
            'missing_sale_id' => [
                'title' => 'أجل بدون sale_id',
                'hint' => 'عمليات أجل لا تملك ربطًا مباشرًا بعملية بيع يومية. هذه تحتاج ربطًا هيكليًا بدل الاعتماد على الوصف.',
                'severity' => 'warning',
                'rows' => $this->missingSaleIdCredits(),
            ],
            'remaining_mismatch' => [
                'title' => 'المتبقي لا يطابق مجموع التحصيلات',
                'hint' => 'المتبقي يجب أن يساوي إجمالي الأجل ناقص مجموع التحصيلات المسجلة في جدول التحصيلات.',
                'severity' => 'danger',
                'rows' => $this->remainingMismatchCredits(),
            ],
            'bad_mixed_payments' => [
                'title' => 'دفعات ميكس مجموعها خاطئ',
                'hint' => 'في تحصيل الميكس يجب أن يساوي مجموع الكاش والشبكة مبلغ التحصيل.',
                'severity' => 'danger',
                'rows' => $this->badMixedCollections(),
            ],
            'bad_status' => [
                'title' => 'status غير مطابق للمتبقي',
                'hint' => 'إذا المتبقي صفر يجب أن تكون الحالة deducted، وإذا يوجد متبقي يجب ألا تكون deducted.',
                'severity' => 'warning',
                'rows' => $this->badStatusCredits(),
            ],
            'missing_collector' => [
                'title' => 'تحصيل بلا محصل',
                'hint' => 'تحصيلات لا تحتوي على المستخدم/المحاسب الذي نفذ التحصيل.',
                'severity' => 'warning',
                'rows' => $this->missingCollectorCollections(),
            ],
            'deleted_sale_link' => [
                'title' => 'أجل مرتبط بعملية محذوفة',
                'hint' => 'عمليات أجل لديها sale_id لكن لا توجد عملية بيع مقابلة في جدول sales.',
                'severity' => 'danger',
                'rows' => $this->creditsLinkedToDeletedSales(),
            ],
        ];

        $summary = collect($issues)->map(fn (array $issue) => count($issue['rows']));
        $totalIssues = (int) $summary->sum();

        return view('admin.health.credit', compact('issues', 'summary', 'totalIssues'));
    }

    private function baseCreditQuery()
    {
        return DB::table('credit_sales')
            ->leftJoin('stores', 'stores.id', '=', 'credit_sales.store_id')
            ->leftJoin('employees', function ($join) {
                $join->on('employees.id', '=', 'credit_sales.person_id')
                    ->where('credit_sales.person_type', '=', 'App\\Models\\Employee');
            })
            ->whereNull('credit_sales.deleted_at');
    }

    private function selectCreditColumns(): array
    {
        return [
            'credit_sales.id',
            'credit_sales.store_id',
            'stores.name as store_name',
            'credit_sales.person_id',
            'employees.name as employee_name',
            'credit_sales.sale_id',
            'credit_sales.amount',
            'credit_sales.remaining_amount',
            'credit_sales.status',
            'credit_sales.credit_note',
            'credit_sales.description',
            'credit_sales.date',
        ];
    }

    private function missingEmployeeCredits(): array
    {
        return $this->baseCreditQuery()
            ->where(function ($query) {
                $query->whereNull('credit_sales.person_id')
                    ->orWhere(function ($employeeQuery) {
                        $employeeQuery->where('credit_sales.person_type', 'App\\Models\\Employee')
                            ->whereNull('employees.id');
                    });
            })
            ->select($this->selectCreditColumns())
            ->orderByDesc('credit_sales.id')
            ->limit(100)
            ->get()
            ->map(fn ($row) => $this->creditRow($row))
            ->all();
    }

    private function missingSaleIdCredits(): array
    {
        return $this->baseCreditQuery()
            ->whereNull('credit_sales.sale_id')
            ->select($this->selectCreditColumns())
            ->orderByDesc('credit_sales.id')
            ->limit(100)
            ->get()
            ->map(fn ($row) => $this->creditRow($row))
            ->all();
    }

    private function remainingMismatchCredits(): array
    {
        $collectionTotals = DB::table('employee_credit_collections')
            ->select('credit_sale_id', DB::raw('COALESCE(SUM(amount), 0) as collected_total'))
            ->groupBy('credit_sale_id');

        return $this->baseCreditQuery()
            ->leftJoinSub($collectionTotals, 'collection_totals', function ($join) {
                $join->on('collection_totals.credit_sale_id', '=', 'credit_sales.id');
            })
            ->whereRaw('ABS(COALESCE(credit_sales.remaining_amount, 0) - (CASE WHEN (COALESCE(credit_sales.amount, 0) - COALESCE(collection_totals.collected_total, 0)) < 0 THEN 0 ELSE (COALESCE(credit_sales.amount, 0) - COALESCE(collection_totals.collected_total, 0)) END)) > 0.01')
            ->select(array_merge($this->selectCreditColumns(), [
                DB::raw('COALESCE(collection_totals.collected_total, 0) as collected_total'),
                DB::raw('CASE WHEN (COALESCE(credit_sales.amount, 0) - COALESCE(collection_totals.collected_total, 0)) < 0 THEN 0 ELSE (COALESCE(credit_sales.amount, 0) - COALESCE(collection_totals.collected_total, 0)) END as expected_remaining'),
            ]))
            ->orderByDesc('credit_sales.id')
            ->limit(100)
            ->get()
            ->map(fn ($row) => $this->creditRow($row, [
                'collected_total' => (float) $row->collected_total,
                'expected_remaining' => (float) $row->expected_remaining,
            ]))
            ->all();
    }

    private function badMixedCollections(): array
    {
        return DB::table('employee_credit_collections')
            ->leftJoin('credit_sales', 'credit_sales.id', '=', 'employee_credit_collections.credit_sale_id')
            ->leftJoin('stores', 'stores.id', '=', 'employee_credit_collections.store_id')
            ->leftJoin('employees', function ($join) {
                $join->on('employees.id', '=', 'employee_credit_collections.person_id')
                    ->where('employee_credit_collections.person_type', '=', 'App\\Models\\Employee');
            })
            ->where('employee_credit_collections.payment_method', 'mixed')
            ->whereRaw('ABS((COALESCE(employee_credit_collections.cash_amount, 0) + COALESCE(employee_credit_collections.card_amount, 0)) - COALESCE(employee_credit_collections.amount, 0)) > 0.01')
            ->select([
                'employee_credit_collections.id',
                'employee_credit_collections.credit_sale_id',
                'employee_credit_collections.sale_id',
                'employee_credit_collections.store_id',
                'stores.name as store_name',
                'employee_credit_collections.person_id',
                'employees.name as employee_name',
                'employee_credit_collections.amount',
                'employee_credit_collections.cash_amount',
                'employee_credit_collections.card_amount',
                'employee_credit_collections.collection_date',
                'credit_sales.credit_note',
            ])
            ->orderByDesc('employee_credit_collections.id')
            ->limit(100)
            ->get()
            ->map(fn ($row) => $this->collectionRow($row, [
                'expected_amount' => (float) $row->cash_amount + (float) $row->card_amount,
            ]))
            ->all();
    }

    private function badStatusCredits(): array
    {
        return $this->baseCreditQuery()
            ->where(function ($query) {
                $query->where(function ($zeroRemaining) {
                    $zeroRemaining->where('credit_sales.remaining_amount', '<=', 0.01)
                        ->where('credit_sales.status', '!=', CreditSale::STATUS_DEDUCTED);
                })->orWhere(function ($hasRemaining) {
                    $hasRemaining->where('credit_sales.remaining_amount', '>', 0.01)
                        ->where('credit_sales.status', CreditSale::STATUS_DEDUCTED);
                });
            })
            ->select($this->selectCreditColumns())
            ->orderByDesc('credit_sales.id')
            ->limit(100)
            ->get()
            ->map(fn ($row) => $this->creditRow($row))
            ->all();
    }

    private function missingCollectorCollections(): array
    {
        return DB::table('employee_credit_collections')
            ->leftJoin('credit_sales', 'credit_sales.id', '=', 'employee_credit_collections.credit_sale_id')
            ->leftJoin('stores', 'stores.id', '=', 'employee_credit_collections.store_id')
            ->leftJoin('employees', function ($join) {
                $join->on('employees.id', '=', 'employee_credit_collections.person_id')
                    ->where('employee_credit_collections.person_type', '=', 'App\\Models\\Employee');
            })
            ->whereNull('employee_credit_collections.collected_by')
            ->select([
                'employee_credit_collections.id',
                'employee_credit_collections.credit_sale_id',
                'employee_credit_collections.sale_id',
                'employee_credit_collections.store_id',
                'stores.name as store_name',
                'employee_credit_collections.person_id',
                'employees.name as employee_name',
                'employee_credit_collections.amount',
                'employee_credit_collections.cash_amount',
                'employee_credit_collections.card_amount',
                'employee_credit_collections.collection_date',
                'credit_sales.credit_note',
            ])
            ->orderByDesc('employee_credit_collections.id')
            ->limit(100)
            ->get()
            ->map(fn ($row) => $this->collectionRow($row))
            ->all();
    }

    private function creditsLinkedToDeletedSales(): array
    {
        return $this->baseCreditQuery()
            ->leftJoin('sales', 'sales.id', '=', 'credit_sales.sale_id')
            ->whereNotNull('credit_sales.sale_id')
            ->whereNull('sales.id')
            ->select($this->selectCreditColumns())
            ->orderByDesc('credit_sales.id')
            ->limit(100)
            ->get()
            ->map(fn ($row) => $this->creditRow($row))
            ->all();
    }

    private function creditRow($row, array $extra = []): array
    {
        return array_merge([
            'type' => 'credit',
            'id' => (int) $row->id,
            'store' => $row->store_name ?: ('متجر #' . $row->store_id),
            'employee' => $row->employee_name ?: ('موظف #' . ($row->person_id ?? 'غير محدد')),
            'sale_id' => $row->sale_id,
            'credit_note' => $row->credit_note ?: $row->description,
            'amount' => (float) $row->amount,
            'remaining_amount' => (float) $row->remaining_amount,
            'status' => $row->status,
            'date' => $row->date,
        ], $extra);
    }

    private function collectionRow($row, array $extra = []): array
    {
        return array_merge([
            'type' => 'collection',
            'id' => (int) $row->id,
            'credit_sale_id' => (int) $row->credit_sale_id,
            'store' => $row->store_name ?: ('متجر #' . $row->store_id),
            'employee' => $row->employee_name ?: ('موظف #' . ($row->person_id ?? 'غير محدد')),
            'sale_id' => $row->sale_id,
            'credit_note' => $row->credit_note,
            'amount' => (float) $row->amount,
            'cash_amount' => (float) $row->cash_amount,
            'card_amount' => (float) $row->card_amount,
            'collection_date' => $row->collection_date,
        ], $extra);
    }
}
