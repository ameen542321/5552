<?php

namespace App\Http\Controllers\Employees;

use App\Models\Store;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Services\EmployeeLogService;
use App\Services\Employees\EmployeeHistoricalStoreService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;

class EmployeeService
{
    public static function index(?Request $request = null)
    {
        $user = auth()->user();

        $selectedStore = null;
        $routeStore = $request?->route('store');

        if ($routeStore instanceof Store) {
            $selectedStore = $routeStore;
        } elseif ($request?->filled('store')) {
            $selectedStore = Store::findOrFail((int) $request->query('store'));
        }

        if ($selectedStore) {
            if ($user->role !== 'admin' && (int) $selectedStore->user_id !== (int) $user->id) {
                abort(403);
            }

            $storeIds = collect([(int) $selectedStore->id]);
        } elseif ($user->role === 'admin') {
            $storeIds = Store::pluck('id');
        } elseif ($user->role === 'user') {
            $storeIds = $user->stores->pluck('id');
        } elseif (auth('accountant')->check()) {
            $storeIds = collect([auth('accountant')->user()->store_id]);
        } else {
            abort(403);
        }

        // في صفحة متجر محدد نعرض موظفي الشهر الحالي حتى لو نُقلوا خلال الشهر،
        // أما صفحة كل الموظفين فتبقى على الموظفين الحاليين لدى المالك.
        $employees = $selectedStore
            ? app(EmployeeHistoricalStoreService::class)
                ->employeesForStoreDuringPeriod((int) $selectedStore->id, now()->startOfMonth(), now()->endOfMonth())
                ->values()
            : Employee::whereIn('store_id', $storeIds)
                ->get();

        $employees->load(['store', 'accountant', 'activeAccountant']);

        self::attachCurrentMonthSalaryInfo($employees, $selectedStore?->id);

        return view('employees.index', compact('employees', 'selectedStore'));
    }

    public function create(array $data)
    {
        // الحفظ المباشر للبيانات المجهزة من الكنترولر
        return Employee::create($data);
    }

    // بقية الدوال (edit, update, store القديمة) تبقى كما هي دون تغيير في منطقها الأصلي
    public static function edit(Employee $employee)
    {
        if (auth('accountant')->check()) { abort(403); }

        $user = auth('admin')->user() ?: auth('web')->user();

        if (!$user) {
            abort(403);
        }

        if ($user->role !== 'admin' && !$user->stores()->where('id', $employee->store_id)->exists()) {
            abort(403);
        }

        $stores = ($user->role === 'admin') ? Store::all() : $user->stores;

        return view('employees.edit', compact('employee', 'stores'));
    }

