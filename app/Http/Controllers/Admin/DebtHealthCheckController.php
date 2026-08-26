<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Debt;
use Illuminate\Support\Facades\DB;

class DebtHealthCheckController extends Controller
{
    /**
     * صفحة فحص قراءة فقط لنظام المديونية.
     * مهم: هذه الصفحة لا تنفذ update/delete/insert، بل تعرض نتائج استعلامات SELECT فقط حتى لا تؤثر على بيانات العملاء.
     */
    public function index()
    {
        $issues = [
            'missing_employee' => [
                'title' => 'مديونية بدون موظف',
                'hint' => 'سجلات مديونية لا يوجد لها موظف صالح أو تم حذف الموظف المرتبط بها.',
                'severity' => 'danger',
                'rows' => $this->missingEmployeeDebts(),
            ],
            'collection_without_parent' => [
                'title' => 'تحصيل بدون ربط بالمديونية الأصلية',
                'hint' => 'سجلات تحصيل سالبة لا تحتوي على debt_parent_id، وهذا يجعل معرفة أصل السداد غير دقيقة.',
                'severity' => 'warning',
                'rows' => $this->collectionsWithoutParent(),
            ],
            'collection_parent_missing' => [
                'title' => 'تحصيل مرتبط بمديونية غير موجودة',
                'hint' => 'سجل التحصيل يحتوي على debt_parent_id لكن لا توجد مديونية أصلية مقابلة.',
                'severity' => 'danger',
                'rows' => $this->collectionsWithMissingParent(),
            ],
            'collection_parent_mismatch' => [
                'title' => 'تحصيل مرتبط بمديونية لا تطابق الموظف أو المتجر',
                'hint' => 'التحصيل يجب أن يكون تابعًا لمديونية أصلية لنفس المتجر ونفس الموظف ونفس نوع الشخص.',
                'severity' => 'danger',
                'rows' => $this->collectionsWithMismatchedParent(),
            ],
            'duplicate_collection' => [
                'title' => 'تحصيل مكرر لنفس المديونية',
                'hint' => 'أكثر من سجل تحصيل لنفس أصل المديونية وبنفس المبلغ والوصف والتاريخ.',
                'severity' => 'warning',
                'rows' => $this->duplicateCollections(),
            ],
            'deducted_positive_balance' => [
                'title' => 'مديونية مسددة وبها رصيد موجب',
                'hint' => 'إذا كانت الحالة deducted فمن المتوقع ألا يبقى مبلغ موجب قابل للتحصيل.',
                'severity' => 'danger',
                'rows' => $this->deductedPositiveBalances(),
            ],
        ];

        $summary = collect($issues)->map(fn (array $issue) => count($issue['rows']));
        $totalIssues = (int) $summary->sum();

        return view('admin.health.debt', compact('issues', 'summary', 'totalIssues'));
    }

    private function baseDebtQuery(string $alias = 'debts')
    {
        return DB::table('debts as ' . $alias)
            ->leftJoin('stores', 'stores.id', '=', $alias . '.store_id')
            ->leftJoin('employees', function ($join) use ($alias) {
                $join->on('employees.id', '=', $alias . '.person_id')
                    ->where($alias . '.person_type', '=', 'App\\Models\\Employee');
            })
            ->whereNull($alias . '.deleted_at');
    }

    private function selectDebtColumns(string $alias = 'debts'): array
    {
        return [
            $alias . '.id',
            $alias . '.store_id',
            'stores.name as store_name',
            $alias . '.person_id',
            $alias . '.person_type',
            'employees.name as employee_name',
            $alias . '.debt_parent_id',
            $alias . '.amount',
            $alias . '.description',
            $alias . '.status',
            $alias . '.date',
            $alias . '.created_at',
        ];
    }

    private function missingEmployeeDebts(): array
    {
        return $this->baseDebtQuery()
            ->where(function ($query) {
                $query->whereNull('debts.person_id')
                    ->orWhere(function ($employeeQuery) {
                        $employeeQuery->where('debts.person_type', 'App\\Models\\Employee')
                            ->whereNull('employees.id');
                    });
            })
            ->select($this->selectDebtColumns())
            ->orderByDesc('debts.id')
            ->limit(100)
            ->get()
            ->map(fn ($row) => $this->debtRow($row))
            ->all();
    }

    private function collectionsWithoutParent(): array
    {
        return $this->baseDebtQuery()
            ->where('debts.amount', '<', 0)
            ->whereNull('debts.debt_parent_id')
            ->select($this->selectDebtColumns())
            ->orderByDesc('debts.id')
            ->limit(100)
            ->get()
            ->map(fn ($row) => $this->debtRow($row))
            ->all();
    }

