<?php

namespace App\Http\Controllers;

use App\Services\LogService;
use App\Models\Store;
use App\Models\DailyBalance;
use App\Models\Expense;
use App\Models\Withdrawal;
use App\Models\Sale;
use App\Models\Purchase;
use App\Models\Accountant;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log as LaravelLog;
use App\Support\ArabicPdf as PDF;
use App\Services\ShiftLifecycleService;
use App\Services\Stores\StoreAccessService;
use App\Services\Stores\ActiveAccountantService;
use App\Services\Stores\StoreDetailsService;
use App\Services\Stores\StoreDashboardService;
use App\Services\Shifts\ShiftGapInfoService;
use App\Services\Shifts\ShiftGapRequestService;
use App\Services\Shifts\ShiftGapOverviewService;
use App\Services\Shifts\ShiftSettingsHistoryService;
use App\Services\Shifts\ShiftOperationBinderService;
use App\Models\ArchivedItem;
use App\Services\AdministrativeArchiveService;
use App\Services\SupportSessionService;
use App\Services\Reports\MonthlyStoreReportService;
use App\Services\Reports\ComprehensiveStoreSearchReportService;
use App\Services\Reports\RecentReportFilesService;

/**
 * ===================================================================
 * StoreController - إدارة المتاجر
 * ===================================================================
 *
 * هذا الكنترولر مسؤول عن جميع عمليات المتاجر:
 * - إنشاء، تعديل، عرض، حذف المتاجر
 * - إحصائيات المتاجر (المخزون، المبيعات، الموظفين)
 * - إدارة حالة المتاجر (نشط/معطل)
 * - سلة المهملات واستعادة المتاجر المحذوفة
 *
 * جميع الدوال تتحقق من ملكية المستخدم للمتجر قبل التنفيذ
 * -------------------------------------------------------------------
 */

class StoreController extends Controller
{
    /**
     * =================================================================
     * دوال التحقق من الصلاحية والخطة
     * =================================================================
     */

    /**
     * التحقق من صلاحية إنشاء متجر جديد حسب الخطة
     *
     * @return bool
     */
    protected function canUserAddStore()
    {
        $user = auth()->user();
        if (!$user->plan_id && !$user->allowed_stores) return false;

        $allowed = $user->plan_id ? $user->plan->allowed_stores : $user->allowed_stores;

        // نحسب النشط وما زال في السلة، أما المؤرشف إداريًا فقد خرج من حساب المالك.
        return Store::withTrashed()
            ->where('user_id', $user->id)
            ->whereNotIn('id', app(AdministrativeArchiveService::class)->archivedIds(Store::class))
            ->count() < $allowed;
    }

    /**
     * =================================================================
     * دوال CRUD الأساسية (إنشاء، عرض، تعديل، حذف)
     * =================================================================
     */

    /**
     * عرض قائمة المتاجر
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        $user = auth()->user();

        // المتاجر الفعالة أولاً، وتبقى المتاجر الموقوفة مجمعة في نهاية القائمة.
        $stores = $user->stores()
            ->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
            ->latest()
            ->get();

        // إجمالي المتاجر (نشطة + في السلة) لغرض التحقق من الخطة
        $totalCountWithTrashed = Store::withTrashed()
            ->where('user_id', $user->id)
            ->whereNotIn('id', app(AdministrativeArchiveService::class)->archivedIds(Store::class))
            ->count();

        // عدد المحذوفات فقط للعرض في الأيقونة
        $trashedCount = $user->stores()->onlyTrashed()
            ->whereNotIn('id', app(AdministrativeArchiveService::class)->archivedIds(Store::class))
            ->count();

        return view('user.stores.index', compact('stores', 'trashedCount', 'totalCountWithTrashed'));
    }

    /**
     * عرض صفحة إنشاء متجر جديد
     *
     * @return \Illuminate\View\View|\Illuminate\Http\RedirectResponse
     */
    public function create()
    {
        $user = auth()->user();
        $totalUsed = $user->stores()->withTrashed()->count();
        $allowed = $user->plan->allowed_stores ?? $user->allowed_stores ?? 1;

        if ($totalUsed >= $allowed) {
            return redirect()->route('user.stores.index')
                ->with('error', 'لقد استنفدت الحد الأقصى للمتاجر المسموح بها في خطتك.');
        }

        return view('user.stores.create');
    }

    /**
     * حفظ متجر جديد في قاعدة البيانات
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {
        // التحقق من البيانات
        $request->validate([
            'name'                => 'required|string|max:255',
            'phone'               => 'nullable|string|max:255',
            'address'             => 'nullable|string|max:255',
            'commercial_registration' => 'nullable|string|max:255',
            'tax_number'          => 'nullable|string|max:255',
            'description'         => 'nullable|string',
            'number_of_shifts'     => 'required|integer|in:1,2',
            'inventory_audit_cycle_months' => 'required|integer|in:6,12',
            'inventory_audit_start_mode' => 'required|in:store_created_at,manual',
            'inventory_audit_start_date' => 'nullable|required_if:inventory_audit_start_mode,manual|date',
            'labor_description_options' => 'nullable|array|max:6',
            'labor_description_options.*' => 'nullable|string|max:100',
        ]);

        $user = auth()->user();

        // التحقق من الحصة (النشط + المحذوف)
        $totalUsed = $user->stores()->withTrashed()->count();
        $allowed   = $user->allowed_stores ?? ($user->plan->allowed_stores ?? 1);

        if ($totalUsed >= $allowed) {
            return redirect()->back()->with('error', 'لقد وصلت للحد الأقصى المسموح به في خطتك.');
        }

        // الحفظ الفعلي
        $user->stores()->create([
            'user_id'             => $user->id,
            'name'                => $request->name,
            'phone'               => $request->phone,
            'address'             => $request->address,
            // [تعديل آمن] توحيد اسم الحقل مع نماذج الإنشاء/التعديل والبطاقات.
            'commercial_registration' => $request->commercial_registration,
            'tax_number'          => $request->tax_number,
            'description'         => $request->description,
            'number_of_shifts'     => (int) $request->number_of_shifts,
            'inventory_audit_cycle_months' => (int) $request->inventory_audit_cycle_months,
            'inventory_audit_start_mode' => $request->inventory_audit_start_mode,
            'inventory_audit_start_date' => $request->inventory_audit_start_mode === 'manual'
                ? $request->inventory_audit_start_date
                : null,
            'labor_description_options' => $this->normalizeLaborDescriptionOptions($request->input('labor_description_options')),
            'logo'                => null,
            'status'              => 'active',
            'slug'                => Str::slug($request->name) . '-' . uniqid(),
            'expires_at'          => null,
        ]);

        return redirect()->route('user.stores.index')->with('success', 'تم إنشاء المتجر بنجاح مع كافة البيانات الضريبية.');
    }

    /**
     * عرض صفحة تعديل المتجر
     *
     * @param Store $store
     * @return \Illuminate\View\View
     */
    public function edit(Store $store)
    {
        // التأكد أن المالك هو من يحاول التعديل
        if ($store->user_id !== auth()->id()) {
            abort(403);
        }

        return view('user.stores.edit', compact('store'));
    }

