<?php

namespace App\Http\Controllers\Employees;

use App\Models\Employee;
use App\Models\Accountant;
use Illuminate\Http\Request;
use App\Services\EmployeeLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * --------------------------------------------------------------------------
 * EmployeeActions
 * --------------------------------------------------------------------------
 * هذا الملف مسؤول عن:
 * - ترقية موظف إلى محاسب
 * - إعادة تفعيل محاسب سابق
 * - سحب صلاحية المحاسب
 * - فحص الإيميل قبل الترقية
 * --------------------------------------------------------------------------
 */
class EmployeeActions
{
    /**
     * ----------------------------------------------------------------------
     * ترقية موظف إلى محاسب
     * ----------------------------------------------------------------------
     * - إذا كان لديه حساب سابق → إعادة تفعيل
     * - إذا لا → إنشاء حساب جديد
     * - يتم احترام حد الخطة (allowed_accountants)
     * - تسجيل العملية في السجلات
     * ----------------------------------------------------------------------
     */
    public static function promote(Request $request, Employee $employee)
    {
        self::authorizeEmployeeAccess($employee);

        $activeAccountant = Accountant::where('employee_id', $employee->id)
            ->where('status', 'active')
            ->first();

        if ($activeAccountant) {
            return back()->with('info', 'هذا الموظف لديه حساب محاسب نشط بالفعل.');
        }

        $existing = Accountant::withTrashed()
            ->where('employee_id', $employee->id)
            ->latest('id')
            ->first();

        $user = $employee->store->user;

        if (!$user || !$user->plan) {
            return back()->with('error', 'لا توجد خطة اشتراك مفعّلة لهذا المستخدم.');
        }

        $limit = $user->plan->allowed_accountants;

        $currentCount = Accountant::where('user_id', $user->id)
            ->where('status', 'active')
            ->count();

        /**
         * --------------------------------------------------------------
         * 1) إعادة تفعيل محاسب سابق
         * --------------------------------------------------------------
         */
        if ($existing && ($existing->trashed() || $existing->status !== 'active')) {

            if ($currentCount >= $limit) {
                return back()->with('error', 'لقد وصلت إلى الحد المسموح به من المحاسبين حسب خطتك الحالية.');
            }

            $request->validate([
                'password' => 'nullable|min:6',
            ]);

            DB::transaction(function () use ($existing, $employee, $request) {
                Employee::whereKey($employee->id)->lockForUpdate()->firstOrFail();
                if (Accountant::where('employee_id', $employee->id)->where('status', 'active')->where('id', '!=', $existing->id)->exists()) {
                    throw ValidationException::withMessages(['accountant' => 'يوجد حساب محاسب فعال لهذا الموظف بالفعل.']);
                }

                Accountant::where('employee_id', $employee->id)
                    ->where('id', '!=', $existing->id)
                    ->update(['status' => 'suspended']);

                if ($existing->trashed()) {
                    $existing->restore();
                }

                if ($request->filled('password')) {
                    $existing->password = $request->password;
                }

                $existing->status = 'active';
                $existing->save();
            });

            // سجل العملية
            EmployeeLogService::add(
                $existing,
                'accountant_reactivated',
                "إعادة تفعيل حساب المحاسب للموظف {$employee->name}"
            );

            return back()->with('success', 'تم إعادة تفعيل حساب المحاسب بنجاح');
        }

        /**
         * --------------------------------------------------------------
         * 2) ترقية لأول مرة
         * --------------------------------------------------------------
         */
        if (!$existing) {

            if ($currentCount >= $limit) {
                return back()->with('error', 'لا يمكنك ترقية المزيد من المحاسبين.');
            }

            $request->validate([
                'email'    => 'required|email|unique:accountants,email',
                'password' => 'required|min:6',
            ]);

            $accountant = DB::transaction(function () use ($employee, $user, $request) {
                Employee::whereKey($employee->id)->lockForUpdate()->firstOrFail();
                if (Accountant::where('employee_id', $employee->id)->where('status', 'active')->exists()) {
                    throw ValidationException::withMessages(['accountant' => 'يوجد حساب محاسب فعال لهذا الموظف بالفعل.']);
                }

                Accountant::where('employee_id', $employee->id)->update(['status' => 'suspended']);

                return Accountant::create([
                    'employee_id' => $employee->id,
                    'user_id'     => $user->id,
                    'store_id'    => $employee->store_id,
                    'name'        => $employee->name,
                    'email'       => $request->email,
                    'phone'       => $employee->phone,
                    'password'    => $request->password,
                    'role'        => 'accountant',
                    'status'      => 'active',
                ]);
            });

            // سجل العملية
            EmployeeLogService::add(
                $accountant,
                'accountant_promoted',
                "تم ترقية الموظف {$employee->name} إلى محاسب"
            );

            return back()->with('success', 'تم ترقية الموظف إلى محاسب بنجاح');
        }

        /**
         * --------------------------------------------------------------
         * 3) لديه حساب نشط بالفعل
         * --------------------------------------------------------------
         */
        return back()->with('info', 'هذا الموظف لديه حساب محاسب نشط بالفعل.');
    }

    /**
     * ----------------------------------------------------------------------
     * فحص الإيميل قبل الترقية
     * ----------------------------------------------------------------------
     */
    public static function checkEmail(Request $request)
    {
        $email = $request->input('email');

        $exists = Accountant::withTrashed()
            ->where('email', $email)
            ->exists();

        return response()->json([
            'exists' => $exists,
        ]);
    }

    /**
     * ----------------------------------------------------------------------
     * سحب صلاحية المحاسب (تعليق الحساب)
     * ----------------------------------------------------------------------
     */
    public static function demote(Employee $employee)
    {
        self::authorizeEmployeeAccess($employee);

        $employee->load('activeAccountant');

        if (!$employee->activeAccountant) {
            return back()->with('error', 'هذا الموظف ليس لديه حساب محاسب فعال.');
        }

        $employee->activeAccountant->update([
            'status' => 'suspended',
        ]);

        // سجل العملية
        EmployeeLogService::add(
            $employee->activeAccountant,
            'accountant_suspended',
            "تم سحب صلاحية المحاسب من الموظف {$employee->name}"
        );

        return back()->with('success', 'تم سحب صلاحية المحاسب بنجاح');
    }

    private static function safeReturnTo(?string $returnTo): ?string
    {
        if (!$returnTo) {
            return null;
        }

        if (str_starts_with($returnTo, '/')) {
            return $returnTo;
        }

        $appHost = parse_url(url('/'), PHP_URL_HOST);
        $targetHost = parse_url($returnTo, PHP_URL_HOST);

        return $targetHost && $targetHost === $appHost ? $returnTo : null;
    }

    private static function authorizeEmployeeAccess(Employee $employee): void
    {
        if (auth('admin')->check()) {
            return;
        }

        $user = auth('web')->user();

        if (!$user || $user->role !== 'user') {
            abort(403);
        }

        if (!$user->stores()->where('id', $employee->store_id)->exists()) {
            abort(403, 'هذا الموظف لا ينتمي لمتاجرك');
        }
    }

}