    private function collectionsWithMissingParent(): array
    {
        return $this->baseDebtQuery()
            ->leftJoin('debts as parent_debts', function ($join) {
                $join->on('parent_debts.id', '=', 'debts.debt_parent_id')
                    ->whereNull('parent_debts.deleted_at');
            })
            ->where('debts.amount', '<', 0)
            ->whereNotNull('debts.debt_parent_id')
            ->whereNull('parent_debts.id')
            ->select($this->selectDebtColumns())
            ->orderByDesc('debts.id')
            ->limit(100)
            ->get()
            ->map(fn ($row) => $this->debtRow($row))
            ->all();
    }

    private function collectionsWithMismatchedParent(): array
    {
        return $this->baseDebtQuery()
            ->join('debts as parent_debts', function ($join) {
                $join->on('parent_debts.id', '=', 'debts.debt_parent_id')
                    ->whereNull('parent_debts.deleted_at');
            })
            ->where('debts.amount', '<', 0)
            ->where(function ($query) {
                $query->whereColumn('debts.store_id', '!=', 'parent_debts.store_id')
                    ->orWhereColumn('debts.person_id', '!=', 'parent_debts.person_id')
                    ->orWhereColumn('debts.person_type', '!=', 'parent_debts.person_type')
                    // لا نعدّ الأصل المسدد برصيد 0 خطأ؛ الخطأ أن يكون أصل التحصيل نفسه سجل تحصيل سالب أو تابعًا لأصل آخر.
                    ->orWhere('parent_debts.amount', '<', 0)
                    ->orWhereNotNull('parent_debts.debt_parent_id');
            })
            ->select(array_merge($this->selectDebtColumns(), [
                'parent_debts.amount as parent_amount',
                'parent_debts.status as parent_status',
            ]))
            ->orderByDesc('debts.id')
            ->limit(100)
            ->get()
            ->map(fn ($row) => $this->debtRow($row, [
                'parent_amount' => (float) $row->parent_amount,
                'parent_status' => $row->parent_status,
            ]))
            ->all();
    }

    private function duplicateCollections(): array
    {
        $duplicates = DB::table('debts')
            ->select([
                'debt_parent_id',
                'person_id',
                'person_type',
                'store_id',
                'amount',
                'description',
                'date',
                DB::raw('COUNT(*) as duplicate_count'),
                DB::raw('MIN(id) as first_id'),
                DB::raw('MAX(id) as last_id'),
            ])
            ->whereNull('deleted_at')
            ->where('amount', '<', 0)
            ->whereNotNull('debt_parent_id')
            ->groupBy('debt_parent_id', 'person_id', 'person_type', 'store_id', 'amount', 'description', 'date')
            ->havingRaw('COUNT(*) > 1');

        return DB::query()
            ->fromSub($duplicates, 'duplicate_debts')
            ->leftJoin('stores', 'stores.id', '=', 'duplicate_debts.store_id')
            ->leftJoin('employees', function ($join) {
                $join->on('employees.id', '=', 'duplicate_debts.person_id')
                    ->where('duplicate_debts.person_type', '=', 'App\\Models\\Employee');
            })
            ->select([
                'duplicate_debts.*',
                'stores.name as store_name',
                'employees.name as employee_name',
            ])
            ->orderByDesc('last_id')
            ->limit(100)
            ->get()
            ->map(fn ($row) => [
                'type' => 'duplicate_collection',
                'id' => (int) $row->last_id,
                'first_id' => (int) $row->first_id,
                'store' => $row->store_name ?: ('متجر #' . $row->store_id),
                'employee' => $row->employee_name ?: ('موظف #' . ($row->person_id ?? 'غير محدد')),
                'debt_parent_id' => (int) $row->debt_parent_id,
                'amount' => (float) $row->amount,
                'description' => $row->description,
                'status' => 'duplicate',
                'date' => $row->date,
                'duplicate_count' => (int) $row->duplicate_count,
            ])
            ->all();
    }

    private function deductedPositiveBalances(): array
    {
        return $this->baseDebtQuery()
            ->where('debts.status', Debt::STATUS_DEDUCTED)
            ->where('debts.amount', '>', 0.01)
            ->select($this->selectDebtColumns())
            ->orderByDesc('debts.id')
            ->limit(100)
            ->get()
            ->map(fn ($row) => $this->debtRow($row))
            ->all();
    }

    private function debtRow($row, array $extra = []): array
    {
        return array_merge([
            'type' => (float) $row->amount < 0 ? 'collection' : 'debt',
            'id' => (int) $row->id,
            'store' => $row->store_name ?: ('متجر #' . $row->store_id),
            'employee' => $row->employee_name ?: ('موظف #' . ($row->person_id ?? 'غير محدد')),
            'debt_parent_id' => $row->debt_parent_id ? (int) $row->debt_parent_id : null,
            'amount' => (float) $row->amount,
            'description' => $row->description,
            'status' => $row->status,
            'date' => $row->date ?: $row->created_at,
        ], $extra);
    }
}
