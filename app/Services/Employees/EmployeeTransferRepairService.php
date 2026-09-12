<?php

namespace App\Services\Employees;

use App\Models\Employee;
use App\Services\EmployeeLogService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class EmployeeTransferRepairService
{
    public function __construct(
        private readonly EmployeeTransferAuditService $audit,
        private readonly EmployeeAccountantLifecycleService $accountantLifecycle,
    ) {}

    public function preview(): array
    {
        $report = $this->audit->report();
        $report['affectedByStore'] = collect($report['suspectedMovedHistoricalRows'])
            ->groupBy(fn (array $row) => $row['recorded_store_id'] . ':' . $row['expected_store_id'])
            ->map(function (Collection $rows) {
                $first = $rows->first();

                return [
                    'from_store_id' => $first['recorded_store_id'],
                    'to_store_id' => $first['expected_store_id'],
                    'rows_count' => $rows->count(),
                    'amount_total' => round($rows->sum('amount'), 2),
                ];
            })
            ->values();

        return $report;
    }

    public function apply(array $preview, int $batchSize = 100): array
    {
        if (collect($preview['orphanSales'])->isNotEmpty()
            || collect($preview['incompleteTransfers'])->isNotEmpty()
            || collect($preview['duplicateBalanceSnapshots'])->isNotEmpty()) {
            throw new RuntimeException('تعذر التصحيح الآلي: يجب معالجة المبيعات اليتيمة وسجلات النقل الناقصة أو المكررة يدويًا أولًا.');
        }

        $totalsBefore = $this->financialTotals();
        $repairedRows = 0;
        $repairedAccountants = 0;

        collect($preview['mismatchedAccountants'])
            ->chunk(max(1, $batchSize))
            ->each(function (Collection $chunk) use (&$repairedAccountants) {
                DB::transaction(function () use ($chunk, &$repairedAccountants) {
                    foreach ($chunk as $issue) {
                        $employee = Employee::withTrashed()->with('store')->find($issue->employee_id);
                        if (! $employee || ! $employee->store) {
                            continue;
                        }

                        $accountant = $this->accountantLifecycle->suspendAfterTransfer($employee);
                        if ($accountant) {
                            $repairedAccountants++;
                        }
                    }
                });
            });

        collect($preview['suspectedMovedHistoricalRows'])
            ->chunk(max(1, $batchSize))
            ->each(function (Collection $chunk) use (&$repairedRows) {
                DB::transaction(function () use ($chunk, &$repairedRows) {
                    foreach ($chunk as $issue) {
                        $updated = DB::table($issue['table'])
                            ->where('id', $issue['row_id'])
                            ->where('store_id', $issue['recorded_store_id'])
                            ->update(['store_id' => $issue['expected_store_id']]);

                        if ($updated !== 1) {
                            continue;
                        }

                        $employee = Employee::withTrashed()->find($issue['employee_id']);
                        if ($employee) {
                            EmployeeLogService::add(
                                $employee,
                                'employee_transfer_data_repaired',
                                "تصحيح متجر سجل تاريخي في {$issue['table']} دون تغيير قيمته أو مالكه.",
                                null,
                                [
                                    'table' => $issue['table'],
                                    'row_id' => $issue['row_id'],
                                    'old_store_id' => $issue['recorded_store_id'],
                                    'new_store_id' => $issue['expected_store_id'],
                                    'operation_date' => $issue['recorded_date'],
                                    'unchanged_amount' => $issue['amount'],
                                    'basis' => $issue['basis'],
                                ],
                            );
                        }

                        $repairedRows++;
                    }
                });
            });

        $totalsAfter = $this->financialTotals();
        if ($totalsBefore !== $totalsAfter) {
            throw new RuntimeException('توقف التحقق: تغير إجمالي مالي أثناء التصحيح. راجع سجل التدقيق واسترجع النسخة الاحتياطية.');
        }

        return compact('repairedRows', 'repairedAccountants', 'totalsBefore', 'totalsAfter');
    }

    private function financialTotals(): array
    {
        $amountColumns = [
            'employee_withdrawals' => 'amount',
            'debts' => 'amount',
            'credit_sales' => 'amount',
            'employee_credit_collections' => 'amount',
            'employee_absences' => 'penalty_amount',
            'employee_logs' => 'amount',
            'employee_salary_reports' => 'final_salary',
        ];

        return collect($amountColumns)
            ->mapWithKeys(fn (string $column, string $table) => [
                $table => round((float) DB::table($table)->sum($column), 2),
            ])
            ->all();
    }
}
