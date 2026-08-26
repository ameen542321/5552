<?php

namespace App\Http\Controllers\Employees;

use App\Models\Store;
use App\Models\Employee;
use App\Models\Accountant;
use App\Services\EmployeeLogService;
use App\Models\ArchivedItem;
use App\Services\AdministrativeArchiveService;
use App\Services\SupportSessionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * --------------------------------------------------------------------------
 * EmployeeTrash
 * --------------------------------------------------------------------------
 * مسؤول عن:
 * - حذف الموظف (Soft Delete)
 * - عرض سلة المحذوفات
 * - استرجاع الموظف
 * - الحذف النهائي (Force Delete)
 * --------------------------------------------------------------------------
 */
class EmployeeTrash
{
    /**
     * ----------------------------------------------------------------------
     * حذف موظف (Soft Delete)
     * ----------------------------------------------------------------------
     */
    public static function delete(Employee $employee, $request)
{
    // منع المحاسب
    if (auth('accountant')->check()) {
        abort(403);
    }

    $user = auth()->user();

    if ($user->role === 'user') {
        $storeIds = $user->stores->pluck('id')->toArray();

        if (!in_array($employee->store_id, $storeIds)) {
            abort(403);
        }
    }

    // عند إيقاف/حذف الموظف يتم إيقاف حساب المحاسب المرتبط فقط، حتى لا يبقى تسجيل الدخول فعالاً لموظف غير نشط.
    Accountant::withTrashed()
        ->where('employee_id', $employee->id)
        ->where('status', 'active')
        ->update(['status' => 'suspended']);

    // تنفيذ الحذف
    $employee->delete();

    // تسجيل العملية
    EmployeeLogService::add(
        $employee,
        'employee_deleted',
        "تم حذف الموظف {$employee->name}"
    );

    // 🔥 هنا return_to الحقيقي
    $returnTo = EmployeeService::safeReturnTo($request->query('return_to'));
    if ($returnTo) {
        return redirect($returnTo)
            ->with('success', 'تم حذف الموظف');
    }

    return redirect()
        ->route('user.employees.index')
        ->with('success', 'تم حذف الموظف');
}



    /**
     * ----------------------------------------------------------------------
     * عرض سلة المحذوفات
     * ----------------------------------------------------------------------
     */
    public static function list()
    {
        $user = auth()->user();

        // منع المحاسب
        if ($user->role === 'accountant') {
            abort(403);
        }

        // المدير يرى كل المتاجر – المستخدم يرى متاجره فقط
        $storeIds = $user->role === 'admin'
            ? Store::withTrashed()->pluck('id')->toArray()
            : Store::withTrashed()->where('user_id', $user->id)->pluck('id')->toArray();

        if (request('store_id') && in_array((int) request('store_id'), array_map('intval', $storeIds), true)) {
            $storeIds = [(int) request('store_id')];
        }

        $query = Employee::onlyTrashed()->whereIn('store_id', $storeIds);
        if (! app(SupportSessionService::class)->active()) {
            $query->whereNotIn('id', app(AdministrativeArchiveService::class)->archivedIds(Employee::class));
        }
        $employees = $query->with(['store' => fn ($query) => $query->withTrashed(), 'archivedItem'])->paginate(20);

        return view('employees.trash', compact('employees'));
    }

    /**
     * ----------------------------------------------------------------------
     * استرجاع موظف محذوف
     * ----------------------------------------------------------------------
     */
    public static function restore($id)
    {
        $user = auth()->user();

        $storeIds = $user->role === 'admin'
            ? Store::withTrashed()->pluck('id')
            : Store::withTrashed()->where('user_id', $user->id)->pluck('id');

        $employee = Employee::onlyTrashed()
            ->whereIn('store_id', $storeIds)
            ->findOrFail($id);

        $archive = app(AdministrativeArchiveService::class)->activeFor($employee, $employee->id);
        if ($archive) {
            abort_unless(app(SupportSessionService::class)->active(), 404);
            DB::transaction(function () use ($employee, $archive) {
                Employee::withTrashed()->lockForUpdate()->findOrFail($employee->id)->restore();
                ArchivedItem::lockForUpdate()->findOrFail($archive->id)->update([
                    'status' => 'restored', 'restored_at' => now(),
                    'restored_by' => app(SupportSessionService::class)->active()?->admin_id,
                    'admin_message' => 'تمت استعادة الموظف بواسطة الدعم التقني.',
                ]);
            });
        } else {
            $employee->restore();
        }

        // تسجيل العملية
        EmployeeLogService::add(
            $employee,
            'employee_restored',
            "تم استعادة الموظف {$employee->name}"
        );

        return back()->with('success', 'تم استرجاع الموظف');
    }