    public static function update(Request $request, Employee $employee)
    {
        if (auth('accountant')->check()) { abort(403); }

        $user = auth('admin')->user() ?: auth('web')->user();

        if (!$user) {
            abort(403);
        }

        if ($user->role !== 'admin' && !$user->stores()->where('id', $employee->store_id)->exists()) {
            abort(403);
        }

        $storeRule = Rule::exists('stores', 'id');

        if ($user->role !== 'admin') {
            $storeRule = $storeRule->where(fn ($query) => $query->where('user_id', $user->id));
        }

        $accountant = $employee->accountant;

        $request->validate([
            'store_id' => ['required', $storeRule],
            'name'     => 'required|string|max:255',
            'salary'   => 'required|numeric|min:0',
            'accountant_email' => [
                Rule::requiredIf((bool) $accountant),
                'nullable',
                'email',
                $accountant
                    ? Rule::unique('accountants', 'email')->ignore($accountant->id)
                    : Rule::unique('accountants', 'email'),
            ],
            'accountant_password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'salary_effective_mode' => 'nullable|in:today,custom',
            'salary_effective_date' => [
                'required_if:salary_effective_mode,custom',
                'nullable',
                'date',
                function ($attribute, $value, $fail) {
                    if (!$value) {
                        return;
                    }

                    $date = Carbon::parse($value);
                    if (!$date->isSameMonth(now())) {
                        $fail('يمكن اختيار تاريخ سريان الراتب من الشهر الحالي فقط.');
                    }
                },
            ],
            'transfer_effective_date' => [
                'nullable',
                'date',
                function ($attribute, $value, $fail) use ($request, $employee) {
                    if ((int) $request->input('store_id') === (int) $employee->store_id) {
                        return;
                    }

                    if (!$value) {
                        $fail('يجب تحديد تاريخ نقل الموظف عند تغيير المتجر.');
                        return;
                    }

                    $date = Carbon::parse($value);
                    if (!$date->isSameMonth(now())) {
                        $fail('يمكن اختيار تاريخ النقل من الشهر الحالي فقط.');
                    }

                    if ($date->isFuture()) {
                        $fail('لا يمكن اختيار تاريخ نقل مستقبلي.');
                    }
                },
            ],
        ]);

        $oldStoreId = (int) $employee->store_id;
        $requestedStoreId = (int) $request->input('store_id');
        // لا نسجل نقلًا عند تعديل الاسم أو الهاتف أو الراتب؛ النقل مرتبط بتغير معرف المتجر فقط.
        $storeChanged = $oldStoreId !== $requestedStoreId;
        $oldSalary  = $employee->salary;

        $salaryEffectiveDate = $request->input('salary_effective_mode') === 'custom' && $request->filled('salary_effective_date')
            ? Carbon::parse($request->input('salary_effective_date'))->toDateString()
            : now()->toDateString();

        $transferEffectiveDate = $storeChanged
            ? Carbon::parse($request->input('transfer_effective_date'))->startOfDay()
            : null;

        DB::transaction(function () use ($request, $employee, $accountant, $oldStoreId, $oldSalary, $salaryEffectiveDate, $transferEffectiveDate, $storeChanged) {
            $employee->update($request->only('store_id', 'name', 'phone', 'salary'));

            if ($accountant) {
                $accountantData = ['email' => $request->input('accountant_email')];
                if ($request->filled('accountant_password')) {
                    $accountantData['password'] = $request->input('accountant_password');
                }
                $accountant->update($accountantData);
            }

            EmployeeLogService::add($employee, 'employee_updated', "تم تعديل بيانات الموظف {$employee->name}");

            if ($storeChanged) {
                $oldStore = Store::find($oldStoreId);
                $newStore = $employee->store;

                if ($oldStore && $newStore) {
                    self::transferEmployeeFinancialRecordsToStore($employee, (int) $employee->store_id, true);
                    $transferLog = EmployeeLogService::add(
                        $employee,
                        'employee_transferred',
                        "تم نقل الموظف من متجر {$oldStore->name} إلى متجر {$newStore->name}. تم نقل المديونيات فقط، وبقيت سجلات السحب والغياب والرواتب في متجرها الأصلي لحفظ تقارير الفترات السابقة. إذا كان للموظف حساب محاسب فعال فقد تم إيقافه بعد النقل حتى تتم مراجعته.",
                        null,
                        [
                            'old_store_id' => $oldStore->id,
                            'old_store_name' => $oldStore->name,
                            'new_store_id' => $newStore->id,
                            'new_store_name' => $newStore->name,
                            'effective_date' => $transferEffectiveDate?->toDateString(),
                            'current_month_records_only' => false,
                            'financial_records_follow_operation_store' => true,
                            'active_accountant_suspended' => true,
                        ]
                    );

                    if ($transferLog && $transferEffectiveDate) {
                        $transferLog->forceFill(['created_at' => $transferEffectiveDate])->save();
                    }
                }
            }

            if ($oldSalary != $employee->salary) {
                EmployeeLogService::add(
                    $employee,
                    'salary_update',
                    "تعديل الراتب من {$oldSalary} إلى {$employee->salary} ريال",
                    null,
                    [
                        'old_salary' => (float) $oldSalary,
                        'new_salary' => (float) $employee->salary,
                        'effective_date' => $salaryEffectiveDate,
                    ]
                );
            }
        });

        $returnTo = self::safeReturnTo($request->input('return_to'));

        return redirect($returnTo ?? route('user.employees.index'))
            ->with('success', 'تم تحديث بيانات العامل بنجاح');
    }


