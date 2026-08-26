<?php

namespace App\Http\Controllers;

use App\Models\Store;
use App\Traits\HasLogs;
use App\Models\Employee;
use App\Models\Accountant;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Services\PlanLimitService;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Carbon;
use App\Http\Controllers\Employees\EmployeeService;

class AccountantController extends Controller
{
    use HasLogs;

    /*
    |--------------------------------------------------------------------------
    | عرض قائمة المحاسبين
    |--------------------------------------------------------------------------
    */
  public function index(Request $request)
{
    /** @var \App\Models\User $user */
    $user = auth('web')->user();

    $selectedStore = null;
    $routeStore = $request->route('store');

    if ($routeStore instanceof Store) {
        $selectedStore = $routeStore;
    } elseif ($request->filled('store')) {
        $selectedStore = Store::findOrFail((int) $request->query('store'));
    }

    if ($selectedStore) {
        if ((int) $selectedStore->user_id !== (int) $user->id) {
            abort(403);
        }

        $storeIds = collect([(int) $selectedStore->id]);
    } else {
        // جميع المتاجر التابعة للمستخدم
        $storeIds = $user->stores()->pluck('id');
    }

    // جلب المحاسبين المرتبطين بالمتاجر المطلوبة وبشرط الحالة "فعالة" فقط
    $accountants = Accountant::with(['employee.store'])
        ->whereIn('store_id', $storeIds)
        ->where('status', 'active') // إضافة شرط الحالة الفعالة
        ->paginate(20);

    EmployeeService::attachCurrentMonthSalaryInfo($accountants->getCollection()->pluck('employee')->filter(), $selectedStore?->id);

    // جلب عدد المحاسبين المحذوفين لنفس المتاجر (يبقى كما هو بناءً على طلبك عدم الحذف)
    $trashedCount = Accountant::onlyTrashed()
        ->whereIn('store_id', $storeIds)
        ->count();

    return view('user.accountants.index', compact('accountants', 'trashedCount', 'selectedStore'));
}


    /*
    |--------------------------------------------------------------------------
    | صفحة إنشاء محاسب
    |--------------------------------------------------------------------------
    */
    public function create(Request $request)
{
    $user = auth()->user();

    // إذا جئت من صفحة المتجر
    if ($request->from === 'store' && $request->store) {

        $store = Store::where('id', $request->store)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->firstOrFail();

        // لا نحتاج قائمة المتاجر هنا
        $stores = collect();

        return view('user.accountants.create', [
            'store'  => $store,
            'stores' => $stores
        ]);
    }

    // إذا جئت من صفحة كل المحاسبين
    $stores = Store::where('user_id', $user->id)
        ->where('status', 'active')
        ->get();

    if ($stores->isEmpty()) {
        return back()->with('error', 'لا يمكنك إضافة محاسب لأنه لا يوجد لديك أي متجر نشط.');
    }

    return view('user.accountants.create', [
        'store'  => null,
        'stores' => $stores
    ]);
}

    /*
    |--------------------------------------------------------------------------
    | حفظ محاسب جديد
    |--------------------------------------------------------------------------
    */
    public function store(Request $request)
    {
        $user = auth()->user();

        $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:accountants,email',
            'password' => 'required|min:6',
            'phone'    => 'required|string',
            'store_id' => [
                'required',
                Rule::exists('stores', 'id')->where(fn ($query) => $query
                    ->where('user_id', $user->id)
                    ->where('status', 'active')),
            ],
        ]);

        // التحقق من ملكية المتجر
        $store = Store::where('id', $request->store_id)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->firstOrFail();

        // فحص حدود الخطة
        PlanLimitService::assertCanAddAccountant($store);

        // إنشاء أو جلب الموظف
        $employee = Employee::firstOrCreate(
    [
        'store_id' => $store->id,
        'phone'    => $request->phone,
    ],
    [
        'user_id'   => $user->id,
        'name'      => $request->name,
        'role'      => 'accountant',
        'salary'    => $request->salary ?? 0, // وضع الراتب هنا
        'status'    => 'active',
        'added_by'  => $user->id,
        'email'     => $request->email,
    ]
);