    /**
     * تحديث بيانات المتجر
     *
     * @param Request $request
     * @param Store $store
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, Store $store)
    {
        // التحقق من الملكية
        if ($store->user_id !== auth()->id()) {
            abort(403);
        }

        // التحقق من البيانات
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'phone' => 'nullable|string',
            'address' => 'nullable|string',
            'tax_number' => 'nullable|string',
            'commercial_registration' => 'nullable|string',
            'bank_accounts' => 'nullable|string',
            'number_of_shifts' => 'required|integer|in:1,2',
            'inventory_audit_cycle_months' => 'required|integer|in:6,12',
            'inventory_audit_start_mode' => 'required|in:store_created_at,manual',
            'inventory_audit_start_date' => 'nullable|required_if:inventory_audit_start_mode,manual|date',
            'labor_description_options' => 'nullable|array|max:6',
            'labor_description_options.*' => 'nullable|string|max:100',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $validated['labor_description_options'] = $this->normalizeLaborDescriptionOptions($validated['labor_description_options'] ?? null);
        $validated['number_of_shifts'] = (int) $validated['number_of_shifts'];
        $validated['inventory_audit_cycle_months'] = (int) $validated['inventory_audit_cycle_months'];
        if ($validated['inventory_audit_start_mode'] !== 'manual') {
            $validated['inventory_audit_start_date'] = null;
        }
        $previousNumberOfShifts = (int) $store->number_of_shifts;

        // معالجة رفع الشعار
        if ($request->hasFile('logo')) {
            // حذف الشعار القديم
            if ($store->logo && file_exists(public_path('storage/' . $store->logo))) {
                @unlink(public_path('storage/' . $store->logo));
            }

            // تخزين الشعار الجديد
            $path = $request->file('logo')->store('stores/logos', 'public');
            $validated['logo'] = $path;
        }

        // تحديث البيانات
        $store->update($validated);

        app(ShiftSettingsHistoryService::class)->recordShiftCountChange(
            $store->fresh(),
            $previousNumberOfShifts,
            (int) $validated['number_of_shifts'],
            (int) auth()->id()
        );

        $this->normalizeLatestShiftDecisionAfterShiftCountChange(
            $store->fresh(),
            $previousNumberOfShifts,
            (int) $validated['number_of_shifts']
        );

        $redirectRoute = $request->input('return_to') === 'show'
            ? redirect()->route('user.stores.show', $store)
            : redirect()->route('user.stores.index');

        return $redirectRoute->with('success', 'تم تحديث بيانات المتجر بنجاح');
    }


    private function normalizeLaborDescriptionOptions(array|string|null $value): array
    {
        $lines = is_array($value) ? $value : (preg_split('/\R/u', (string) $value) ?: []);

        $options = array_values(array_slice(array_unique(array_filter(array_map(
            static fn ($line) => trim($line),
            $lines
        ))), 0, 6));

        return $options ?: ['تضليل', 'تجليد', 'شغل يد'];
    }

    /**
     * =================================================================
     * دوال العرض والصفحات (show, details)
     * =================================================================
     */

    /**
     * عرض الصفحة الرئيسية للمتجر (إحصائيات سريعة)
     *
     * @param Store $store
     * @return \Illuminate\View\View
     */
    public function show(Store $store)
    {
        // التحقق من ملكية المتجر
        if ($store->user_id !== auth()->id()) {
            abort(403);
        }

        $dashboardSummary = app(StoreDashboardService::class)->summary($store);
        $secondShiftRestoreCandidate = $this->secondShiftRestoreCandidate($store);
        $secondShiftRestoreBlocked = $secondShiftRestoreCandidate
            ? $this->hasOperationsAfterShiftClose($store->id, $secondShiftRestoreCandidate)
            : false;

        return view('user.stores.show', array_merge($dashboardSummary, [
            'store' => $store,
            'user' => auth()->user(),
            'secondShiftRestoreCandidate' => $secondShiftRestoreCandidate,
            'secondShiftRestoreBlocked' => $secondShiftRestoreBlocked,
        ]));
    }

    private function normalizeLatestShiftDecisionAfterShiftCountChange(Store $store, int $previousNumberOfShifts, int $newNumberOfShifts): void
    {
        if ($previousNumberOfShifts === $newNumberOfShifts) {
            return;
        }

        $lastBalance = DailyBalance::query()
            ->where('store_id', $store->id)
            ->whereNotNull('end_time')
            ->latest('end_time')
            ->first();

        if (! $lastBalance) {
            return;
        }

        $businessDate = $lastBalance->business_date
            ? $lastBalance->business_date->toDateString()
            : $lastBalance->created_at->toDateString();

        if ($newNumberOfShifts === 1 || ($previousNumberOfShifts < 2 && $newNumberOfShifts === 2)) {
            $lastBalance->update([
                'next_shift_business_date' => \Carbon\Carbon::parse($businessDate)->addDay()->toDateString(),
                'next_shift_decision' => 'next_business_date',
                'next_shift_decided_by' => null,
            ]);
        }
    }

