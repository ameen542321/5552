<?php

namespace App\Services\Employees;

use App\Models\Accountant;
use App\Models\Employee;
use App\Models\EmployeeLog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class EmployeeTransferAuditService
{
    public const HISTORICAL_TABLES = [
        'employee_withdrawals',
        'debts',
        'credit_sales',
        'employee_credit_collections',
        'employee_absences',
        'employee_logs',
    ];

    public function __construct(
        private readonly EmployeeHistoricalStoreService $historicalStores,
    ) {}

    /**
     * يبني تقرير معاينة فقط. لا تعدّل هذه الخدمة أي سجل.
     */
    public function report(): array
    {
        $mismatchedAccountants = Accountant::withTrashed()
            ->join('employees', 'employees.id', '=', 'accountants.employee_id')
            ->join('stores as employee_stores', 'employee_stores.id', '=', 'employees.store_id')
            ->where(function ($query) {
                $query->whereColumn('accountants.store_id', '!=', 'employees.store_id')
                    ->orWhereColumn('accountants.user_id', '!=', 'employee_stores.user_id');
            })
            ->get([
                'accountants.id as accountant_id',
                'accountants.store_id as accountant_store_id',
                'employees.id as employee_id',
                'employees.store_id as employee_store_id',
            ]);

        $orphanSales = collect();
        if (Schema::hasTable('sales') && Schema::hasColumn('sales', 'accountant_id')) {
            $orphanSales = DB::table('sales')
                ->leftJoin('accountants', 'accountants.id', '=', 'sales.accountant_id')
                ->whereNotNull('sales.accountant_id')
                ->whereNull('accountants.id')
                ->get(['sales.id', 'sales.store_id', 'sales.accountant_id']);
        }

        $transfers = EmployeeLog::withTrashed()
            ->where('person_type', Employee::class)
            ->where('action_name', 'employee_transferred')
            ->orderBy('person_id')
            ->orderBy('created_at')
            ->get();

        $incompleteTransfers = $transfers->filter(function (EmployeeLog $log) {
            $meta = $log->meta ?: [];

            return ! isset(
                $meta['old_store_id'],
                $meta['new_store_id'],
                $meta['effective_date'],
                $meta['transferred_personal_debt_balance'],
            );
        })->values();

        $duplicateBalanceSnapshots = $transfers
            ->filter(fn (EmployeeLog $log) => isset($log->meta['transferred_personal_debt_balance']))
            ->groupBy(fn (EmployeeLog $log) => implode(':', [
                $log->person_id,
                $log->meta['old_store_id'] ?? '',
                $log->meta['new_store_id'] ?? '',
                $log->meta['effective_date'] ?? '',
            ]))
            ->filter(fn (Collection $logs) => $logs->count() > 1)
            ->flatten(1)
            ->values();

        $suspectedMovedHistoricalRows = $this->suspectedMovedHistoricalRows(
            $transfers,
            $incompleteTransfers->pluck('person_id')->map(fn ($id) => (int) $id)->unique(),
        );

        return compact(
            'mismatchedAccountants',
            'orphanSales',
            'incompleteTransfers',
            'duplicateBalanceSnapshots',
            'suspectedMovedHistoricalRows',
        );
    }

    private function suspectedMovedHistoricalRows(Collection $transfers, Collection $unsafeEmployeeIds): Collection
    {
        $suspects = collect();
        $employeeIds = $transfers->pluck('person_id')
            ->map(fn ($id) => (int) $id)
            ->reject(fn ($id) => $unsafeEmployeeIds->contains($id))
            ->unique()
            ->values();
        $employees = Employee::withTrashed()->whereIn('id', $employeeIds)->get()->keyBy('id');

        foreach ($employees as $employee) {
            foreach (self::HISTORICAL_TABLES as $table) {
                if (! $this->supportsHistoricalAudit($table)) {
                    continue;
                }

                $dateExpression = $this->dateExpression($table);
                $columns = ['id', 'store_id', DB::raw("{$dateExpression} as operation_date")];
                if (Schema::hasColumn($table, 'amount')) {
                    $columns[] = 'amount';
                }
                if (in_array($table, ['credit_sales', 'employee_credit_collections'], true)
                    && Schema::hasColumn($table, 'sale_id')) {
                    $columns[] = 'sale_id';
                }
                if ($table === 'employee_credit_collections' && Schema::hasColumn($table, 'credit_sale_id')) {
                    $columns[] = 'credit_sale_id';
                }
                if ($table === 'employee_logs') {
                    $columns[] = 'action_name';
                }

                $rows = DB::table($table)
                    ->where('person_type', Employee::class)
                    ->where('person_id', $employee->id)
                    ->when($table === 'employee_logs', fn ($query) => $query->where('action_name', '!=', 'employee_transferred'))
                    ->get($columns);

                foreach ($rows as $row) {
                    if (! $row->operation_date) {
                        continue;
                    }

                    $expectedStoreId = $this->expectedStoreId($employee, $table, $row);
                    if ($expectedStoreId <= 0 || $expectedStoreId === (int) $row->store_id) {
                        continue;
                    }

                    $suspects->push([
                        'employee_id' => (int) $employee->id,
                        'table' => $table,
                        'row_id' => (int) $row->id,
                        'recorded_store_id' => (int) $row->store_id,
                        'expected_store_id' => $expectedStoreId,
                        'recorded_date' => (string) $row->operation_date,
                        'amount' => (float) ($row->amount ?? 0),
                        'basis' => $this->storeResolutionBasis($table, $row),
                    ]);
                }
            }
        }

        return $suspects;
    }

    private function supportsHistoricalAudit(string $table): bool
    {
        return Schema::hasTable($table)
            && Schema::hasColumn($table, 'person_id')
            && Schema::hasColumn($table, 'person_type')
            && Schema::hasColumn($table, 'store_id')
            && (Schema::hasColumn($table, 'date')
                || Schema::hasColumn($table, 'business_date')
                || Schema::hasColumn($table, 'collection_date')
                || Schema::hasColumn($table, 'month')
                || Schema::hasColumn($table, 'created_at'));
    }

    private function dateExpression(string $table): string
    {
        $columns = collect(['business_date', 'collection_date', 'date', 'created_at'])
            ->filter(fn ($column) => Schema::hasColumn($table, $column))
            ->values();

        return $columns->count() > 1
            ? 'COALESCE(' . $columns->implode(', ') . ')'
            : (string) $columns->first();
    }

    private function expectedStoreId(Employee $employee, string $table, object $row): int
    {
        if (in_array($table, ['credit_sales', 'employee_credit_collections'], true)
            && ! empty($row->sale_id)
            && Schema::hasTable('sales')) {
            $saleStoreId = DB::table('sales')->where('id', $row->sale_id)->value('store_id');
            if ($saleStoreId) {
                return (int) $saleStoreId;
            }
        }

        if ($table === 'employee_credit_collections'
            && ! empty($row->credit_sale_id)
            && Schema::hasTable('credit_sales')) {
            $creditStoreId = DB::table('credit_sales')->where('id', $row->credit_sale_id)->value('store_id');
            if ($creditStoreId) {
                return (int) $creditStoreId;
            }
        }

        return $this->historicalStores->employeeStoreIdAtPeriodEnd($employee, $row->operation_date);
    }

    private function storeResolutionBasis(string $table, object $row): string
    {
        if (in_array($table, ['credit_sales', 'employee_credit_collections'], true) && ! empty($row->sale_id)) {
            return 'linked_sale_store';
        }

        if ($table === 'employee_credit_collections' && ! empty($row->credit_sale_id)) {
            return 'linked_credit_sale_store';
        }

        return 'employee_transfer_timeline';
    }
}