    /**
     * ----------------------------------------------------------------------
     * حذف نهائي (Force Delete)
     * ----------------------------------------------------------------------
     */
    public static function forceDelete($id)
    {
        $user = auth()->user();

        // منع المحاسب
        if ($user->role === 'accountant') {
            abort(403);
        }

        // السماح فقط للمدير والمستخدم
        if (!in_array($user->role, ['admin', 'user'])) {
            abort(403);
        }

        $storeIds = $user->role === 'admin'
            ? Store::withTrashed()->pluck('id')->toArray()
            : Store::withTrashed()->where('user_id', $user->id)->pluck('id')->toArray();

        $employee = Employee::onlyTrashed()
            ->whereIn('store_id', $storeIds)
            ->where('id', $id)
            ->firstOrFail();

        $archiveService = app(AdministrativeArchiveService::class);
        $archive = $archiveService->activeFor($employee, $employee->id);
        $store = Store::withTrashed()->findOrFail($employee->store_id);
        if (! app(SupportSessionService::class)->active() || ! $archive) {
            $archiveService->archive($employee, $store->user_id, $store->id, $employee->name);
            EmployeeLogService::add($employee, 'employee_archived', "تم حذف الموظف {$employee->name} نهائيًا من حساب المالك");
            return back()->with('success', 'تم حذف الموظف نهائيًا من حسابك. يمكن طلب استعادته من الدعم التقني خلال 30 يومًا.');
        }

        $blockers = self::permanentDeleteBlockers($employee->id);
        if ($blockers !== []) {
            $archive->update(['admin_message' => 'تعذر الحذف الفعلي لارتباط الموظف بـ ' . implode('، ', $blockers)]);
            return back()->with('error', $archive->admin_message);
        }

        // تسجيل العملية
        EmployeeLogService::add(
            $employee,
            'employee_force_deleted',
            "تم حذف الموظف {$employee->name} نهائيًا"
        );

        DB::transaction(function () use ($employee, $archive) {
            Employee::withTrashed()->lockForUpdate()->findOrFail($employee->id)->forceDelete();
            ArchivedItem::lockForUpdate()->findOrFail($archive->id)->update([
                'status' => 'purged', 'admin_message' => 'حُذف الموظف غير المرتبط فعلياً بواسطة الدعم التقني.',
            ]);
        });

        return redirect()
            ->route('user.employees.trash')
            ->with('success', 'تم حذف الموظف نهائيًا');
    }

    private static function permanentDeleteBlockers(int $employeeId): array
    {
        $checks = [
            'accountants' => ['حساب محاسب', ['employee_id']],
            'employee_withdrawals' => ['سحوبات', ['employee_id', 'person_id']],
            'withdrawals' => ['سحوبات', ['employee_id', 'person_id']],
            'employee_absences' => ['غيابات', ['employee_id', 'person_id']],
            'absences' => ['غيابات', ['employee_id', 'person_id']],
            'employee_debts' => ['مديونيات', ['employee_id', 'person_id']],
            'debts' => ['مديونيات', ['employee_id', 'person_id']],
            'employee_credit_sales' => ['مبيعات آجلة', ['employee_id', 'person_id']],
            'credit_sales' => ['مبيعات آجلة', ['employee_id', 'person_id']],
            'employee_salary_reports' => ['تقارير رواتب', ['employee_id', 'person_id']],
            'salary_reports' => ['تقارير رواتب', ['employee_id', 'person_id']],
            'sales' => ['عمليات بيع', ['employee_id']],
        ];

        $labels = [];
        foreach ($checks as $table => [$label, $columns]) {
            if (! Schema::hasTable($table)) continue;
            $existing = array_values(array_filter($columns, fn ($column) => Schema::hasColumn($table, $column)));
            if ($existing === []) continue;
            $exists = DB::table($table)->where(function ($query) use ($existing, $employeeId) {
                foreach ($existing as $column) $query->orWhere($column, $employeeId);
            })->exists();
            if ($exists) $labels[] = $label;
        }

        return array_values(array_unique($labels));
    }
}
