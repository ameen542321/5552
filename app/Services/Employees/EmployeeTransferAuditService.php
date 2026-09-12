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

        $suspectedMovedHistoricalRows = $this->suspectedMovedHistoricalRows($transfers);

        return compact(
            'mismatchedAccountants',
            'orphanSales',
            'incompleteTransfers',
            'duplicateBalanceSnapshots',
            'suspectedMovedHistoricalRows',
        );
    }

    private function suspectedMovedHistoricalRows(Collection $transfers): Collection
    {
        $tables = [
            'employee_withdrawals',
            'debts',
            'credit_sales',
            'employee_absences',
            'employee_salary_reports',
        ];
        $suspects = collect();

        foreach ($transfers as $transfer) {
            $meta = $transfer->meta ?: [];
            if (! isset($meta['new_store_id'], $meta['effective_date'])) {
                continue;
            }

            foreach ($tables as $table) {
                if (! Schema::hasTable($table)
                    || ! Schema::hasColumn($table, 'person_id')
                    || ! Schema::hasColumn($table, 'person_type')
                    || ! Schema::hasColumn($table, 'store_id')) {
                    continue;
                }

                $dateColumn = Schema::hasColumn($table, 'date') ? 'date' : 'created_at';
                $rows = DB::table($table)
                    ->where('person_type', Employee::class)
                    ->where('person_id', $transfer->person_id)
                    ->where('store_id', $meta['new_store_id'])
                    ->whereDate($dateColumn, '<', $meta['effective_date'])
                    ->get(['id', 'store_id', $dateColumn]);

                foreach ($rows as $row) {
                    $suspects->push([
                        'employee_id' => $transfer->person_id,
                        'transfer_log_id' => $transfer->id,
                        'table' => $table,
                        'row_id' => $row->id,
                        'recorded_store_id' => $row->store_id,
                        'recorded_date' => $row->{$dateColumn},
                    ]);
                }
            }
        }

        return $suspects;
    }
}
