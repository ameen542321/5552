<?php

namespace App\Services\Employees;

use App\Http\Controllers\Employees\EmployeeService;
use App\Models\Absence;
use App\Models\CreditSale;
use App\Models\Debt;
use App\Models\Employee;
use App\Models\Store;
use App\Models\Withdrawal;
use App\Services\Accounting\FinancialSummaryService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class EmployeePayrollService
{
    public function __construct(private readonly EmployeeHistoricalStoreService $historicalStores)
    {
    }

    public function monthlyRowsForStore(int $storeId, string $month, $periodStart, $periodEnd): Collection
    {
        $employees = $this->historicalStores->employeesForStoreDuringPeriod($storeId, $periodStart, $periodEnd);
        $employeeIds = $employees->pluck('id');

        $withdrawals = $this->withdrawalsByEmployee($storeId, $employeeIds, $periodStart, $periodEnd);
        $absences = $this->absencesByEmployee($storeId, $employeeIds, $periodStart, $periodEnd);
        $debts = $this->debtsByEmployee($storeId, $employeeIds, $periodStart, $periodEnd);
        $creditRemaining = $this->creditRemainingByEmployee($storeId, $employeeIds, $periodStart, $periodEnd);

        return $employees->map(function (Employee $employee) use ($storeId, $month, $periodStart, $periodEnd, $withdrawals, $absences, $debts, $creditRemaining) {
            $withdrawalTotal = (float) ($withdrawals[$employee->id] ?? 0);
            $absenceDays = (int) ($absences[$employee->id] ?? 0);
            $debtTotal = (float) ($debts[$employee->id] ?? 0) + (float) ($creditRemaining[$employee->id] ?? 0);
            $baseSalary = $this->salaryAtPeriodEnd($employee, $periodEnd);
            $assignmentDays = $this->historicalStores->assignmentDaysForEmployeeStore($employee, (int) $storeId, $periodStart, $periodEnd);
            $workingDates = $this->workingDatesForStore($employee, (int) $storeId, $periodStart, $periodEnd);
            $workedDays = min($assignmentDays, $workingDates->count());
            $suspendedDays = max(0, $assignmentDays - $workedDays);
            $payableSalary = $this->salaryAmountForDates($employee, $workingDates, $periodStart);
            $dailySalary = $baseSalary / max((int) $periodStart->daysInMonth, 1);
            $absencePenalty = $dailySalary * $absenceDays;
            $netSalary = max(0, $payableSalary - $withdrawalTotal - $absencePenalty - $debtTotal);

            return [
                'id' => $employee->id,
                'month' => $month,
                'name' => $employee->name,
                'base_salary' => $baseSalary,
                'salary' => $payableSalary,
                'worked_days' => $workedDays,
                'suspended_days' => $suspendedDays,
                'withdrawals' => $withdrawalTotal,
                'absences_count' => $absenceDays,
                'absence_penalty' => $absencePenalty,
                'debts' => $debtTotal,
                'net_salary' => $netSalary,
                'remaining' => $netSalary,
                'status' => $employee->trashed() ? 'محذوف' : ($employee->status ?? 'نشط'),
            ];
        })->filter(fn (array $row) => $row['worked_days'] > 0 || $row['withdrawals'] > 0 || $row['absences_count'] > 0 || $row['debts'] > 0)->values();
    }

    public function proratedSalariesTotalForStore(int $storeId, $periodStart, $periodEnd): float
    {
        return $this->historicalStores
            ->employeesForStoreDuringPeriod($storeId, $periodStart, $periodEnd)
            ->sum(function (Employee $employee) use ($storeId, $periodStart, $periodEnd) {
                $workingDates = $this->workingDatesForStore($employee, (int) $storeId, $periodStart, $periodEnd);

                return $this->salaryAmountForDates($employee, $workingDates, $periodStart);
            });
    }

    public function salaryRowsForStores(Collection $storeIds, $periodStart, $periodEnd): Collection
    {
        $storeNames = Store::whereIn('id', $storeIds)->pluck('name', 'id');

        return $storeIds->flatMap(function ($storeId) use ($periodStart, $periodEnd, $storeNames) {
            $employees = $this->historicalStores->employeesForStoreDuringPeriod((int) $storeId, $periodStart, $periodEnd);
            $employeeIds = $employees->pluck('id');
            $withdrawals = $this->withdrawalsByEmployee((int) $storeId, $employeeIds, $periodStart, $periodEnd);
            $absenceDaysByEmployee = $this->absencesByEmployee((int) $storeId, $employeeIds, $periodStart, $periodEnd);

            return $employees->map(function (Employee $employee) use ($storeId, $periodStart, $periodEnd, $storeNames, $withdrawals, $absenceDaysByEmployee) {
                $baseSalary = $this->salaryAtPeriodEnd($employee, $periodEnd);
                $assignmentDays = $this->historicalStores->assignmentDaysForEmployeeStore($employee, (int) $storeId, $periodStart, $periodEnd);
                $workingDates = $this->workingDatesForStore($employee, (int) $storeId, $periodStart, $periodEnd);
                $workedDays = min($assignmentDays, $workingDates->count());
                $suspendedDays = max(0, $assignmentDays - $workedDays);
                $payableSalary = $this->salaryAmountForDates($employee, $workingDates, $periodStart);
                $withdrawalsTotal = (float) ($withdrawals[$employee->id] ?? 0);
                $absenceDays = (int) ($absenceDaysByEmployee[$employee->id] ?? 0);
                $absenceDeduction = $absenceDays * ($baseSalary / max(1, $periodStart->daysInMonth));

                return (object) [
                    'id' => $employee->id,
                    'store_id' => (int) $storeId,
                    'name' => $employee->name,
                    'store_name' => $storeNames[$storeId] ?? null,
                    'base_salary' => $baseSalary,
                    'salary' => $payableSalary,
                    'worked_days' => $workedDays,
                    'suspended_days' => $suspendedDays,
                    'withdrawals_total' => $withdrawalsTotal,
                    'absence_days' => $absenceDays,
                    'absence_deduction' => $absenceDeduction,
                ];
            })->filter(fn ($row) => $row->worked_days > 0 || $row->withdrawals_total > 0 || $row->absence_days > 0);
        })->values();
    }


    /**
     * يسترجع راتب الموظف كما كان في نهاية فترة التقرير.
     * عند تعديل راتب شهر 7 مثلًا لا يجب أن تتغير تقارير شهر 6، لذلك نرجع للخلف عبر سجلات salary_update اللاحقة للفترة.
     */
    public function salaryAtPeriodEnd(Employee $employee, $periodEnd): float
    {
        $salary = (float) $employee->salary;

        $periodEndDate = Carbon::parse($periodEnd)->copy()->endOfDay();
        $futureSalaryUpdates = \App\Models\EmployeeLog::query()
            ->where('person_type', Employee::class)
            ->where('person_id', $employee->id)
            ->where('action_name', 'salary_update')
            ->orderByDesc('created_at')
            ->get(['description', 'meta', 'created_at'])
            ->filter(function ($log) use ($periodEndDate) {
                $effectiveDate = data_get($log->meta, 'effective_date');
                $effectiveAt = $effectiveDate
                    ? Carbon::parse($effectiveDate)->startOfDay()
                    : Carbon::parse($log->created_at);

                return $effectiveAt->gt($periodEndDate);
            });

        foreach ($futureSalaryUpdates as $log) {
            $oldSalary = data_get($log->meta, 'old_salary');

            if ($oldSalary === null && preg_match('/من\s+([0-9]+(?:\.[0-9]+)?)/u', (string) $log->description, $matches)) {
                $oldSalary = $matches[1];
            }

            if ($oldSalary !== null) {
                $salary = (float) $oldSalary;
            }
        }

        return $salary;
    }

    /**
     * يحسب الراتب المستحق يوميًا داخل فترة التقرير حتى لو تغير الراتب في منتصف الشهر.
     * مثال: إذا تغير الراتب يوم 10، تُحسب أيام 1-9 بالراتب القديم وباقي الشهر بالراتب الجديد دون تعديل أي بيانات محفوظة.
     */
    public function salaryInfoWithSalaryChanges(Employee $employee, $periodStart, $periodEnd): array
    {
        return $this->salaryInfoForAssignment($employee, null, $periodStart, $periodEnd);
    }

    public function salaryInfoForStoreWithSalaryChanges(Employee $employee, int $storeId, $periodStart, $periodEnd): array
    {
        return $this->salaryInfoForAssignment($employee, $storeId, $periodStart, $periodEnd);
    }

    private function salaryInfoForAssignment(Employee $employee, ?int $storeId, $periodStart, $periodEnd): array
    {
        $baseSalary = $this->salaryAtPeriodEnd($employee, $periodEnd);
        $salaryInfo = $this->salaryInfoForPeriod($employee, $baseSalary, $periodStart, $periodEnd);
        $assignmentDays = $storeId
            ? $this->historicalStores->assignmentDaysForEmployeeStore($employee, $storeId, $periodStart, $periodEnd)
            : (int) ($salaryInfo['total_days'] ?? 0);
        $workingDates = $storeId
            ? $this->workingDatesForStore($employee, $storeId, $periodStart, $periodEnd)
            : $this->workingDatesForPeriod($employee, $periodStart, $periodEnd);
        $workedDays = min($assignmentDays, $workingDates->count());

        return array_merge($salaryInfo, [
            'base_salary' => $baseSalary,
            'payable_salary' => $this->salaryAmountForDates($employee, $workingDates, $periodStart),
            'worked_days' => $workedDays,
            'suspended_days' => max(0, $assignmentDays - $workedDays),
            'total_days' => $assignmentDays,
        ]);
    }

    private function workingDatesForStore(Employee $employee, int $storeId, $periodStart, $periodEnd): Collection
    {
        $workingDates = $this->workingDatesForPeriod($employee, $periodStart, $periodEnd);
        $segments = $this->historicalStores->assignmentSegmentsForEmployee($employee, $periodStart, $periodEnd)
            ->filter(fn (array $segment) => (int) $segment['store_id'] === $storeId);

        return $workingDates->filter(function (Carbon $date) use ($segments) {
            return $segments->contains(function (array $segment) use ($date) {
                return $date->betweenIncluded(
                    Carbon::parse($segment['start'])->startOfDay(),
                    Carbon::parse($segment['end'])->startOfDay()
                );
            });
        })->values();
    }

    private function workingDatesForPeriod(Employee $employee, $periodStart, $periodEnd): Collection
    {
        $start = Carbon::parse($periodStart)->copy()->startOfDay();
        $end = Carbon::parse($periodEnd)->copy()->startOfDay();
        $suspendedDates = collect();
        $events = \App\Models\EmployeeLog::where('person_type', Employee::class)
            ->where('person_id', $employee->id)
            ->whereIn('action_name', ['employee_suspended', 'employee_activated'])
            ->where('created_at', '<=', $end->copy()->endOfDay())
            ->orderBy('created_at')
            ->get(['action_name', 'created_at']);
        $lastBeforePeriod = $events->filter(fn ($event) => $event->created_at->lt($start))->last();
        $suspendedFrom = $lastBeforePeriod?->action_name === 'employee_suspended' ? $start->copy() : null;

        if (! $lastBeforePeriod && $events->isEmpty() && $employee->status === 'suspended') {
            $hasFutureSuspensionLog = \App\Models\EmployeeLog::where('person_type', Employee::class)
                ->where('person_id', $employee->id)
                ->where('action_name', 'employee_suspended')
                ->where('created_at', '>', $end->copy()->endOfDay())
                ->exists();

            if (! $hasFutureSuspensionLog) {
                $suspendedFrom = $start->copy();
            }
        }

        foreach ($events->filter(fn ($event) => $event->created_at->betweenIncluded($start, $end->copy()->endOfDay())) as $event) {
            if ($event->action_name === 'employee_suspended' && ! $suspendedFrom) {
                $suspendedFrom = $event->created_at->copy()->startOfDay();
                continue;
            }

            if ($event->action_name === 'employee_activated' && $suspendedFrom) {
                $suspendedDates = $suspendedDates->merge($this->datesBetween($suspendedFrom, $event->created_at->copy()->startOfDay()->subDay()));
                $suspendedFrom = null;
            }
        }

        if ($suspendedFrom) {
            $suspendedDates = $suspendedDates->merge($this->datesBetween($suspendedFrom, $end));
        }

        return $this->datesBetween($start, $end)
            ->reject(fn (Carbon $date) => $suspendedDates->contains(fn (Carbon $suspendedDate) => $suspendedDate->isSameDay($date)))
            ->values();
    }

    private function datesBetween($from, $to): Collection
    {
        $cursor = Carbon::parse($from)->copy()->startOfDay();
        $end = Carbon::parse($to)->copy()->startOfDay();
        $dates = collect();

        while ($cursor->lte($end)) {
            $dates->push($cursor->copy());
            $cursor->addDay();
        }

        return $dates;
    }

    private function salaryAmountForDates(Employee $employee, Collection $dates, $periodStart): float
    {
        $daysInMonth = max((int) Carbon::parse($periodStart)->daysInMonth, 1);

        return round($dates->sum(fn (Carbon $date) => $this->salaryAtDate($employee, $date) / $daysInMonth), 2);
    }

    private function salaryAtDate(Employee $employee, Carbon $date): float
    {
        return $this->salaryAtPeriodEnd($employee, $date->copy()->endOfDay());
    }


    private function salaryInfoForPeriod(Employee $employee, float $baseSalary, $periodStart, $periodEnd): array
    {
        $currentSalary = $employee->salary;
        $employee->setAttribute('salary', $baseSalary);
        $salaryInfo = EmployeeService::calculateProratedSalaryForEmployee($employee, $periodStart, $periodEnd);
        $employee->setAttribute('salary', $currentSalary);

        return $salaryInfo;
    }

    private function withdrawalsByEmployee(int $storeId, Collection $employeeIds, $periodStart, $periodEnd): Collection
    {
        if ($employeeIds->isEmpty()) {
            return collect();
        }

        $withdrawalsQuery = Withdrawal::where('store_id', $storeId)
            ->where('person_type', Employee::class)
            ->whereIn('person_id', $employeeIds);

        app(FinancialSummaryService::class)->applyAccountingPeriodToTable(
            $withdrawalsQuery,
            'employee_withdrawals',
            $periodStart,
            $periodEnd
        );

        return $withdrawalsQuery
            ->selectRaw('person_id, COALESCE(SUM(amount), 0) as total')
            ->groupBy('person_id')
            ->pluck('total', 'person_id');
    }

    private function absencesByEmployee(int $storeId, Collection $employeeIds, $periodStart, $periodEnd): Collection
    {
        if ($employeeIds->isEmpty()) {
            return collect();
        }

        return Absence::where('store_id', $storeId)
            ->where('person_type', Employee::class)
            ->whereIn('person_id', $employeeIds)
            ->betweenOperationDates($periodStart, $periodEnd)
            ->selectRaw('person_id, COUNT(*) as count_total')
            ->groupBy('person_id')
            ->pluck('count_total', 'person_id');
    }

    private function debtsByEmployee(int $storeId, Collection $employeeIds, $periodStart, $periodEnd): Collection
    {
        if ($employeeIds->isEmpty()) {
            return collect();
        }

        return Debt::where('store_id', $storeId)
            ->where('person_type', Employee::class)
            ->whereIn('person_id', $employeeIds)
            ->betweenOperationDates($periodStart, $periodEnd)
            ->selectRaw('person_id, COALESCE(SUM(amount), 0) as total')
            ->groupBy('person_id')
            ->pluck('total', 'person_id');
    }

    private function creditRemainingByEmployee(int $storeId, Collection $employeeIds, $periodStart, $periodEnd): Collection
    {
        if ($employeeIds->isEmpty()) {
            return collect();
        }

        return CreditSale::where('store_id', $storeId)
            ->where('person_type', Employee::class)
            ->whereIn('person_id', $employeeIds)
            ->betweenOperationDates($periodStart, $periodEnd)
            ->selectRaw('person_id, COALESCE(SUM(remaining_amount), 0) as total')
            ->groupBy('person_id')
            ->pluck('total', 'person_id');
    }
}