// في حال كان الموظف موجود مسبقًا بدون user_id
if (!$employee->user_id) {
    $employee->update(['user_id' => $user->id]);
}
// في حال كان الموظف موجود مسبقًا، نقوم بتحديث راتبه والـ user_id
    $employee->update([
        'user_id' => $user->id,
        'salary'  => $request->salary ?? $employee->salary // تحديث الراتب إذا أُرسل
    ]);
        // إنشاء المحاسب
        $accountant = Accountant::create([
            'user_id'     => $user->id,
            'store_id'    => $store->id,
            'employee_id' => $employee->id,
            'name'        => $request->name,
            'email'       => $request->email,
            'phone'       => $request->phone,
            'password'    => bcrypt($request->password),
            'role'        => 'accountant',
            'status'      => 'active',
        ]);

        // تسجيل اللوق
        $this->addLog(
            'accountant_created',
            "تم إضافة المحاسب: {$accountant->name} براتب: {$employee->salary}",
            $accountant,
            [
                'store_id'    => $store->id,
                'employee_id' => $employee->id,
                'new_values'  => $accountant->only(['name', 'email', 'phone', 'store_id', 'employee_id']),
            ]
        );
        $returnTo = $this->safeReturnTo($request->input('return_to'));
        if ($returnTo) {
            return redirect()->to($returnTo)
                ->with('success', 'تم إضافة المحاسب بنجاح');
        }
       // fallback: ارجع لنفس نطاق الإنشاء عند عدم وجود return_to صريح.
       return redirect()
       ->route($request->store_id ? 'user.stores.accountants.index' : 'user.accountants.index', $request->store_id ? [$request->store_id] : [])
 ->with('success', 'تم إضافة المحاسب بنجاح');
 }

    /*
    |--------------------------------------------------------------------------
    | صفحة تعديل محاسب
    |--------------------------------------------------------------------------
    */
    public function edit($id)
    {
        $accountant = Accountant::with('employee.store')
            ->forUserStores()
            ->findOrFail($id);

        $user = auth()->user();

        $stores = Store::where('user_id', $user->id)
            ->where('status', 'active')
            ->get();

        return view('user.accountants.edit', compact('accountant', 'stores'));
    }

    /*
    |--------------------------------------------------------------------------
    | تحديث بيانات محاسب
    |--------------------------------------------------------------------------
    */
    public function update(Request $request, $id)
{
    $user = auth()->user();

    $accountant = Accountant::with(['store', 'employee'])->forUserStores()->findOrFail($id);

    $request->validate([
        'email'    => 'required|email|unique:accountants,email,' . $accountant->id,
        'password' => 'nullable|min:6',
        'status'   => 'required|in:active,suspended',
        'store_id' => [
            'required',
            Rule::exists('stores', 'id')->where(fn ($query) => $query
                ->where('user_id', $user->id)
                ->where('status', 'active')),
        ],
        'name'     => 'required|string|max:255',
        'phone'    => 'required|string|max:20',
        'salary'   => 'required|numeric|min:0',
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
            function ($attribute, $value, $fail) use ($request, $accountant) {
                if ((int) $request->input('store_id') === (int) optional($accountant->employee)->store_id) {
                    return;
                }

                if (!$value) {
                    $fail('يجب تحديد تاريخ نقل الموظف عند تغيير متجر المحاسب.');
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

    $salaryEffectiveDate = $request->input('salary_effective_mode') === 'custom' && $request->filled('salary_effective_date')
        ? Carbon::parse($request->input('salary_effective_date'))->toDateString()
        : now()->toDateString();

    $transferEffectiveDate = optional($accountant->employee)->store_id != (int) $request->input('store_id')
        ? Carbon::parse($request->input('transfer_effective_date'))->startOfDay()
        : null;

    $store = Store::where('id', $request->store_id)
        ->where('user_id', $user->id)
        ->firstOrFail();

    DB::transaction(function () use ($request, $accountant, $store, $salaryEffectiveDate, $transferEffectiveDate) {
        $oldEmployeeStoreId = optional($accountant->employee)->store_id;

        // 🔵 1) تحديث بيانات المحاسب
        $isStoreTransfer = $oldEmployeeStoreId && (int) $oldEmployeeStoreId !== (int) $store->id;

        $data = [
            'email'    => $request->email,
            'status'   => $isStoreTransfer ? 'suspended' : $request->status,
            'store_id' => $isStoreTransfer ? $accountant->store_id : $store->id,
            'name'     => $request->name,
            'phone'    => $request->phone,

        ];

        if ($request->filled('password')) {
            $data['password'] = bcrypt($request->password);
        }

        $accountant->update($data);

        // 🔵 2) تحديث بيانات الموظف المالية المرتبطة دون ربط حالة الموظف بحالة حساب الدخول.
        if ($accountant->employee) {
            $oldSalary = (float) $accountant->employee->salary;
            $accountant->employee->update([
                'name'     => $request->name,
                'phone'    => $request->phone,
                'store_id' => $store->id,
                'salary'   => $request->salary,

            ]);

            if ((float) $oldSalary !== (float) $accountant->employee->salary) {
                \App\Services\EmployeeLogService::add(
                    $accountant->employee,
                    'salary_update',
                    "تعديل الراتب من {$oldSalary} إلى {$accountant->employee->salary} ريال",
                    null,
                    [
                        'old_salary' => (float) $oldSalary,
                        'new_salary' => (float) $accountant->employee->salary,
                        'effective_date' => $salaryEffectiveDate,
                    ]
                );
            }

            if ($oldEmployeeStoreId && (int) $oldEmployeeStoreId !== (int) $store->id) {
                EmployeeService::transferEmployeeFinancialRecordsToStore($accountant->employee, (int) $store->id, true);

                $oldStore = Store::find($oldEmployeeStoreId);
                $newStore = $accountant->employee->store;

                if ($oldStore && $newStore) {
                    $transferLog = \App\Services\EmployeeLogService::add(
                        $accountant->employee,
                        'employee_transferred',
                        "تم نقل الموظف المرتبط بالمحاسب من متجر {$oldStore->name} إلى متجر {$newStore->name} أثناء تعديل بيانات المحاسب.",
                        null,
                        [
                            'old_store_id' => $oldStore->id,
                            'old_store_name' => $oldStore->name,
                            'new_store_id' => $newStore->id,
                            'new_store_name' => $newStore->name,
                            'effective_date' => $transferEffectiveDate?->toDateString(),
                            'changed_from_accountant_profile' => true,
                            'active_accountant_suspended' => true,
                        ]
                    );

                    if ($transferLog && $transferEffectiveDate) {
                        $transferLog->forceFill(['created_at' => $transferEffectiveDate])->save();
                    }
                }
            }
        }
    });

    // 🔵 3) تسجيل السجل
    $this->addLog(
        'accountant_updated',
        "تم تعديل بيانات المحاسب: {$accountant->email}",
        $accountant,
        [
            'store_id'    => $store->id,
            'employee_id' => $accountant->employee_id,
        ]
    );

    $returnTo = $this->safeReturnTo($request->input('return_to'));
    if ($returnTo) {
        return redirect()->to($returnTo)
            ->with('success', 'تم تحديث بيانات المحاسب');
    }

    return redirect()
        ->route('user.accountants.show', $accountant->id)
        ->with('success', 'تم تحديث بيانات المحاسب');
}


    /*
    |--------------------------------------------------------------------------
    | إيقاف محاسب
    |--------------------------------------------------------------------------
    */
    public function suspend($id)
    {
        $accountant = Accountant::forUserStores()->findOrFail($id);

        $oldStatus = $accountant->status;

        $accountant->update(['status' => 'suspended']);

        $this->addLog(
            'accountant_suspended',
            "تم إيقاف المحاسب: {$accountant->name}",
            $accountant,
            [
                'store_id'    => $accountant->store_id,
                'employee_id' => $accountant->employee_id,
                'old_values'  => ['status' => $oldStatus],
                'new_values'  => ['status' => 'suspended'],
            ]
        );

        $returnTo = $this->safeReturnTo(request('return_to'));
        if ($returnTo) {
            return redirect()->to($returnTo)->with('success', 'تم إيقاف المحاسب');
        }

        return back()->with('success', 'تم إيقاف المحاسب');
    }

    /*
    |--------------------------------------------------------------------------
    | تفعيل محاسب
    |--------------------------------------------------------------------------
    */
   public function activate($id)
    {
        $accountant = Accountant::with('employee')->forUserStores()->findOrFail($id);

        if (!$accountant->employee || $accountant->employee->status !== 'active') {
            return back()->with('error', 'لا يمكن تفعيل حساب المحاسب لأن الموظف المرتبط به غير فعال.');
        }

        if ((int) $accountant->employee->store_id !== (int) $accountant->store_id) {
            return back()->with('error', 'لا يمكن تفعيل حساب المحاسب لأن الموظف المرتبط به ليس على نفس متجر حساب المحاسب. رقّه من المتجر الجديد لإنشاء/تفعيل حساب صحيح هناك.');
        }

        $accountant->update(['status' => 'active']);

        // ⭐ مسح القيود باستخدام الإيميل فقط
        $throttleKey = Str::lower($accountant->email);
        RateLimiter::clear($throttleKey);

        $returnTo = $this->safeReturnTo(request('return_to'));
        if ($returnTo) {
            return redirect()->to($returnTo)->with('success', 'تم تفعيل الحساب ومسح قيود الدخول بنجاح.');
        }

        return back()->with('success', 'تم تفعيل الحساب ومسح قيود الدخول بنجاح.');
    }


    private function normalizeAccountantReturnTo(?string $returnTo, Accountant $accountant): ?string
    {
        if (!$returnTo) {
            return null;
        }

        $path = parse_url($returnTo, PHP_URL_PATH) ?: '';
        if (! Str::contains($path, '/employees')) {
            return $returnTo;
        }

        parse_str(parse_url($returnTo, PHP_URL_QUERY) ?: '', $query);
        $storeId = $query['store'] ?? $query['store_id'] ?? $accountant->store_id;

        return route('user.accountants.index', array_filter(['store' => $storeId]));
    }

    private function safeReturnTo(?string $returnTo): ?string
    {
        if (!$returnTo) {
            return null;
        }

        if (str_starts_with($returnTo, '/')) {
            return $returnTo;
        }

        $appHost = parse_url(url('/'), PHP_URL_HOST);
        $targetHost = parse_url($returnTo, PHP_URL_HOST);

        return $targetHost && $appHost === $targetHost ? $returnTo : null;
    }

    /*
    |--------------------------------------------------------------------------
    | حذف محاسب (Soft Delete)
    |--------------------------------------------------------------------------
    */
    public function delete($id)
    {
        $accountant = Accountant::with('employee')->forUserStores()->findOrFail($id);

        $blockers = $this->accountantDeletionBlockers($accountant);
        if (! empty($blockers)) {
            return back()->with('error', 'لا يمكن حذف المحاسب لوجود سجلات مرتبطة باسمه: ' . implode('، ', $blockers) . '. يمكنك إيقافه فقط للحفاظ على السجلات.');
        }

        $accountant->delete();

        $this->addLog(
            'accountant_deleted',
            "تم حذف المحاسب (Soft Delete): {$accountant->name}",
            $accountant,
            [
                'store_id'    => $accountant->store_id,
                'employee_id' => $accountant->employee_id,
                'old_values'  => ['status' => $accountant->status],
                'new_values'  => ['status' => 'deleted'],
            ]
        );

        $returnTo = $this->safeReturnTo(request('return_to'));
        if ($returnTo) {
            return redirect()->to($returnTo)->with('success', 'تم حذف المحاسب (سلة المحذوفات).');
        }

        return back()->with('success', 'تم حذف المحاسب (سلة المحذوفات).');
    }

    /*
    |--------------------------------------------------------------------------
    | عرض سلة المحذوفات
    |--------------------------------------------------------------------------
    */
    public function trash()
    {
        $accountantsQuery = Accountant::onlyTrashed()
            ->forUserStores()
            ->with('employee.store');

        if (request('store_id') && auth()->user()?->stores()->where('id', request('store_id'))->exists()) {
            $accountantsQuery->where('store_id', (int) request('store_id'));
        }

        $accountants = $accountantsQuery->get();

        return view('user.accountants.trash', compact('accountants'));
    }

    /*
    |--------------------------------------------------------------------------
    | استعادة محاسب
    |--------------------------------------------------------------------------
    */
    public function restore($id)
    {
        $accountant = Accountant::onlyTrashed()
            ->forUserStores()
            ->findOrFail($id);

        $accountant->restore();

        $this->addLog(
            'accountant_restored',
            "تم استعادة المحاسب: {$accountant->name}",
            $accountant,
            [
                'store_id'    => $accountant->store_id,
                'employee_id' => $accountant->employee_id,
                'new_values'  => ['status' => 'restored'],
            ]
        );

        return back()->with('success', 'تم استعادة المحاسب بنجاح.');
    }

    /*
    |--------------------------------------------------------------------------
    | حذف نهائي
    |--------------------------------------------------------------------------
    */
   public function forceDelete($id)
{
    $user = auth()->user();

    $accountant = Accountant::onlyTrashed()
        ->whereIn('store_id', $user->stores->pluck('id'))
        ->where('id', $id)
        ->firstOrFail();

    $blockers = $this->accountantDeletionBlockers($accountant);
    if (! empty($blockers)) {
        return back()->with('error', 'لا يمكن حذف المحاسب نهائيًا لوجود سجلات مرتبطة باسمه: ' . implode('، ', $blockers) . '.');
    }

    // حذف الموظف إذا لم يكن مرتبطًا بأي شيء
    if ($accountant->employee) {
        $accountant->employee->forceDelete();
    }

    // حذف المحاسب نهائيًا
    $accountant->forceDelete();

    return redirect()
        ->route('user.accountants.trash')
        ->with('success', 'تم حذف المحاسب نهائيًا.');
}


    private function accountantDeletionBlockers(Accountant $accountant): array
    {
        $blockers = [];

        $this->appendTableBlocker($blockers, 'sales', 'accountant_id', $accountant->id, 'مبيعات');
        $this->appendTableBlocker($blockers, 'daily_balances', 'accountant_id', $accountant->id, 'إقفالات يومية');
        $this->appendTableBlocker($blockers, 'device_tokens', 'accountant_id', $accountant->id, 'جلسات/أجهزة');
        $this->appendTableBlocker($blockers, 'logs', 'model_id', $accountant->id, 'سجلات نظام', fn ($query) => $query->where('model_type', Accountant::class));

        $employee = $accountant->employee;
        if ($employee) {
            $this->appendTableBlocker($blockers, 'employee_logs', 'person_id', $employee->id, 'سجلات الموظف', fn ($query) => $query->where('person_type', Employee::class));
            $this->appendTableBlocker($blockers, 'employee_withdrawals', 'person_id', $employee->id, 'سحوبات', fn ($query) => $query->where('person_type', Employee::class));
            $this->appendTableBlocker($blockers, 'debts', 'person_id', $employee->id, 'مديونيات', fn ($query) => $query->where('person_type', Employee::class));
            $this->appendTableBlocker($blockers, 'credit_sales', 'person_id', $employee->id, 'بيع آجل', fn ($query) => $query->where('person_type', Employee::class));
            $this->appendTableBlocker($blockers, 'employee_absences', 'person_id', $employee->id, 'غيابات', fn ($query) => $query->where('person_type', Employee::class));
            $this->appendTableBlocker($blockers, 'employee_salary_reports', 'person_id', $employee->id, 'تقارير رواتب', fn ($query) => $query->where('person_type', Employee::class));
            $this->appendTableBlocker($blockers, 'sales', 'employee_id', $employee->id, 'مبيعات موظف');
        }

        return array_values(array_unique($blockers));
    }

    private function appendTableBlocker(array &$blockers, string $table, string $column, int $value, string $label, ?callable $scope = null): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        $query = DB::table($table)->where($column, $value);
        if ($scope) {
            $scope($query);
        }

        if ($query->exists()) {
            $blockers[] = $label;
        }
    }


}