    public function restoreSecondShift(Store $store)
    {
        if ($store->user_id !== auth()->id()) {
            abort(403);
        }

        $candidate = $this->secondShiftRestoreCandidate($store);

        if (! $candidate) {
            return redirect()->route('user.stores.show', $store)
                ->with('error', 'لا يوجد شفت ثاني قابل لإعادة التفعيل لهذا المتجر.');
        }

        if ($this->hasOperationsAfterShiftClose($store->id, $candidate)) {
            return redirect()->route('user.stores.show', $store)
                ->with('error', 'لا يمكن إعادة تفعيل الشفت الثاني بعد تسجيل عمليات لاحقة. راجع البيانات أولاً.');
        }

        $businessDate = $candidate->business_date
            ? $candidate->business_date->toDateString()
            : $candidate->created_at->toDateString();

        $candidate->update([
            'next_shift_business_date' => $businessDate,
            'next_shift_decision' => 'same_business_date',
            'next_shift_decided_by' => null,
            'notes' => trim(($candidate->notes ? $candidate->notes . "\n" : '') . 'تمت إعادة تفعيل الشفت الثاني بواسطة المالك: ' . auth()->user()->name),
        ]);

        return redirect()->route('user.stores.show', $store)
            ->with('success', 'تمت إعادة تفعيل الشفت الثاني لنفس التاريخ بنجاح.');
    }

    private function secondShiftRestoreCandidate(Store $store): ?DailyBalance
    {
        if ((int) $store->number_of_shifts < 2) {
            return null;
        }

        $lastBalance = DailyBalance::query()
            ->where('store_id', $store->id)
            ->whereNotNull('end_time')
            ->latest('end_time')
            ->first();

        if (! $lastBalance || $lastBalance->next_shift_decision !== 'next_business_date' || ! $lastBalance->next_shift_business_date) {
            return null;
        }

        return $lastBalance;
    }

    private function hasOperationsAfterShiftClose(int $storeId, DailyBalance $balance): bool
    {
        $closedAt = $balance->end_time;

        if (! $closedAt) {
            return true;
        }

        return Sale::where('store_id', $storeId)->where('created_at', '>', $closedAt)->exists()
            || Expense::where('store_id', $storeId)->where('created_at', '>', $closedAt)->exists()
            || Withdrawal::where('store_id', $storeId)->where('created_at', '>', $closedAt)->exists();
    }

    public function requestAccountantShiftInput(Request $request, Store $store)
    {
        $this->authorizeStoreAccess($store);

        if (! $this->storeAccessService()->isUsableForShiftWorkflow($store)) {
            return back()->with('error', 'المتجر غير مفعل ولا يدخل ضمن خدمات الشفتات.');
        }

        $validated = $request->validate([
            'business_date' => 'required|date',
            'accountant_id' => 'required|integer',
            'missing_shift_number' => 'nullable|integer|min:1|max:3',
        ]);

        $date = \Carbon\Carbon::parse($validated['business_date'])->toDateString();
        $accountant = app(ActiveAccountantService::class)->findActiveAccountantForStore(
            $store,
            auth()->user(),
            (int) $validated['accountant_id']
        );

        if (! $accountant) {
            return back()->with('error', 'يرجى اختيار محاسب فعال مرتبط بهذا المتجر.');
        }

        if (! in_array($date, app(ShiftLifecycleService::class)->missingBusinessDates($store->id), true)) {
            return back()->with('error', 'هذا التاريخ لم يعد ضمن قائمة الشفتات الناقصة.');
        }

        $shiftInfo = app(ShiftGapInfoService::class)->shiftInfo($store, $date);
        $requestedShiftNumber = (int) ($validated['missing_shift_number'] ?? $shiftInfo['missing_shift_number']);
        if ($requestedShiftNumber !== (int) $shiftInfo['missing_shift_number']) {
            return back()->with('error', 'رقم الشفت المطلوب لم يعد مطابقًا لحالة اليوم الحالية. يرجى تحديث الصفحة.');
        }

        if (app(ShiftGapRequestService::class)->activeStatus($store->id, $date, $requestedShiftNumber)) {
            return back()->with('info', 'تم إرسال هذا الشفت للمحاسب سابقًا، وهو بانتظار المعالجة.');
        }

        app(ShiftGapRequestService::class)->createOwnerRequest(
            $store,
            auth()->user(),
            $accountant,
            $date,
            $shiftInfo
        );

        return back()->with('success', 'تم تسجيل طلب إعادة اليوم للمحاسب في سجل العمليات.');
    }

    public function cancelAccountantShiftInputRequest(Request $request, Store $store)
    {
        $this->authorizeStoreAccess($store);

        if (! $this->storeAccessService()->isUsableForShiftWorkflow($store)) {
            return back()->with('error', 'المتجر غير مفعل ولا يدخل ضمن خدمات الشفتات.');
        }

        $validated = $request->validate([
            'business_date' => 'required|date',
            'missing_shift_number' => 'required|integer|min:1|max:3',
        ]);

        $businessDate = \Carbon\Carbon::parse($validated['business_date'])->toDateString();
        $missingShiftNumber = (int) $validated['missing_shift_number'];

        $wasCanceled = app(ShiftGapRequestService::class)->cancelOwnerRequest(
            $store,
            auth()->user(),
            $businessDate,
            $missingShiftNumber
        );

        if (! $wasCanceled) {
            return back()->with('info', 'لا يوجد طلب نشط لهذا الشفت حتى يتم إلغاؤه.');
        }

        return back()->with('success', 'تم إلغاء طلب المحاسب ويمكنك الآن إعادة إرساله لمحاسب آخر.');
    }

    public function reassignAccountantShiftInputRequest(Request $request, Store $store)
    {
        $this->authorizeStoreAccess($store);

        if (! $this->storeAccessService()->isUsableForShiftWorkflow($store)) {
            return back()->with('error', 'المتجر غير مفعل ولا يدخل ضمن خدمات الشفتات.');
        }

        $validated = $request->validate([
            'business_date' => 'required|date',
            'missing_shift_number' => 'required|integer|min:1|max:3',
            'accountant_id' => 'required|integer',
        ]);

        $businessDate = \Carbon\Carbon::parse($validated['business_date'])->toDateString();
        $missingShiftNumber = (int) $validated['missing_shift_number'];
        $newAccountant = app(ActiveAccountantService::class)->findActiveAccountantForStore(
            $store,
            auth()->user(),
            (int) $validated['accountant_id']
        );

        if (! $newAccountant) {
            return back()->with('error', 'يرجى اختيار محاسب فعال مرتبط بهذا المتجر.');
        }

        $wasReassigned = app(ShiftGapRequestService::class)->reassignOwnerRequest(
            $store,
            auth()->user(),
            $newAccountant,
            $businessDate,
            $missingShiftNumber
        );

        if (! $wasReassigned) {
            return back()->with('info', 'لا يوجد طلب نشط لهذا الشفت حتى تتم إعادة تعيينه.');
        }

        return back()->with('success', 'تمت إعادة تعيين طلب الشفت للمحاسب المختار.');
    }

