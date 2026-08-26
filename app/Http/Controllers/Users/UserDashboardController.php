<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use App\Models\CreditSale;
use App\Models\DailyBalance;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\Log;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\Withdrawal;
use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Services\EmployeeLogService;
use App\Services\ShiftLifecycleService;
use App\Services\Stores\StoreAccessService;
use App\Services\Stores\ActiveAccountantService;
use App\Services\Shifts\ShiftGapInfoService;
use App\Services\Shifts\ShiftGapRequestService;
use App\Services\Accounting\FinancialSummaryService;
use App\Services\Employees\EmployeePayrollService;
use App\Services\Users\OwnerDashboardViewService;
use App\Modules\PurchaseOrders\Models\StorePurchaseOrder;

class UserDashboardController extends Controller
{
    private const COLLECTED_SALE_TYPES = ['cash', 'card', 'credit', 'mixed'];

    /**
     * عرض لوحة المالك بعد تجميع كل جزء من البيانات داخل دالة مستقلة.
     */
    public function index(Request $request)
    {
        $user = auth('web')->user();
        $stores = app(StoreAccessService::class)->activeStoresForOwner($user);
        $storeIds = $stores->pluck('id');
        $dashboardDate = $this->dashboardDate($request);

        if ($storeIds->isEmpty()) {
            return view('dashboard.user.index', array_merge(
                $this->emptyStateData($user, $stores),
                ['dashboardDate' => $dashboardDate, 'storesWithoutBalance' => collect()]
            ));
        }

        $dailySummary = $this->buildDailySummary($storeIds, $dashboardDate);
        $monthlySummary = $this->buildMonthlySummary($user->id, $storeIds, $dashboardDate);
        $salarySummary = $this->buildSalarySummary($user, $storeIds, $dashboardDate);
        $creditSummary = $this->buildCreditSummary($storeIds);
        $inventorySummary = $this->buildInventorySummary($user->id, $storeIds, $dashboardDate);
        $metricStoreBreakdowns = app(OwnerDashboardViewService::class)->storeBreakdowns(
            $stores,
            $monthlySummary['store_metrics'],
            $salarySummary['salariesByStore'],
            self::COLLECTED_SALE_TYPES,
            $dashboardDate
        );

        $subscriptionEnd = $user->subscription_end_at;
        $daysLeft = $subscriptionEnd ? now()->diffInDays($subscriptionEnd, false) : null;
        $chartData = $this->prepareChartData($storeIds, $dashboardDate);
        $activities = Log::with('store')
            ->whereIn('store_id', $storeIds)
            ->where(function ($query) use ($dashboardDate) {
                $query->where('details->business_date', $dashboardDate->toDateString())
                    ->orWhere('details->operation_date', $dashboardDate->toDateString())
                    ->orWhere(function ($legacyQuery) use ($dashboardDate) {
                        $legacyQuery->whereNull('details->business_date')
                            ->whereNull('details->operation_date')
                            ->whereDate('created_at', $dashboardDate->toDateString());
                    });
            })
            ->latest()
            ->limit(10)
            ->get();
        $storesWithoutBalance = $this->storesWithoutBalance($stores, $dashboardDate);
        $pendingPurchaseOrderAlerts = StorePurchaseOrder::with(['store', 'accountant', 'items:id,store_purchase_order_id,inventory_count_attempt'])
            ->whereIn('store_id', $storeIds)
            ->whereIn('workflow_status', ['pending_owner_review', 'returned_after_edit', 'returned_after_count', 'pending_owner_receipt_review', 'pending_inventory_approval'])
            ->latest('updated_at')
            ->limit(8)
            ->get();

        // نجمع مصادر التنبيهات مرة واحدة حتى يستخدم رأس لوحة المالك العدد نفسه المعروض في الصفحة المستقلة.
        $suspendedEmployeeAlerts = $this->buildSuspendedEmployeeAlerts($user, $storeIds);
        $missingShiftAlerts = $this->buildMissingShiftAlerts($stores);
        $pendingStoreTransfersCount = $this->pendingStoreTransfersCount($storeIds);
        $firstPendingTransfer = $this->firstPendingStoreTransfer($storeIds);
        $administrativeAlertsCount = $this->administrativeAlertsCount(
            $missingShiftAlerts,
            $suspendedEmployeeAlerts,
            $pendingStoreTransfersCount,
            $monthlySummary['totals'],
            $salarySummary,
            $creditSummary,
        );

        return view('dashboard.user.index', array_merge(
            [
                'user' => $user,
                'stores' => $stores,
                'daysLeft' => $daysLeft,
                'activities' => $activities,
                'metricStoreBreakdowns' => $metricStoreBreakdowns,
                'suspendedEmployeeAlerts' => $suspendedEmployeeAlerts,
                'missingShiftAlerts' => $missingShiftAlerts,
                'pendingStoreTransfersCount' => $pendingStoreTransfersCount,
                'administrativeAlertsCount' => $administrativeAlertsCount,
                'dashboardDate' => $dashboardDate,
                'storesWithoutBalance' => $storesWithoutBalance,
                'pendingPurchaseOrderAlerts' => $pendingPurchaseOrderAlerts,
            ],
            $dailySummary,
            $monthlySummary['totals'],
            $salarySummary,
            $creditSummary,
            $inventorySummary,
            $chartData
        ));
    }

