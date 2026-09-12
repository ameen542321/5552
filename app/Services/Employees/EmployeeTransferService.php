<?php

namespace App\Services\Employees;

use App\Models\Debt;
use App\Models\Employee;
use App\Models\Store;
use App\Services\EmployeeLogService;
use Carbon\CarbonInterface;

class EmployeeTransferService
{
    public function __construct(
        private readonly EmployeeAccountantLifecycleService $accountantLifecycle,
    ) {}

    public function finalizeMovedEmployee(
        Employee $employee,
        Store $oldStore,
        CarbonInterface $effectiveDate,
        string $source = 'employee_profile',
    ): void {
        $employee->unsetRelation('store')->load('store');
        $newStore = $employee->store;

        if (! $newStore || (int) $newStore->id === (int) $oldStore->id) {
            return;
        }

        $transferredPersonalDebtBalance = (float) Debt::query()
            ->where('person_type', Employee::class)
            ->where('person_id', $employee->id)
            ->where('status', Debt::STATUS_PENDING)
            ->where('amount', '>', 0)
            ->where(function ($query) use ($effectiveDate) {
                $query->whereDate('date', '<', $effectiveDate->toDateString())
                    ->orWhere(function ($fallback) use ($effectiveDate) {
                        $fallback->whereNull('date')
                            ->where('created_at', '<', $effectiveDate->copy()->startOfDay());
                    });
            })
            ->sum('amount');

        $accountant = $this->accountantLifecycle->suspendAfterTransfer($employee);

        $transferLog = EmployeeLogService::add(
            $employee,
            'employee_transferred',
            "تم نقل الموظف من متجر {$oldStore->name} إلى متجر {$newStore->name}. بقيت جميع العمليات التاريخية في متجر حدوثها دون تغيير، ونُقل الرصيد الشخصي المفتوح فقط محاسبيًا. حساب المحاسب المرتبط، إن وجد، نُقل وأوقف حتى المراجعة.",
            null,
            [
                'old_store_id' => $oldStore->id,
                'old_store_name' => $oldStore->name,
                'new_store_id' => $newStore->id,
                'new_store_name' => $newStore->name,
                'effective_date' => $effectiveDate->toDateString(),
                'transfer_source' => $source,
                'historical_records_preserved' => true,
                'financial_records_follow_operation_store' => true,
                'active_accountant_suspended' => (bool) $accountant,
                'transferred_personal_debt_balance' => $transferredPersonalDebtBalance,
                'debt_balance_as_of' => $effectiveDate->copy()->subDay()->toDateString(),
            ],
        );

        $transferLog?->forceFill(['created_at' => $effectiveDate])->save();
    }
}