    public function zeroCloseShiftGap(Request $request, Store $store)
    {
        $this->authorizeStoreAccess($store);

        if (! $this->storeAccessService()->isUsableForShiftWorkflow($store)) {
            return back()->with('error', 'المتجر غير مفعل ولا يدخل ضمن خدمات الشفتات.');
        }

        $validated = $request->validate([
            'business_date' => 'nullable|required_without:business_dates|date',
            'business_dates' => 'nullable|array',
            'business_dates.*' => 'date',
        ]);

        $dates = collect($validated['business_dates'] ?? [$validated['business_date']])
            ->filter()
            ->map(fn ($date) => \Carbon\Carbon::parse($date)->toDateString())
            ->unique()
            ->values();

        if ($dates->isEmpty()) {
            return back()->with('error', 'لم يتم تحديد أي يوم للإغلاق الصفري.');
        }

        $missingDates = app(ShiftLifecycleService::class)->missingBusinessDates($store->id);

        $accountantId = Accountant::where('store_id', $store->id)->value('id');
        if (! $accountantId) {
            return back()->with('error', 'لا يمكن إنشاء إغلاق صفري بدون وجود محاسب مرتبط بالمتجر.');
        }

        $closedDates = [];
        $operationClosedDates = [];
        $blockedDates = [];
        $ignoredDates = [];
        foreach ($dates as $date) {
            if (! in_array($date, $missingDates, true)) {
                $ignoredDates[] = $date;
                continue;
            }

            $operationCounts = app(ShiftGapOverviewService::class)->operationCounts($store, $date);
            if (($operationCounts['sales_count'] + $operationCounts['expenses_count'] + $operationCounts['withdrawals_count']) > 0) {
                // إغلاق المالك يعتمد بيانات اليوم المحدد سواء كان فارغًا أو يحتوي عمليات، ولا يوجد إغلاق آلي بدون طلب المالك.
                $salesQuery = Sale::where('store_id', $store->id)
                    ->forAccountingDate($date)
                    ->whereNull('daily_balance_id')
                    ->excludeManualInvoiceEntries();

                $totalSales = (float) (clone $salesQuery)->sum('paid_amount');
                $cashSales = (float) (clone $salesQuery)->where('sale_type', 'cash')->sum('paid_amount')
                    + (float) (clone $salesQuery)->where('sale_type', 'mixed')->sum('cash_amount');
                $expenses = (float) Expense::where('store_id', $store->id)
                    ->forAccountingDate($date)
                    ->whereNull('daily_balance_id')
                    ->sum('amount');
                $withdrawals = (float) Withdrawal::where('store_id', $store->id)
                    ->forAccountingDate($date)
                    ->whereNull('daily_balance_id')
                    ->sum('amount');
                $expectedCash = $cashSales - $expenses - $withdrawals;

                $dailyBalance = DailyBalance::create([
                    'store_id' => $store->id,
                    'accountant_id' => $accountantId,
                    'system_sales_total' => $totalSales,
                    'system_cash_expected' => $expectedCash,
                    'actual_cash_submitted' => $expectedCash,
                    'difference' => 0,
                    'start_time' => \Carbon\Carbon::parse($date)->startOfDay(),
                    'end_time' => \Carbon\Carbon::parse($date)->endOfDay(),
                    'business_date' => $date,
                    'closed_at' => now(),
                    'next_shift_business_date' => \Carbon\Carbon::parse($date)->addDay()->toDateString(),
                    'next_shift_decision' => 'next_business_date',
                    'next_shift_decided_by' => null,
                    'notes' => 'إغلاق مالك لشفت يحتوي عمليات بدون تقرير PDF: ' . auth()->user()->name,
                ]);

                app(ShiftOperationBinderService::class)->attachByBusinessDate(
                    $dailyBalance,
                    $date,
                    includeLegacyCreatedAtDate: true,
                    excludeManualInvoiceEntries: true
                );

                app(LogService::class)->add(
                    'shift_gap_owner_closed_with_operations',
                    'أغلق المالك شفتًا يحتوي عمليات بتاريخ ' . $date,
                    $dailyBalance,
                    ['business_date' => $date, 'store_id' => $store->id, 'operation_counts' => $operationCounts]
                );

                $closedDates[] = $date;
                $operationClosedDates[] = $date;
                continue;
            }

            $dailyBalance = DailyBalance::create([
                'store_id' => $store->id,
                'accountant_id' => $accountantId,
                'system_sales_total' => 0,
                'system_cash_expected' => 0,
                'actual_cash_submitted' => 0,
                'difference' => 0,
                'start_time' => \Carbon\Carbon::parse($date)->startOfDay(),
                'end_time' => \Carbon\Carbon::parse($date)->endOfDay(),
                'business_date' => $date,
                'closed_at' => now(),
                'next_shift_business_date' => \Carbon\Carbon::parse($date)->addDay()->toDateString(),
                'next_shift_decision' => 'next_business_date',
                'next_shift_decided_by' => null,
                'notes' => 'إغلاق صفري / إجازة بواسطة المالك: ' . auth()->user()->name,
            ]);

            app(LogService::class)->add(
                'shift_gap_zero_closed',
                'تم تحديد يوم كشف صفري / إجازة بتاريخ ' . $date,
                $dailyBalance,
                ['business_date' => $date, 'store_id' => $store->id]
            );

            $closedDates[] = $date;
        }

        if (empty($closedDates) && ! empty($blockedDates)) {
            return back()->with('error', 'لم يتم الإغلاق الصفري لأن الأيام المحددة تحتوي عمليات: ' . implode('، ', $blockedDates));
        }

        if (empty($closedDates)) {
            return back()->with('error', 'لم يتم إنشاء أي إغلاق صفري. الأيام المحددة لم تعد ضمن قائمة الشفتات الناقصة: ' . implode('، ', $ignoredDates));
        }

        $zeroClosedDates = array_values(array_diff($closedDates, $operationClosedDates));
        $messageParts = [];
        if (! empty($zeroClosedDates)) {
            $messageParts[] = 'تم إنشاء إغلاق صفري للأيام: ' . implode('، ', $zeroClosedDates);
        }
        if (! empty($operationClosedDates)) {
            $messageParts[] = 'تم إغلاق شفتات تحتوي عمليات واعتماد بياناتها للأيام: ' . implode('، ', $operationClosedDates);
        }
        $message = implode('، ', $messageParts);
        if (! empty($blockedDates)) {
            $message .= '، وتم تجاهل أيام تحتوي عمليات وتحتاج مراجعة: ' . implode('، ', $blockedDates);
        }

        return back()->with('success', $message);
    }

