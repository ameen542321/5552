<?php

namespace App\Services\Employees;

use App\Models\Accountant;
use App\Models\Employee;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class EmployeeAccountantLifecycleService
{
    public function suspendAfterTransfer(Employee $employee): ?Accountant
    {
        $accountant = $employee->accountant()->withTrashed()->latest('id')->first();

        if (! $accountant) {
            return null;
        }

        $accountant->update([
            'user_id' => $employee->store->user_id,
            'store_id' => $employee->store_id,
            'status' => 'suspended',
            'suspension_reason' => 'تم إيقاف الحساب مؤقتًا بعد نقل الموظف إلى متجر آخر.',
        ]);

        if (Schema::hasTable('device_tokens')) {
            DB::table('device_tokens')->where('accountant_id', $accountant->id)->delete();
        }

        return $accountant;
    }

    public function activateForEmployee(Accountant $accountant, Employee $employee): void
    {
        $employee->loadMissing('store.user.plan');

        if ($employee->status !== 'active') {
            throw ValidationException::withMessages([
                'accountant' => 'لا يمكن تفعيل حساب المحاسب لأن الموظف المرتبط به غير فعال.',
            ]);
        }

        if (! $employee->store || $employee->store->status !== 'active') {
            throw ValidationException::withMessages([
                'accountant' => 'لا يمكن تفعيل حساب المحاسب لأن متجر الموظف غير فعال.',
            ]);
        }

        if ((int) $accountant->store_id !== (int) $employee->store_id
            || (int) $accountant->user_id !== (int) $employee->store->user_id) {
            throw ValidationException::withMessages([
                'accountant' => 'لا يمكن تفعيل الحساب قبل مطابقة متجر ومالك المحاسب مع الموظف المرتبط به.',
            ]);
        }

        $limit = $employee->store->user?->plan?->allowed_accountants;
        if ($limit === null) {
            throw ValidationException::withMessages([
                'accountant' => 'لا توجد خطة اشتراك مفعّلة لمالك المتجر.',
            ]);
        }

        $activeCount = Accountant::query()
            ->where('user_id', $accountant->user_id)
            ->where('status', 'active')
            ->where('id', '!=', $accountant->id)
            ->count();

        if ($activeCount >= $limit) {
            throw ValidationException::withMessages([
                'accountant' => 'تم الوصول إلى الحد المسموح به من المحاسبين في الخطة الحالية.',
            ]);
        }

        DB::transaction(function () use ($accountant, $employee) {
            Employee::whereKey($employee->id)->lockForUpdate()->firstOrFail();

            Accountant::query()
                ->where('employee_id', $employee->id)
                ->where('id', '!=', $accountant->id)
                ->update(['status' => 'suspended']);

            if ($accountant->trashed()) {
                $accountant->restore();
            }

            $accountant->update([
                'status' => 'active',
                'suspension_reason' => null,
            ]);
        });
    }
}