    public function administrativeAlerts()
    {
        // هذه الصفحة تعيد استخدام ملخصات لوحة المالك ولا تنشئ أو تعدل أي سجل في قاعدة البيانات.
        $user = auth('web')->user();
        $stores = app(StoreAccessService::class)->activeStoresForOwner($user);
        $storeIds = $stores->pluck('id');
        $dashboardDate = today();
        $dailySummary = $this->buildDailySummary($storeIds, $dashboardDate);
        $monthlyTotals = $this->buildMonthlySummary($user->id, $storeIds, $dashboardDate)['totals'];
        $salarySummary = $this->buildSalarySummary($user, $storeIds);
        $creditSummary = $this->buildCreditSummary($storeIds);
        $missingShiftAlerts = $this->buildMissingShiftAlerts($stores);
        $suspendedEmployeeAlerts = $this->buildSuspendedEmployeeAlerts($user, $storeIds);
        $pendingStoreTransfersCount = $this->pendingStoreTransfersCount($storeIds);
        $firstPendingTransfer = $this->firstPendingStoreTransfer($storeIds);

        return view('user.administrative-alerts', array_merge(
            compact(
                'stores',
                'missingShiftAlerts',
                'suspendedEmployeeAlerts',
                'pendingStoreTransfersCount',
                'firstPendingTransfer',
            ),
            $dailySummary,
            $monthlyTotals,
            $salarySummary,
            $creditSummary,
        ));
    }

    private function dashboardDate(Request $request): Carbon
    {
        $requestedDate = $request->query('date');

        if (! is_string($requestedDate) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $requestedDate)) {
            return today();
        }