    public function shiftGaps(Store $store)
    {
        $this->authorizeStoreAccess($store);

        $overviewData = app(ShiftGapOverviewService::class)->ownerOverview($store, auth()->user());

        return view('user.stores.shift-gaps', array_merge(['store' => $store], $overviewData));
    }

    /**
     * عرض صفحة التفاصيل المتقدمة للمتجر
     * (إحصائيات شاملة: مخزون، موظفين، مبيعات، أرباح)
     *
     * @param int $storeId
     * @return \Illuminate\View\View
     */
    public function details($storeId)
    {
        $store = auth()->user()->stores()->findOrFail($storeId);
        $detailsData = app(StoreDetailsService::class)->build($store);

        return view('user.stores.details', $detailsData);
    }

    /**
     * =================================================================
     * دوال إدارة الحالة والإعدادات
     * =================================================================
     */

    /**
     * تعيين متجر كمتجر حالي للمستخدم
     *
     * @param Store $store
     * @return \Illuminate\Http\RedirectResponse
     */
    public function setCurrentStore(Store $store)
    {
        $this->authorizeStoreAccess($store);

        if (! $this->storeAccessService()->isActive($store)) {
            return back()->with('error', 'لا يمكن تعيين متجر معطل كمتجر حالي');
        }

        auth()->user()->update(['current_store_id' => $store->id]);

        // تسجيل العملية
        app(LogService::class)->add(
            action: 'set_current',
            description: 'تم تعيين المتجر كمتجر حالي',
            model: $store,
            details: ['name' => $store->name],
        );

        return back()->with('success', 'تم تعيين ' . $store->name . ' كمتجر حالي');
    }

    /**
     * تغيير حالة المتجر (تفعيل/تعطيل)
     *
     * @param Store $store
     * @return \Illuminate\Http\RedirectResponse
     */
    public function toggleStatus(Store $store)
    {
        $this->authorizeStoreAccess($store);

        // تبديل الحالة تلقائياً
        $oldStatus = $store->status;
        $newStatus = ($oldStatus === 'active') ? 'suspended' : 'active';

        $store->update([
            'status' => $newStatus,
            'suspension_reason' => $newStatus == 'suspended' ? 'تم الإيقاف بواسطة المالك' : null
        ]);

        // تسجيل العملية
        app(LogService::class)->add(
            action: 'status_change',
            description: 'تم تغيير حالة المتجر إلى ' . ($newStatus == 'active' ? 'نشط' : 'معطل'),
            model: $store,
            details: ['old_status' => $oldStatus, 'new_status' => $newStatus],
        );

        $message = $newStatus == 'active' ? 'تم تفعيل المتجر بنجاح' : 'تم إيقاف المتجر بنجاح';
        return back()->with('success', $message);
    }

    /**
     * =================================================================
     * دوال سلة المهملات والحذف
     * =================================================================
     */

    /**
     * حذف المتجر (نقل للسلة)
     *
     * @param Store $store
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(Store $store)
    {
        $this->authorizeStoreAccess($store);

        try {
            DB::transaction(function () use ($store): void {
                // تسجيل العملية قبل الحذف
                app(LogService::class)->add(
                    action: 'delete',
                    description: 'تم نقل المتجر إلى سلة المهملات',
                    model: $store,
                    details: ['name' => $store->name],
                );

                // لا نترك current_store_id يشير إلى متجر محذوف؛ ننتقل إلى متجر نشط آخر أو null.
                if ((int) $store->user->current_store_id === (int) $store->id) {
                    $replacementStoreId = Store::query()
                        ->where('user_id', $store->user_id)
                        ->where('id', '!=', $store->id)
                        ->where('status', 'active')
                        ->value('id');

                    $store->user->update(['current_store_id' => $replacementStoreId]);
                }

                // نوقف الدخول مؤقتًا فقط؛ يبقى ربط المحاسب حتى يمكن إعادته مع استعادة المتجر.
                $store->accountants()
                    ->where('status', 'active')
                    ->update([
                        'status' => 'suspended',
                        'suspension_reason' => Store::DELETED_STORE_ACCOUNTANT_SUSPENSION_REASON,
                    ]);

                $store->delete();
            });

            return redirect()->route('user.stores.index')
                ->with('success', 'تم نقل المتجر إلى سلة المهملات بنجاح');

        } catch (\Exception $e) {
            LaravelLog::error('Store soft deletion failed.', [
                'store_id' => $store->id,
                'user_id' => auth()->id(),
                'exception' => $e,
            ]);

            return redirect()->route('user.stores.show', $store)
                ->with('error', 'تعذر نقل المتجر إلى سلة المهملات. لم يتم حذف أي بيانات.');
        }
    }

    /**
     * عرض سلة المهملات (المتاجر المحذوفة)
     *
     * @return \Illuminate\View\View
     */
    public function trash()
    {
        $query = Store::onlyTrashed()->where('user_id', auth()->id());
        if (! app(SupportSessionService::class)->active()) {
            $query->whereNotIn('id', app(AdministrativeArchiveService::class)->archivedIds(Store::class));
        }
        $stores = $query->with('archivedItem')->latest()->get();

        return view('user.stores.trash', compact('stores'));
    }