    /**
     * إيقاف الموظف ماليًا ووظيفيًا مع إيقاف حساب المحاسب المرتبط فقط إن وجد.
     */
    public static function suspend(Employee $employee, Request $request)
    {
        self::authorizeEmployeeForStatusChange($employee);

        DB::transaction(function () use ($employee) {
            $employee->update(['status' => 'suspended']);

            $employee->accountant()
                ->withTrashed()
                ->where('status', 'active')
                ->update(['status' => 'suspended']);

            EmployeeLogService::add($employee, 'employee_suspended', "تم إيقاف الموظف {$employee->name}");
        });

        $returnTo = self::safeReturnTo($request->input('return_to') ?: $request->query('return_to'));

        return redirect($returnTo ?? route('user.employees.index'))
            ->with('success', 'تم إيقاف الموظف ماليًا ووظيفيًا، وتم إيقاف حساب المحاسب المرتبط إن وجد. لن يتم احتساب راتبه عن أيام الإيقاف.');
    }

    /**
     * تفعيل الموظف فقط دون إعادة تفعيل حساب المحاسب المرتبط.
     */
    public static function activate(Employee $employee, Request $request)
    {
        self::authorizeEmployeeForStatusChange($employee);

        $employee->update(['status' => 'active']);
        EmployeeLogService::add($employee, 'employee_activated', "تم تفعيل الموظف {$employee->name}");

        $returnTo = self::safeReturnTo($request->input('return_to') ?: $request->query('return_to'));

        return redirect($returnTo ?? route('user.employees.index'))
            ->with('success', 'تم تفعيل الموظف فقط. سيتم استئناف احتساب راتبه من تاريخ التفعيل، ولم يتم تفعيل حساب المحاسب المرتبط.');
    }

    private static function authorizeEmployeeForStatusChange(Employee $employee): void
    {
        if (auth('accountant')->check()) { abort(403); }

        $user = auth('admin')->user() ?: auth('web')->user();

        if (!$user) {
            abort(403);
        }

        if ($user->role !== 'admin' && !$user->stores()->where('id', $employee->store_id)->exists()) {
            abort(403);
        }
    }

    /**
     * نقل السجلات المالية واليومية التابعة للموظف إلى متجره الجديد دون نقل المبيعات التشغيلية القديمة.
     */
    public static function transferEmployeeFinancialRecordsToStore(Employee $employee, int $newStoreId, bool $suspendActiveAccountant = false): void
    {
        $accountantUpdate = ['store_id' => $newStoreId];

        if ($suspendActiveAccountant) {
            $accountantUpdate['status'] = 'suspended';
        }

        if ($suspendActiveAccountant) {
            $employee->accountant()->withTrashed()
                ->where('status', 'active')
                ->update(['status' => 'suspended']);
        } else {
            $employee->accountant()->withTrashed()->update($accountantUpdate);
        }

        // تنتقل ملكية سجل الموظف الكامل إلى المتجر الجديد حتى لا يفقد ديونه أو سحوباته عند حذف المتجر السابق.
        foreach ([
            'debts',
            'employee_withdrawals',
            'employee_absences',
            'credit_sales',
            'employee_credit_collections',
            'employee_salary_reports',
            'employee_logs',
        ] as $table) {
            self::moveEmployeeStoreRows($table, $employee->id, $newStoreId);
        }
    }