        try {
            $date = Carbon::createFromFormat('Y-m-d', $requestedDate)->startOfDay();

            return $date->format('Y-m-d') === $requestedDate && $date->lte(today()) ? $date : today();
        } catch (\Throwable) {
            return today();
        }
    }

    private function storesWithoutBalance(Collection $stores, Carbon $dashboardDate): Collection
    {
        $balancedStoreIds = DailyBalance::query()
            ->whereIn('store_id', $stores->pluck('id'))
            ->whereDate('business_date', $dashboardDate->toDateString())
            ->where(function ($query) {
                $query->whereNotNull('closed_at')->orWhereNotNull('end_time');
            })
            ->pluck('store_id')
            ->unique();

        return $stores
            ->reject(fn ($store) => $balancedStoreIds->contains($store->id))
            ->values();
    }

    private function firstPendingStoreTransfer(Collection $storeIds): ?\App\Models\StoreTransfer
    {
        return \App\Models\StoreTransfer::where('status', 'pending')
            ->where(function ($query) use ($storeIds) {
                $query->whereIn('sender_store_id', $storeIds)
                    ->orWhereIn('receiver_store_id', $storeIds);
            })
            ->oldest()
            ->first(['id', 'sender_store_id', 'receiver_store_id', 'status']);
    }

    private function pendingStoreTransfersCount(Collection $storeIds): int
    {
        // التنبيه يشمل التحويلات الواردة والصادرة لجميع متاجر المالك النشطة.
        return \App\Models\StoreTransfer::where('status', 'pending')
            ->where(function ($query) use ($storeIds) {
                $query->whereIn('sender_store_id', $storeIds)
                    ->orWhereIn('receiver_store_id', $storeIds);
            })->count();
    }

    private function administrativeAlertsCount(
        Collection $missingShiftAlerts,
        Collection $suspendedEmployeeAlerts,
        int $pendingStoreTransfersCount,
        array $monthlySummary,
        array $salarySummary,
        array $creditSummary,
    ): int {
        // العدد يمثل الحالات القابلة للمراجعة، وليس عدد أقسام الصفحة فقط.
        return $missingShiftAlerts->sum('missing_count')
            + $suspendedEmployeeAlerts->count()
            + $pendingStoreTransfersCount
            + (($monthlySummary['expensesMonth'] ?? 0) > ($monthlySummary['salesMonth'] ?? 0) ? 1 : 0)
            + (int) ($creditSummary['creditLate'] ?? 0)
            + collect($salarySummary['employeeSalaryThresholdAlerts'] ?? [])->count()
            + (int) ($salarySummary['employeesWithoutSalaryCount'] ?? 0);
    }


    private function buildMissingShiftAlerts(Collection $stores): Collection
    {
        $shiftService = app(ShiftLifecycleService::class);

        return $stores->filter(fn ($store) => $store->status === 'active')->map(function ($store) use ($shiftService) {
            $missingDates = $shiftService->missingBusinessDates($store->id);

            if (empty($missingDates)) {
                return null;
            }

            $activeAccountants = app(ActiveAccountantService::class)->activeAccountantsForStore($store, auth()->user());
            $shiftRows = collect($missingDates)->flatMap(function ($date) use ($store) {
                return app(ShiftGapInfoService::class)->missingShiftRowsForDate($store, $date);
            })->values();
            $requestStatuses = app(ShiftGapRequestService::class)->activeStatusesForMissingRows($store->id, $shiftRows);

            return [
                'store' => $store,
                'active_accountants' => $activeAccountants,
                'missing_dates' => $shiftRows->map(fn ($row) => array_merge($row, [
                    'request_status' => $requestStatuses[$row['date'].'#'.$row['missing_shift_number']] ?? null,
                ]))->values(),
                'missing_count' => $shiftRows->count(),
                'first_missing_date' => $missingDates[0],
                'last_missing_date' => $missingDates[count($missingDates) - 1],
            ];
        })->filter()->values();
    }



    public function dismissSuspendedEmployeeAlert(Employee $employee, Request $request)
    {
        $user = auth('web')->user();
        $this->authorizeOwnerEmployee($employee, $user);

        $nextReminder = $this->nextSuspendedEmployeeReminderDate();

        Cache::put(
            $this->suspendedEmployeeTravelCacheKey($user->id, $employee->id),
            true,
            $nextReminder
        );

        EmployeeLogService::add(
            $employee,
            'employee_unpaid_leave_confirmed',
            "تم اعتبار الموظف {$employee->name} مسافر / إجازة بدون راتب حتى تاريخ {$nextReminder->format('Y-m-d')}",
            null,
            ['next_reminder_at' => $nextReminder->toDateString()]
        );

        return back()->with('success', 'تم تأجيل تنبيه الموظف الموقوف حتى تاريخ 10 القادم وتسجيله كمسافر / إجازة بدون راتب.');
    }

    public function terminateSuspendedEmployee(Employee $employee, Request $request)
    {
        $user = auth('web')->user();
        $this->authorizeOwnerEmployee($employee, $user);

        if ($employee->status !== 'suspended') {
            return back()->with('error', 'لا يمكن تنفيذ الفصل إلا على موظف موقوف.');
        }

        DB::transaction(function () use ($employee) {
            EmployeeLogService::add($employee, 'employee_terminated', "تم فصل الموظف {$employee->name} من تنبيه المالك بعد مراجعة المديونيات والبيع الآجل.");

            $employee->accountant()
                ->withTrashed()
                ->update(['status' => 'suspended']);

            $employee->accountant()->withTrashed()->delete();
            $employee->delete();
        });

        return back()->with('success', 'تم فصل الموظف وحذف حساب المحاسب المرتبط إن وجد.');
    }

    /**
     * إرجاع بطاقات اليوم وآخر عملية دون إعادة تحميل الصفحة.
     *
     * النتيجة تخزن لثلاث ثوانٍ فقط لمنع تكرار الحساب نفسه بين عدة تبويبات.
     */
    public function dailySnapshot(Request $request)
    {
        $user = auth('web')->user();
        $stores = app(StoreAccessService::class)->activeStoresForOwner($user);
        $storeIds = $stores->pluck('id');
        $dashboardDate = $this->dashboardDate($request);
        $storeKey = $storeIds->sort()->implode('-') ?: 'none';
        $cacheKey = "owner-dashboard:{$user->id}:daily-snapshot:{$dashboardDate->toDateString()}:{$storeKey}";

        $snapshot = Cache::remember($cacheKey, now()->addSeconds(3), function () use ($storeIds, $dashboardDate) {
            $dailySummary = $this->buildDailySummary($storeIds, $dashboardDate);
            $latestSale = Sale::query()
                ->collectedDashboardSales()
                ->whereIn('store_id', $storeIds)
                ->forAccountingDate($dashboardDate->toDateString())
                ->with(['store:id,name', 'items.product:id,name'])
                ->latest()
                ->first();

            return [
                'sales_today' => $dailySummary['salesToday'],
                'expenses_today' => $dailySummary['expensesToday'],
                'products_cost_today' => $dailySummary['productsCostToday'],
                // المصروفات لا تخصم من الربح بناءً على توجيه النظام الحالي.
                'profit_today' => $dailySummary['profitToday'],
                'operations_count' => $dailySummary['dailySalesOperationsCount'],
                'latest_operation' => $this->buildLatestOperation($latestSale),
            ];
        });

        $snapshot['updated_at'] = now()->format('h:i:s A');

        return response()->json($snapshot)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }

    /**
     * حساب مؤشرات اليوم. الربح لا يخصم المصروفات حسب السلوك المعتمد حاليًا.
     */
    private function buildDailySummary(Collection $storeIds, Carbon $dashboardDate): array
    {
        $salesQuery = Sale::query()
            ->collectedDashboardSales()
            ->whereIn('store_id', $storeIds)
            ->forAccountingDate($dashboardDate->toDateString());

        $salesToday = (float) (clone $salesQuery)->sum('paid_amount');
        $dailyFinancialSummary = app(FinancialSummaryService::class)->storeSummariesForPeriod(
            $storeIds,
            $dashboardDate->copy()->startOfDay(),
            $dashboardDate->copy()->endOfDay(),
            self::COLLECTED_SALE_TYPES
        );
        $dailyTotals = $dailyFinancialSummary->totals();
        $productsCostToday = $dailyTotals->productsCost;

        return [
            'salesToday' => $salesToday,
            'dailySalesOperationsCount' => (int) (clone $salesQuery)->count(),
            'productsCostToday' => $productsCostToday,
            'expensesToday' => $dailyTotals->expenses,
            'profitToday' => $salesToday - $productsCostToday,
        ];
    }

    /**
     * بناء ملخص الشهر مرة واحدة مع القيم المجمعة حسب المتجر.
     */
    private function buildMonthlySummary(int $userId, Collection $storeIds, Carbon $dashboardDate): array
    {
        $monthKey = $dashboardDate->format('Y-m');
        $storeKey = $storeIds->sort()->implode('-');

        return Cache::remember(
            "owner-dashboard:{$userId}:monthly-summary:{$monthKey}:{$storeKey}",
            now()->addMinutes(5),
            function () use ($storeIds, $dashboardDate) {
                $monthStart = $dashboardDate->copy()->startOfMonth();
                $monthEnd = $dashboardDate->copy()->endOfMonth();
                $monthlyFinancialSummary = app(FinancialSummaryService::class)->storeSummariesForPeriod(
                    $storeIds,
                    $monthStart,
                    $monthEnd,
                    self::COLLECTED_SALE_TYPES
                );

                $storeMetrics = $monthlyFinancialSummary->summariesByStore
                    ->map(fn ($storeMetrics) => [
                        'sales_month' => $storeMetrics->sales,
                        'products_cost_month' => $storeMetrics->productsCost,
                        'expenses_month' => $storeMetrics->expenses,
                        'monthly_owner_purchases' => $storeMetrics->ownerPurchases,
                        'monthly_accountant_consumption' => $storeMetrics->internalUse,
                        'monthly_purchases_consumption' => $storeMetrics->purchasesAndInternalUse(),
                        'profit_month' => $storeMetrics->profit(),
                    ])
                    ->all();

                $monthlyTotals = $monthlyFinancialSummary->totals();

                return [
                    'totals' => [
                        'salesMonth' => $monthlyTotals->sales,
                        'expensesMonth' => $monthlyTotals->expenses,
                        'profitMonth' => $monthlyTotals->profit(),
                        'monthlyOwnerPurchases' => $monthlyTotals->ownerPurchases,
                        'monthlyAccountantConsumption' => $monthlyTotals->internalUse,
                        'monthlyPurchasesAndConsumption' => $monthlyTotals->purchasesAndInternalUse(),
                    ],
                    'store_metrics' => $storeMetrics,
                ];
            }
        );
    }

    /**
     * تجهيز الرواتب والسحوبات، بما فيها مجموع الرواتب لكل متجر باستعلام واحد.
     */
    private function buildSalarySummary($user, Collection $storeIds, ?Carbon $dashboardDate = null): array
    {
        $employeesWithoutSalary = $user->employees()
            ->with('store:id,name')
            ->whereIn('store_id', $storeIds)
            ->where(function ($query) {
                $query->whereNull('salary')->orWhere('salary', '<=', 0);
            })
            ->orderBy('store_id')
            ->orderBy('name')
            ->get();

        $salaryMonth = $dashboardDate ?: now();
        $periodStart = $salaryMonth->copy()->startOfMonth();
        $periodEnd = $salaryMonth->copy()->endOfMonth();
        $employeeMonthlyWithdrawals = app(EmployeePayrollService::class)->salaryRowsForStores($storeIds, $periodStart, $periodEnd);
        $employeeIds = $employeeMonthlyWithdrawals->pluck('id')->unique()->values();
        $openCreditTotals = CreditSale::whereIn('store_id', $storeIds)
            ->where('person_type', Employee::class)
            ->whereIn('person_id', $employeeIds)
            ->where('status', CreditSale::STATUS_PENDING)
            ->where('remaining_amount', '>', 0)
            ->selectRaw('person_id, COALESCE(SUM(remaining_amount), 0) as total')
            ->groupBy('person_id')
            ->pluck('total', 'person_id');

        $monthlyWithdrawalTotals = Withdrawal::whereIn('store_id', $storeIds)
            ->where('person_type', Employee::class)
            ->whereIn('person_id', $employeeIds)
            ->whereBetween('date', [$periodStart->toDateString(), $periodEnd->toDateString()])
            ->selectRaw('person_id, COALESCE(SUM(amount), 0) as total')
            ->groupBy('person_id')
            ->pluck('total', 'person_id');

        $salariesByStore = $employeeMonthlyWithdrawals
            ->groupBy('store_id')
            ->map(fn ($employees) => $employees->sum('salary'));

        $employeeSalaryRemainders = $employeeMonthlyWithdrawals
            ->map(function ($employee) use ($openCreditTotals, $monthlyWithdrawalTotals) {
                $salary = (float) $employee->salary;
                $withdrawalsTotal = (float) ($monthlyWithdrawalTotals[$employee->id] ?? $employee->withdrawals_total ?? 0);
                $openCreditTotal = (float) ($openCreditTotals[$employee->id] ?? 0);

                return [
                    'id' => $employee->id,
                    'name' => $employee->name,
                    'store_name' => $employee->store_name,
                    'base_salary' => (float) $employee->base_salary,
                    'salary' => $salary,
                    'worked_days' => (int) $employee->worked_days,
                    'suspended_days' => (int) $employee->suspended_days,
                    'withdrawals_total' => $withdrawalsTotal,
                    'open_credit_total' => $openCreditTotal,
                    'credit_salary_ratio' => $salary > 0 ? round(($openCreditTotal / $salary) * 100, 1) : 0,
                    'withdrawals_salary_ratio' => $salary > 0 ? round(($withdrawalsTotal / $salary) * 100, 1) : 0,
                    'total_obligations' => $openCreditTotal + $withdrawalsTotal,
                    'total_obligations_salary_ratio' => $salary > 0 ? round((($openCreditTotal + $withdrawalsTotal) / $salary) * 100, 1) : 0,
                    'absence_days' => (int) $employee->absence_days,
                    'absence_deduction' => (float) $employee->absence_deduction,
                    'salary_remaining' => max(
                        0,
                        $salary - $withdrawalsTotal - (float) $employee->absence_deduction
                    ),
                ];
            })
            ->values();
        $employeeSalaryThresholdAlerts = $employeeSalaryRemainders
            ->flatMap(function (array $employee) {
                if ((float) $employee['salary'] <= 0) {
                    return [];
                }

                $alerts = [];

                if ((float) $employee['credit_salary_ratio'] >= 50) {
                    $alerts[] = [
                        'type' => 'credit',
                        'level' => 'warning',
                        'employee_name' => $employee['name'],
                        'store_name' => $employee['store_name'],
                        'message' => "تنبيه الموظف {$employee['name']} تجاوزت إجمالي عمليات الأجل المرتبط به 50% من راتبه الشهري.",
                        'amount' => (float) $employee['open_credit_total'],
                        'salary' => (float) $employee['salary'],
                        'ratio' => (float) $employee['credit_salary_ratio'],
                    ];
                }

                if ((float) $employee['withdrawals_salary_ratio'] >= 50) {
                    $alerts[] = [
                        'type' => 'withdrawals',
                        'level' => 'warning',
                        'employee_name' => $employee['name'],
                        'store_name' => $employee['store_name'],
                        'message' => "تحذير الموظف {$employee['name']} بلغت سحوباته 50% من إجمالي راتبه.",
                        'amount' => (float) $employee['withdrawals_total'],
                        'salary' => (float) $employee['salary'],
                        'ratio' => (float) $employee['withdrawals_salary_ratio'],
                    ];
                }

                if ((float) $employee['total_obligations_salary_ratio'] >= 80) {
                    $alerts[] = [
                        'type' => 'combined',
                        'level' => 'danger',
                        'employee_name' => $employee['name'],
                        'store_name' => $employee['store_name'],
                        'message' => "تنبيه الموظف {$employee['name']} تجاوز إجمالي الأجل والسحوبات 80% من راتبه.",
                        'amount' => (float) $employee['total_obligations'],
                        'salary' => (float) $employee['salary'],
                        'ratio' => (float) $employee['total_obligations_salary_ratio'],
                    ];
                }

                return $alerts;
            })
            ->values();

        $monthlySalaries = (float) $salariesByStore->sum();
        $monthlyWorkerWithdrawals = (float) $employeeMonthlyWithdrawals->sum('withdrawals_total');
        $monthlyAbsenceDeductions = (float) $employeeMonthlyWithdrawals->sum('absence_deduction');

        return [
            'employeesCount' => $user->employees()->count(),
            'employeesWithoutSalary' => $employeesWithoutSalary,
            'employeesWithoutSalaryCount' => $employeesWithoutSalary->count(),
            'monthlySalaries' => $monthlySalaries,
            'monthlyWorkerWithdrawals' => $monthlyWorkerWithdrawals,
            'monthlyAbsenceDeductions' => $monthlyAbsenceDeductions,
            'netMonthlySalaries' => max(0, $monthlySalaries - $monthlyWorkerWithdrawals - $monthlyAbsenceDeductions),
            'employeeSalaryRemainders' => $employeeSalaryRemainders,
            'employeeSalaryThresholdAlerts' => $employeeSalaryThresholdAlerts,
            'salariesByStore' => $salariesByStore,
        ];
    }

    /**
     * مؤشرات المديونيات من المصدر الفعلي credit_sales.
     */
    private function buildCreditSummary(Collection $storeIds): array
    {
        return [
            'creditOpen' => CreditSale::whereIn('store_id', $storeIds)
                ->where('status', 'pending')
                ->where('remaining_amount', '>', 0)
                ->count(),
            'creditClosed' => CreditSale::whereIn('store_id', $storeIds)
                ->where('status', 'deducted')
                ->count(),
            'creditLate' => CreditSale::whereIn('store_id', $storeIds)
                ->where('status', 'pending')
                ->where('remaining_amount', '>', 0)
                ->whereDate('date', '<', now()->subDays(30))
                ->count(),
        ];
    }

    /**
     * قوائم المخزون المنخفض وأفضل المنتجات.
     */
    private function buildInventorySummary(int $userId, Collection $storeIds, Carbon $dashboardDate): array
    {
        $lowStockProducts = Product::with('store')
            ->whereIn('store_id', $storeIds)
            ->sellable()
            ->whereExists(function ($query) {
                $query->selectRaw('1')
                    ->from('sale_items')
                    ->whereColumn('sale_items.product_id', 'products.id');
            })
            ->lowStock()
            ->orderBy('quantity')
            ->get();

        $topSellingProducts = Cache::remember(
            "owner-dashboard:{$userId}:top-products:".$dashboardDate->format('Y-m').':'.$storeIds->sort()->implode('-'),
            now()->addMinutes(5),
            function () use ($storeIds, $dashboardDate) {
                $topProductsQuery = DB::table('sale_items')
                    ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
                    ->leftJoin('products', 'sale_items.product_id', '=', 'products.id')
                    ->join('stores', 'sales.store_id', '=', 'stores.id')
                    ->whereIn('sales.store_id', $storeIds)
                    ->whereIn('sales.sale_type', self::COLLECTED_SALE_TYPES)
                    ->where(function ($query) {
                        $query->whereNull('sales.description')
                            ->orWhere('sales.description', '!=', 'manual_invoice_entry');
                    })
                    ->where(function ($query) {
                        $query->where('sale_items.product_usage_snapshot', Product::USAGE_TYPE_SALE)
                            ->orWhere(function ($legacy) {
                                $legacy->whereNull('sale_items.product_usage_snapshot')
                                    ->where(function ($productUsage) {
                                        $productUsage->where('products.usage_type', Product::USAGE_TYPE_SALE)
                                            ->orWhereNull('products.usage_type');
                                    });
                            });
                    });

                app(FinancialSummaryService::class)->applyAccountingPeriodToTable(
                    $topProductsQuery,
                    'sales',
                    $dashboardDate->copy()->startOfMonth(),
                    $dashboardDate->copy()->endOfMonth()
                );

                return $topProductsQuery
                    ->groupBy('sales.store_id', 'stores.name', 'sale_items.product_id', 'sale_items.product_name_snapshot', 'products.name')
                    ->selectRaw('sales.store_id, stores.name as store_name, sale_items.product_id as id')
                    ->selectRaw("COALESCE(sale_items.product_name_snapshot, products.name, 'منتج محذوف') as name")
                    ->selectRaw('COUNT(DISTINCT sales.id) as operations_count')
                    ->selectRaw('COALESCE(SUM(COALESCE(sale_items.quantity_snapshot, sale_items.quantity)), 0) as sold_quantity')
                    ->selectRaw('COALESCE(SUM(sale_items.total), 0) as sales_value')
                    ->get()
                    ->groupBy('store_id')
                    ->flatMap(fn ($products) => $products
                        ->sortByDesc('sold_quantity')
                        ->take(5)
                        ->values())
                    ->values();
            }
        );

        return [
            'lowStockProducts' => $lowStockProducts,
            'lowStockCount' => $lowStockProducts->count(),
            'topSellingProducts' => $topSellingProducts,
        ];
    }

    /**
     * تحويل آخر عملية إلى بنية مختصرة للواجهة.
     */
    private function buildLatestOperation(?Sale $latestSale): ?array
    {
        if (! $latestSale) {
            return null;
        }

        $description = trim((string) $latestSale->description);
        $isTintOperation = mb_stripos($description, 'تضليل') !== false
            || mb_stripos($description, 'تظليل') !== false;
        $productNames = $latestSale->items
            ->map(fn ($item) => optional($item->product)->name)
            ->filter()
            ->unique()
            ->values();
        $operationName = $isTintOperation
            ? $description
            : ($productNames->isNotEmpty()
                ? $productNames->implode(' - ')
                : ($description ?: ((float) $latestSale->labor_total > 0 ? 'شغل يد' : 'عملية بيع')));

        return [
            'id' => (int) $latestSale->id,
            'store_name' => $latestSale->store->name ?? 'متجر غير معروف',
            'description' => $operationName,
            'is_tint' => $isTintOperation,
            'amount' => (float) ($latestSale->paid_amount ?? 0),
            'time' => optional($latestSale->created_at)->format('h:i A'),
        ];
    }

    /**
     * تجهيز مخطط آخر 14 يومًا من المبيعات والمصروفات والدين المتبقي الفعلي.
     */
    private function prepareChartData(Collection $storeIds, Carbon $dashboardDate): array
    {
        $chartStart = $dashboardDate->copy()->subDays(13)->startOfDay();
        $chartEnd = $dashboardDate->copy()->endOfDay();

        $dailySales = Sale::query()
            ->collectedDashboardSales()
            ->selectRaw('COALESCE(business_date, DATE(created_at)) as day, SUM(paid_amount) as total')
            ->whereIn('store_id', $storeIds)
            ->betweenAccountingDates($chartStart, $chartEnd)
            ->groupBy('day')
            ->get()
            ->keyBy('day');

        $dailyExpenses = Expense::selectRaw('COALESCE(business_date, DATE(created_at)) as day, SUM(amount) as total')
            ->whereIn('store_id', $storeIds)
            ->betweenAccountingDates($chartStart, $chartEnd)
            ->groupBy('day')
            ->get()
            ->keyBy('day');

        $dailyRemainingCredit = CreditSale::selectRaw('DATE(date) as day, SUM(remaining_amount) as total')
            ->whereIn('store_id', $storeIds)
            ->where('status', 'pending')
            ->where('remaining_amount', '>', 0)
            ->betweenOperationDates($chartStart, $chartEnd)
            ->groupBy('day')
            ->get()
            ->keyBy('day');

        $labels = [];
        $sales = [];
        $expenses = [];
        $remainingCredit = [];

        for ($dayOffset = 0; $dayOffset < 14; $dayOffset++) {
            $date = $chartStart->copy()->addDays($dayOffset)->toDateString();
            $labels[] = $date;
            $sales[] = (float) ($dailySales[$date]->total ?? 0);
            $expenses[] = (float) ($dailyExpenses[$date]->total ?? 0);
            $remainingCredit[] = (float) ($dailyRemainingCredit[$date]->total ?? 0);
        }

        return [
            'chartLabels' => $labels,
            'chartSales' => $sales,
            'chartExpenses' => $expenses,
            'chartCredit' => $remainingCredit,
        ];
    }



    private function buildSuspendedEmployeeAlerts($user, Collection $storeIds): Collection
    {
        return Employee::with(['store:id,name', 'accountant' => fn ($query) => $query->withTrashed()])
            ->whereIn('store_id', $storeIds)
            ->where('status', 'suspended')
            ->orderBy('updated_at', 'desc')
            ->get(['id', 'store_id', 'name', 'status', 'updated_at'])
            ->reject(fn (Employee $employee) => Cache::has($this->suspendedEmployeeTravelCacheKey($user->id, $employee->id)))
            ->map(function (Employee $employee) {
                $suspendedAt = \App\Models\EmployeeLog::where('person_type', Employee::class)
                    ->where('person_id', $employee->id)
                    ->where('action_name', 'employee_suspended')
                    ->latest('created_at')
                    ->value('created_at');
                $suspendedAtDate = $suspendedAt
                    ? \Carbon\Carbon::parse($suspendedAt)->format('Y-m-d')
                    : 'غير محدد - موقوف قبل نظام السجلات';

                $debtsTotal = (float) \App\Models\Debt::where('person_type', Employee::class)
                    ->where('person_id', $employee->id)
                    ->where('status', 'pending')
                    ->sum('amount');

                $creditTotal = (float) CreditSale::where('person_type', Employee::class)
                    ->where('person_id', $employee->id)
                    ->where('remaining_amount', '>', 0)
                    ->sum('remaining_amount');

                return [
                    'id' => $employee->id,
                    'name' => $employee->name,
                    'store_name' => $employee->store?->name ?? 'متجر غير معروف',
                    'suspended_at' => $suspendedAtDate,
                    'debts_total' => $debtsTotal,
                    'credit_total' => $creditTotal,
                    'has_accountant' => (bool) $employee->accountant,
                ];
            })
            ->values();
    }

    private function authorizeOwnerEmployee(Employee $employee, $user): void
    {
        if (!$user || !$user->stores()->where('id', $employee->store_id)->exists()) {
            abort(403);
        }
    }

    private function suspendedEmployeeTravelCacheKey(int $userId, int $employeeId): string
    {
        return "owner:{$userId}:suspended-employee-alert:{$employeeId}:traveler";
    }

    private function nextSuspendedEmployeeReminderDate()
    {
        $nextReminder = now()->copy()->day(10)->startOfDay();

        if (now()->greaterThanOrEqualTo($nextReminder)) {
            $nextReminder->addMonthNoOverflow();
        }

        return $nextReminder;
    }

    /**
     * بيانات آمنة عندما لا يملك المستخدم متاجر.
     */
    private function emptyStateData($user, Collection $stores): array
    {
        return [
            'stores' => $stores,
            'user' => $user,
            'employeesCount' => 0,
            'daysLeft' => 0,
            'salesToday' => 0,
            'salesMonth' => 0,
            'productsCostToday' => 0,
            'expensesToday' => 0,
            'expensesMonth' => 0,
            'profitToday' => 0,
            'profitMonth' => 0,
            'monthlySalaries' => 0,
            'monthlyWorkerWithdrawals' => 0,
            'monthlyAbsenceDeductions' => 0,
            'netMonthlySalaries' => 0,
            'monthlyOwnerPurchases' => 0,
            'monthlyAccountantConsumption' => 0,
            'monthlyPurchasesAndConsumption' => 0,
            'creditOpen' => 0,
            'metricStoreBreakdowns' => [],
            'dailySalesOperationsCount' => 0,
            'lowStockCount' => 0,
            'lowStockProducts' => collect(),
            'topSellingProducts' => collect(),
            'employeeSalaryRemainders' => collect(),
            'employeeSalaryThresholdAlerts' => collect(),
            'employeesWithoutSalary' => collect(),
            'employeesWithoutSalaryCount' => 0,
            'creditClosed' => 0,
            'creditLate' => 0,
            'activities' => collect(),
            'chartLabels' => [],
            'chartSales' => [],
            'chartExpenses' => [],
            'chartCredit' => [],
        ];
    }
}