    /**
     * استعادة متجر محذوف
     *
     * @param int $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function restore($id)
    {
        $user = auth()->user();
        $store = Store::onlyTrashed()
            ->where('user_id', $user->id)
            ->findOrFail($id);

        $archive = app(AdministrativeArchiveService::class)->activeFor($store, $store->id);
        if ($archive) {
            abort_unless(app(SupportSessionService::class)->active(), 404);
            $slugConflict = Store::withTrashed()
                ->where('id', '!=', $store->id)
                ->where('slug', $archive->original_slug)
                ->exists();
            if ($slugConflict) {
                $archive->update(['admin_message' => 'تعذرت استعادة المتجر لوجود متجر يستخدم الرابط الأصلي.']);
                return back()->with('error', $archive->admin_message);
            }
        }

        // التحقق من الخطة
        $allowed = $user->plan_id ? $user->plan->allowed_stores : ($user->allowed_stores ?? 1);
        $activeCount = $user->stores()->count();

        if (! app(SupportSessionService::class)->active() && $activeCount >= $allowed) {
            return redirect()->route('user.stores.trash')
                ->with('error', 'لا يمكنك استعادة المتجر لأنك وصلت للحد الأقصى المسموح به في خطتك (' . $allowed . ') متجر.');
        }

        DB::transaction(function () use ($store, $archive): void {
            $lockedStore = Store::withTrashed()->lockForUpdate()->findOrFail($store->id);
            if ($archive) {
                $lockedArchive = ArchivedItem::lockForUpdate()->findOrFail($archive->id);
                $lockedStore->name = $lockedArchive->original_name ?: $lockedStore->name;
                $lockedStore->slug = $lockedArchive->original_slug ?: $lockedStore->slug;
            }
            $lockedStore->restore();

            // نعيد فقط الحسابات التي أوقفها حذف المتجر؛ ولا نفعّل حسابًا كان موقوفًا لسبب آخر.
            $lockedStore->accountants()
                ->where('suspension_reason', Store::DELETED_STORE_ACCOUNTANT_SUSPENSION_REASON)
                ->update([
                    'status' => 'active',
                    'suspension_reason' => null,
                ]);

            if ($archive) {
                $lockedArchive->update([
                    'status' => 'restored', 'restored_at' => now(),
                    'restored_by' => app(SupportSessionService::class)->active()?->admin_id,
                    'admin_message' => 'تمت استعادة المتجر بواسطة الدعم التقني.',
                ]);
            }
        });

        return redirect()->route('user.stores.trash')
            ->with('success', 'تم استعادة المتجر بنجاح');
    }

    /**
     * حذف المتجر نهائياً من قاعدة البيانات
     *
     * @param int $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function forceDelete($id)
    {
        $store = Store::onlyTrashed()
            ->where('user_id', auth()->id())
            ->findOrFail($id);
        $archiveService = app(AdministrativeArchiveService::class);
        $archive = $archiveService->activeFor($store, $store->id);

        if (! app(SupportSessionService::class)->active() || ! $archive) {
            $archiveService->archive($store, $store->user_id, $store->id, $store->name, $store->slug);
            return back()->with('success', 'تم حذف المتجر نهائيًا من حسابك. يمكن طلب استعادته من الدعم التقني خلال 30 يومًا.');
        }

        $blockers = $this->storePermanentDeleteBlockers($store);
        if ($blockers !== []) {
            $archive->update(['admin_message' => 'تعذر الحذف الفعلي لارتباط المتجر بـ ' . implode('، ', $blockers)]);
            return back()->with('error', $archive->admin_message);
        }

        try {
            DB::transaction(function () use ($id, $archive): void {
                $store = Store::onlyTrashed()
                    ->where('user_id', auth()->id())
                    ->lockForUpdate()
                    ->findOrFail($id);

                // نسجل الحذف النهائي قبل فصل سجلات التدقيق عن المتجر داخل حدث deleting.
                app(LogService::class)->add(
                    action: 'force_delete',
                    description: 'تم حذف المتجر نهائيًا',
                    model: $store,
                    details: ['name' => $store->name],
                );

                $store->forceDelete();
                ArchivedItem::lockForUpdate()->findOrFail($archive->id)->update([
                    'status' => 'purged', 'admin_message' => 'حُذف المتجر الفارغ فعلياً بواسطة الدعم التقني.',
                ]);
            });
        } catch (\Exception $e) {
            LaravelLog::error('Store force deletion failed.', [
                'store_id' => $id,
                'user_id' => auth()->id(),
                'exception' => $e,
            ]);

            return redirect()->route('user.stores.trash')
                ->with('error', 'تعذر حذف المتجر نهائيًا. لم يتم حذف أي بيانات.');
        }

        return redirect()->route('user.stores.trash')
            ->with('success', 'تم حذف المتجر نهائياً');
    }

    private function storePermanentDeleteBlockers(Store $store): array
    {
        $checks = [
            'المنتجات' => Product::withTrashed()->where('store_id', $store->id)->exists(),
            'الأقسام' => \App\Models\Category::withTrashed()->where('store_id', $store->id)->exists(),
            'الموظفون' => \App\Models\Employee::withTrashed()->where('store_id', $store->id)->exists(),
            'المحاسبون' => Accountant::withTrashed()->where('store_id', $store->id)->exists(),
            'المبيعات' => Sale::where('store_id', $store->id)->exists(),
            'المشتريات' => Purchase::where('store_id', $store->id)->exists(),
            'المصروفات' => Expense::where('store_id', $store->id)->exists(),
            'السحوبات' => Withdrawal::where('store_id', $store->id)->exists(),
        ];

        foreach ([
            'credit_sales' => 'المبيعات الآجلة',
            'employee_credit_collections' => 'تحصيلات الآجل',
            'employee_absences' => 'الغيابات',
            'employee_withdrawals' => 'سحوبات الموظفين',
            'debts' => 'المديونيات',
            'employee_salary_reports' => 'تقارير الرواتب',
            'inventory_logs' => 'سجلات الجرد',
            'stock_movements' => 'حركات المخزون',
            'store_purchase_orders' => 'طلبيات التوريد',
            'store_transfers' => 'النقل المخزني',
            'daily_balances' => 'إقفالات الشفتات',
        ] as $table => $label) {
            if (Schema::hasTable($table)
                && Schema::hasColumn($table, 'store_id')
                && DB::table($table)->where('store_id', $store->id)->exists()) {
                $checks[$label] = true;
            }
        }

        return array_keys(array_filter($checks));
    }

    /**
     * صفحة مركز التقارير للمتجر
     */
    public function reportsIndex(Store $store)
    {
        $this->authorizeStoreAccess($store);

        return view('user.stores.reports.index', compact('store'));
    }