    /**
     * نقل السجلات الحديثة المبنية على person_id والسجلات القديمة المبنية على employee_id.
     */
    private static function moveEmployeeStoreRows(string $table, int $employeeId, int $newStoreId): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable($table)
            || ! \Illuminate\Support\Facades\Schema::hasColumn($table, 'store_id')) {
            return;
        }

        $query = DB::table($table);

        if (\Illuminate\Support\Facades\Schema::hasColumn($table, 'person_id')) {
            $query->where('person_id', $employeeId);

            if (\Illuminate\Support\Facades\Schema::hasColumn($table, 'person_type')) {
                $query->where('person_type', Employee::class);
            }
        } elseif (\Illuminate\Support\Facades\Schema::hasColumn($table, 'employee_id')) {
            $query->where('employee_id', $employeeId);
        } else {
            return;
        }

        $query->update(['store_id' => $newStoreId]);
    }


    /**
     * تحديث السجلات القديمة التي تحتوي employee_id فقط عند وجود العمود فعلياً.
     */
    private static function updateLegacyEmployeeStoreColumn(string $table, string $modelClass, int $employeeId, int $newStoreId, $start = null, $end = null): void
    {
        if (!\Illuminate\Support\Facades\Schema::hasColumn($table, 'employee_id')) {
            return;
        }

        $query = $modelClass::withTrashed()->where('employee_id', $employeeId);

        if ($start && $end) {
            $query->whereBetween('created_at', [$start, $end]);
        }

        $query->update(['store_id' => $newStoreId]);
    }

    private static function updateCurrentMonthRecords($query, int $newStoreId, $start, $end): void
    {
        $query->whereBetween('created_at', [$start, $end])
            ->update(['store_id' => $newStoreId]);
    }

    /**
     * إرفاق أثر الإيقاف على راتب الشهر الحالي لكل موظف لعرضه في الواجهات دون تغيير قاعدة البيانات.
     */
    public static function attachCurrentMonthSalaryInfo(Collection $employees, ?int $storeContextId = null): Collection
    {
        $start = now()->startOfMonth();
        $end = now()->endOfMonth();

        $payrollService = app(\App\Services\Employees\EmployeePayrollService::class);

        return $employees->each(function (Employee $employee) use ($start, $end, $payrollService, $storeContextId) {
            $employee->salary_info = $storeContextId
                ? $payrollService->salaryInfoForStoreWithSalaryChanges($employee, $storeContextId, $start, $end)
                : $payrollService->salaryInfoWithSalaryChanges($employee, $start, $end);
        });
    }

    /**
     * حساب الراتب المستحق حسب أيام العمل الفعلية وأيام الإيقاف المسجلة في employee_logs.
     */
    public static function calculateProratedSalaryForEmployee(Employee $employee, $start, $end): array
    {
        $periodStart = $start->copy()->startOfDay();
        $periodEnd = $end->copy()->endOfDay();
        $totalDays = (int) $periodStart->copy()->startOfDay()->diffInDays($periodEnd->copy()->startOfDay()) + 1;

        $events = \App\Models\EmployeeLog::where('person_type', Employee::class)
            ->where('person_id', $employee->id)
            ->whereIn('action_name', ['employee_suspended', 'employee_activated'])
            ->where('created_at', '<=', $periodEnd)
            ->orderBy('created_at')
            ->get(['action_name', 'created_at']);

        $lastBeforePeriod = $events
            ->filter(fn ($event) => $event->created_at->lt($periodStart))
            ->last();

        $suspendedFrom = null;

        if ($lastBeforePeriod?->action_name === 'employee_suspended') {
            $suspendedFrom = $periodStart->copy();
        } elseif (!$lastBeforePeriod && $events->isEmpty() && $employee->status === 'suspended') {
            $hasFutureSuspensionLog = \App\Models\EmployeeLog::where('person_type', Employee::class)
                ->where('person_id', $employee->id)
                ->where('action_name', 'employee_suspended')
                ->where('created_at', '>', $periodEnd)
                ->exists();

            if (! $hasFutureSuspensionLog) {
                $suspendedFrom = $periodStart->copy();
            }
        }

        $suspendedDays = 0;

        foreach ($events->filter(fn ($event) => $event->created_at->betweenIncluded($periodStart, $periodEnd)) as $event) {
            if ($event->action_name === 'employee_suspended' && !$suspendedFrom) {
                $suspendedFrom = $event->created_at->copy()->startOfDay();
                continue;
            }

            if ($event->action_name === 'employee_activated' && $suspendedFrom) {
                $suspendedUntil = $event->created_at->copy()->startOfDay()->subDay();
                $suspendedDays += self::countInclusiveDays($suspendedFrom, $suspendedUntil);
                $suspendedFrom = null;
            }
        }

        if ($suspendedFrom) {
            $suspendedDays += self::countInclusiveDays($suspendedFrom, $periodEnd);
        }

        $suspendedDays = min($suspendedDays, $totalDays);
        $workedDays = max(0, $totalDays - $suspendedDays);
        $payableSalary = $totalDays > 0
            ? round(((float) $employee->salary / $totalDays) * $workedDays, 2)
            : 0.0;

        return [
            'base_salary' => (float) $employee->salary,
            'payable_salary' => $payableSalary,
            'worked_days' => $workedDays,
            'suspended_days' => $suspendedDays,
            'total_days' => $totalDays,
        ];
    }

    private static function countInclusiveDays($from, $to): int
    {
        $start = $from->copy()->startOfDay();
        $end = $to->copy()->startOfDay();

        if ($end->lt($start)) {
            return 0;
        }

        return (int) $start->diffInDays($end) + 1;
    }

    public static function safeReturnTo(?string $returnTo): ?string
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
}
