<?php

namespace App\Services\Employees;

use App\Models\Absence;
use App\Models\CreditSale;
use App\Models\Debt;
use App\Models\Employee;
use App\Models\EmployeeLog;
use App\Models\Withdrawal;
use App\Services\Accounting\FinancialSummaryService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class EmployeeHistoricalStoreService
{
    /**
     * يرجع الموظفين الذين يجب أن يظهروا في تقرير متجر وفترة، حتى لو نُقلوا لاحقًا.
     */
    public function employeesForStoreDuringPeriod(int $storeId, $periodStart, $periodEnd): Collection
    {
        $historicalEmployeeIds = $this->employeeIdsForStorePeriod($storeId, $periodStart, $periodEnd);

        return Employee::withTrashed()
            ->where(function ($query) use ($storeId, $historicalEmployeeIds) {
                $query->where('store_id', $storeId)
                    ->orWhereIn('id', $historicalEmployeeIds);
            })
            ->where(function ($query) use ($periodStart, $periodEnd) {
                $query->whereNull('deleted_at')
                    ->orWhere('deleted_at', '>=', $periodStart);
            })
            ->orderBy('name')
            ->get(['id', 'store_id', 'name', 'salary', 'status', 'deleted_at']);
    }

    public function employeeIdsForStorePeriod(int $storeId, $periodStart, $periodEnd): Collection
    {
        $transferredDuringPeriodEmployeeIds = EmployeeLog::query()
            ->where('action_name', 'employee_transferred')
            ->where('person_type', Employee::class)
            ->where(function ($query) use ($storeId) {
                $query->where('meta->old_store_id', $storeId)
                    ->orWhere('meta->new_store_id', $storeId);
            })
            ->whereBetween('created_at', [$periodStart, $periodEnd])
            ->pluck('person_id');

        $transferredAfterPeriodEmployeeIds = EmployeeLog::query()
            ->where('action_name', 'employee_transferred')
            ->where('person_type', Employee::class)
            ->where('meta->old_store_id', $storeId)
            ->where('created_at', '>', $periodEnd)
            ->pluck('person_id');

        $withdrawalEmployeeIdsQuery = Withdrawal::where('store_id', $storeId)
            ->where('person_type', Employee::class);
        app(FinancialSummaryService::class)->applyAccountingPeriodToTable(
            $withdrawalEmployeeIdsQuery,
            'employee_withdrawals',
            $periodStart,
            $periodEnd
        );

        $withdrawalEmployeeIds = $withdrawalEmployeeIdsQuery->pluck('person_id');

        $absenceEmployeeIds = Absence::where('store_id', $storeId)
            ->where('person_type', Employee::class)
            ->betweenOperationDates($periodStart, $periodEnd)
            ->pluck('person_id');

        $debtEmployeeIds = Debt::where('store_id', $storeId)
            ->where('person_type', Employee::class)
            ->betweenOperationDates($periodStart, $periodEnd)
            ->pluck('person_id');

        $creditEmployeeIds = CreditSale::where('store_id', $storeId)
            ->where('person_type', Employee::class)
            ->betweenOperationDates($periodStart, $periodEnd)
            ->pluck('person_id');

        return $transferredDuringPeriodEmployeeIds
            ->merge($transferredAfterPeriodEmployeeIds)
            ->merge($withdrawalEmployeeIds)
            ->merge($absenceEmployeeIds)
            ->merge($debtEmployeeIds)
            ->merge($creditEmployeeIds)
            ->map(fn ($employeeId) => (int) $employeeId)
            ->filter()
            ->unique()
            ->values();
    }

    /**
     * يحسب عدد الأيام التي كان فيها الموظف تابعًا لمتجر محدد داخل فترة التقرير.
     * يعتمد على سجلات النقل ولا يستخدم المتجر الحالي فقط حتى تبقى تقارير الشهور السابقة ثابتة.
     */
    public function assignmentDaysForEmployeeStore(Employee $employee, int $storeId, $periodStart, $periodEnd): int
    {
        return $this->assignmentSegmentsForEmployee($employee, $periodStart, $periodEnd)
            ->filter(fn (array $segment) => (int) $segment['store_id'] === (int) $storeId)
            ->sum(fn (array $segment) => $this->inclusiveDays($segment['start'], $segment['end']));
    }

    /**
     * يرجع شرائح انتساب الموظف للمتاجر داخل الفترة من الأحدث للأقدم ثم يعيدها مرتبة زمنيًا.
     */
    public function assignmentSegmentsForEmployee(Employee $employee, $periodStart, $periodEnd): Collection
    {
        $start = Carbon::parse($periodStart)->copy()->startOfDay();
        $end = Carbon::parse($periodEnd)->copy()->endOfDay();
        $currentStoreId = $this->storeIdAtPeriodEnd($employee, $end);
        $cursorEnd = $end->copy();
        $segments = collect();

        $transfers = EmployeeLog::query()
            ->where('action_name', 'employee_transferred')
            ->where('person_type', Employee::class)
            ->where('person_id', $employee->id)
            ->whereBetween('created_at', [$start, $end])
            ->orderByDesc('created_at')
            ->get(['created_at', 'meta']);

        foreach ($transfers as $transfer) {
            $transferDay = $transfer->created_at->copy()->startOfDay();
            if ($transferDay->lte($cursorEnd)) {
                $segments->push([
                    'store_id' => (int) $currentStoreId,
                    'start' => $transferDay->copy(),
                    'end' => $cursorEnd->copy(),
                ]);
            }

            $currentStoreId = (int) data_get($transfer->meta, 'old_store_id', $currentStoreId);
            $cursorEnd = $transferDay->copy()->subDay()->endOfDay();
        }

        if ($cursorEnd->gte($start)) {
            $segments->push([
                'store_id' => (int) $currentStoreId,
                'start' => $start->copy(),
                'end' => $cursorEnd->copy(),
            ]);
        }

        return $segments->reverse()->values();
    }

    /**
     * يسترجع المتجر الفعلي عند نهاية الفترة، حتى لو تم نقل الموظف بعد ذلك.
     */
    private function storeIdAtPeriodEnd(Employee $employee, $periodEnd): int
    {
        $storeId = (int) $employee->store_id;

        $futureTransfers = EmployeeLog::query()
            ->where('action_name', 'employee_transferred')
            ->where('person_type', Employee::class)
            ->where('person_id', $employee->id)
            ->where('created_at', '>', $periodEnd)
            ->orderByDesc('created_at')
            ->get(['meta']);

        foreach ($futureTransfers as $transfer) {
            $newStoreId = (int) data_get($transfer->meta, 'new_store_id', 0);
            $oldStoreId = (int) data_get($transfer->meta, 'old_store_id', 0);

            if ($newStoreId > 0 && $oldStoreId > 0 && $storeId === $newStoreId) {
                $storeId = $oldStoreId;
            }
        }

        return $storeId;
    }

    private function inclusiveDays($from, $to): int
    {
        $start = Carbon::parse($from)->copy()->startOfDay();
        $end = Carbon::parse($to)->copy()->startOfDay();

        if ($end->lt($start)) {
            return 0;
        }

        return (int) $start->diffInDays($end) + 1;
    }
}