    /**
     * تقرير بحث شامل للمتجر يجمع المبيعات والاستهلاك الداخلي ومشتريات المالك.
     */
    public function reportsComprehensiveSearch(Store $store, Request $request)
    {
        $this->authorizeStoreAccess($store);

        $validated = $request->validate([
            'q' => 'nullable|string|max:255',
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
            'scope' => 'nullable|in:all,sales,withdrawals,debts,credit_sales,debt_collections,credit_collections,expenses,absences,internal,purchases,products',
        ]);

        $reportData = app(ComprehensiveStoreSearchReportService::class)->build($store, $validated);

        return view('user.stores.reports.comprehensive-search', $reportData);
    }

    /**
     * تقارير مبيعات آخر 10 أيام (ملفات PDF المولدة للإقفال)
     */
    public function reportsLastTenDays(Store $store)
    {
        $this->authorizeStoreAccess($store);

        $reportFilesData = app(RecentReportFilesService::class)->recentForStore($store, 10);

        return view('user.stores.reports.last-ten-days', array_merge(['store' => $store], $reportFilesData));
    }


    private function buildEmployeesMonthlyReportData(Store $store, Request $request): array
    {
        $month = $request->get('month', now()->format('Y-m'));
        $start = \Carbon\Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $end = $start->copy()->endOfMonth();
        $rows = app(\App\Services\Employees\EmployeePayrollService::class)
            ->monthlyRowsForStore($store->id, $month, $start, $end);

        $employeeIds = $rows->pluck('id')->map(fn ($id) => (int) $id)->filter()->values();
        $creditSales = \App\Models\CreditSale::query()
            ->where('store_id', $store->id)
            ->where('person_type', \App\Models\Employee::class)
            ->whereIn('person_id', $employeeIds)
            ->betweenOperationDates($start, $end)
            ->with('person:id,name')
            ->get(['id', 'person_id', 'person_type', 'amount', 'remaining_amount', 'date', 'description', 'status']);

        $creditCollectionsByEmployee = DB::table('employee_credit_collections')
            ->where('store_id', $store->id)
            ->where('person_type', \App\Models\Employee::class)
            ->whereIn('person_id', $employeeIds)
            ->whereBetween('collection_date', [$start->toDateString(), $end->toDateString()])
            ->select('person_id', DB::raw('COALESCE(SUM(amount), 0) as aggregate'))
            ->groupBy('person_id')
            ->pluck('aggregate', 'person_id');

        $debts = \App\Models\Debt::query()
            ->where('store_id', $store->id)
            ->where('person_type', \App\Models\Employee::class)
            ->whereIn('person_id', $employeeIds)
            ->betweenOperationDates($start, $end)
            ->with(['person:id,name', 'addedBy:id,name'])
            ->get(['id', 'person_id', 'person_type', 'debt_parent_id', 'amount', 'date', 'description', 'payment_method_label', 'cash_amount', 'card_amount', 'status', 'added_by', 'created_at']);

        $debtCollectionLogs = \App\Models\EmployeeLog::query()
            ->where('store_id', $store->id)
            ->where('person_type', \App\Models\Employee::class)
            ->whereIn('person_id', $employeeIds)
            ->whereIn('action_name', ['debt_collect_full', 'debt_collect_partial'])
            ->whereBetween('created_at', [$start, $end])
            ->get(['person_id', 'amount', 'meta', 'created_at']);

        $absences = \App\Models\Absence::query()
            ->where('store_id', $store->id)
            ->where('person_type', \App\Models\Employee::class)
            ->whereIn('person_id', $employeeIds)
            ->betweenOperationDates($start, $end)
            ->with('person:id,name')
            ->get(['person_id', 'person_type', 'date', 'penalty_amount', 'description']);

        $logs = \App\Models\EmployeeLog::query()
            ->where('store_id', $store->id)
            ->where('person_type', \App\Models\Employee::class)
            ->whereIn('person_id', $employeeIds)
            ->whereIn('action_name', ['employee_transferred', 'salary_update'])
            ->whereBetween('created_at', [$start, $end])
            ->with('person:id,name')
            ->get(['person_id', 'person_type', 'action_name', 'description', 'meta', 'created_at']);

        $rows = $rows->map(function (array $row) use ($creditSales, $creditCollectionsByEmployee, $debts, $debtCollectionLogs, $logs, $start, $end) {
            $employeeId = (int) $row['id'];
            $employeeCreditSales = $creditSales->where('person_id', $employeeId);
            $employeeDebts = $debts->where('person_id', $employeeId);
            $employeeLogs = $logs->where('person_id', $employeeId);

            $creditCollections = (float) ($creditCollectionsByEmployee[$employeeId] ?? 0);

            $row['credit_sales'] = (float) $employeeCreditSales->sum('amount');
            $row['credit_collections'] = $creditCollections;
            $row['debt_collections'] = abs((float) $employeeDebts->where('amount', '<', 0)->sum('amount'));
            $row['debt_collection_rows'] = $employeeDebts
                ->where('amount', '<', 0)
                ->sortByDesc('date')
                ->values()
                ->map(function ($debt) use ($debtCollectionLogs, $employeeId) {
                    $amount = abs((float) $debt->amount);
                    $operationDate = optional($debt->date ?? $debt->created_at)->format('Y-m-d');
                    $matchedLog = $debtCollectionLogs->first(function ($log) use ($employeeId, $amount, $operationDate) {
                        $logOperationDate = data_get($log->meta, 'operation_date') ?: optional($log->created_at)->format('Y-m-d');

                        return (int) $log->person_id === $employeeId
                            && abs(abs((float) ($log->amount ?? 0)) - $amount) < 0.01
                            && $logOperationDate === $operationDate;
                    });

                    return [
                        'id' => (int) $debt->id,
                        'parent_id' => $debt->debt_parent_id ? (int) $debt->debt_parent_id : null,
                        'amount' => $amount,
                        'date' => $operationDate,
                        'description' => $debt->description ?: 'تحصيل مديونية',
                        'collector' => data_get($matchedLog?->meta, 'actor_name')
                            ?: $debt->addedBy?->name
                            ?: 'غير محدد',
                        'payment_method_label' => $debt->payment_method_label ?: data_get($matchedLog?->meta, 'payment_method_label', 'كاش'),
                        'cash_amount' => (float) ($debt->cash_amount ?? data_get($matchedLog?->meta, 'cash_amount', 0)),
                        'card_amount' => (float) ($debt->card_amount ?? data_get($matchedLog?->meta, 'card_amount', 0)),
                    ];
                })
                ->all();
            $row['transfers_count'] = (int) $employeeLogs->where('action_name', 'employee_transferred')->count();
            $row['salary_changes_count'] = (int) $employeeLogs->where('action_name', 'salary_update')->count();
            $row['changes_summary'] = $employeeLogs->map(fn ($log) => $log->description)->filter()->implode(' | ');

            return $row;
        });

        $absenceRows = $absences->filter(fn ($absence) => (float) ($absence->penalty_amount ?? 0) > 0)
            ->sortByDesc('date')
            ->values();
        $debtRows = $debts->filter(fn ($debt) => (float) ($debt->amount ?? 0) > 0)
            ->sortByDesc('date')
            ->values();
        $creditRows = $creditSales->filter(fn ($creditSale) => (float) ($creditSale->amount ?? 0) > 0)
            ->sortByDesc('date')
            ->values();
        $operationRows = $logs->sortByDesc('created_at')->values();

        $totals = [
            'salary' => (float) $rows->sum('salary'),
            'withdrawals' => (float) $rows->sum('withdrawals'),
            'debts' => (float) $rows->sum('debts'),
            'credit_sales' => (float) $rows->sum('credit_sales'),
            'debt_collections' => (float) $rows->sum('debt_collections'),
            'credit_collections' => (float) $rows->sum('credit_collections'),
            'absences_count' => (int) $rows->sum('absences_count'),
            'absence_penalty' => (float) $rows->sum('absence_penalty'),
            'worked_days' => (int) $rows->sum('worked_days'),
            'net_salary' => (float) $rows->sum('net_salary'),
        ];

        return compact('store', 'month', 'start', 'end', 'rows', 'totals', 'absenceRows', 'debtRows', 'creditRows', 'operationRows');
    }

    public function reportsEmployeesMonthly(Store $store, Request $request)
    {
        $this->authorizeStoreAccess($store);

        return view('user.stores.reports.employees-monthly', $this->buildEmployeesMonthlyReportData($store, $request));
    }

    public function reportsEmployeesMonthlyPdf(Store $store, Request $request)
    {
        $this->authorizeStoreAccess($store);

        $data = $this->buildEmployeesMonthlyReportData($store, $request);
        $title = 'تقرير_الموظفين_' . str_replace('-', '_', $data['month']) . '_' . $store->id;
        $pdf = PDF::loadView('pdf.store-employees-monthly-report', $data)
            ->setPaper('a4');

        return $pdf->download($title . '.pdf');
    }

    /**
     * التقرير الشهري للمتجر (واجهة)
     */
    public function reportsMonthly(Store $store, Request $request)
    {
        $this->authorizeStoreAccess($store);

        $month = $request->get('month', now()->format('Y-m'));
        $start = \Carbon\Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $end = $start->copy()->endOfMonth();
        $data = app(MonthlyStoreReportService::class)->buildMonthlyReportData($store, $month, $start, $end, false);

        return view('user.stores.reports.monthly', $data);
    }

    /**
     * تصدير PDF للتقرير الشهري
     */
    public function reportsMonthlyPdf(Store $store, Request $request)
    {
        $this->authorizeStoreAccess($store);

        $month = $request->get('month', now()->format('Y-m'));
        $start = \Carbon\Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $end = $start->copy()->endOfMonth();
        $includeSalesDetails = $request->boolean('include_sales_details');
        $data = app(MonthlyStoreReportService::class)->buildMonthlyReportData($store, $month, $start, $end, $includeSalesDetails);
        $data['includeSalesDetails'] = $includeSalesDetails;

        $reportTitle = app(MonthlyStoreReportService::class)->buildMonthlyReportTitle($store->name, $month, $includeSalesDetails);
        $data['reportTitle'] = $reportTitle;

        $pdf = PDF::loadView('pdf.store-monthly-report', $data)
            ->setOption('encoding', 'utf-8');

        return $pdf->download(app(MonthlyStoreReportService::class)->buildSafeReportFileName($reportTitle, $store->id));
    }

    /**
     * =================================================================
     * دوال مساعدة و API
     * =================================================================
     */

    /**
     * واجهة توافقية داخلية للتحقق من أن المتجر المطلوب تابع للمالك الحالي.
     *
     * مكانها الأساسي الآن: StoreAccessService::ensureOwnerCanAccess.
     * خطة الحذف: عند نقل صلاحيات المتاجر إلى Policy/StoreAccessService في كل الكنترولرات،
     * تُحذف هذه الدالة وتستدعى الخدمة أو الـ Policy مباشرة من نقاط الدخول الجديدة.
     */
    private function authorizeStoreAccess(Store $store)
    {
        $this->storeAccessService()->ensureOwnerCanAccess(auth()->user(), $store);
    }

    /**
     * نقطة وصول موحدة لخدمة صلاحيات واستخدام المتاجر حتى لا تتكرر شروط الملكية والحالة داخل الكنترولر.
     */
    private function storeAccessService(): StoreAccessService
    {
        return app(StoreAccessService::class);
    }

    /**
     * الحصول على إحصائيات متقدمة للمتجر (API)
     *
     * @param Store $store
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAdvancedStats(Store $store)
    {
        $this->authorizeStoreAccess($store);

        return response()->json(app(StoreDashboardService::class)->advancedStats($store));
    }
}
