<?php

namespace App\Http\Controllers\Accountant;

use Carbon\Carbon;
use App\Models\Log;
use App\Models\Sale;
use App\Models\Store;
use App\Models\Expense;
use App\Models\Accountant;
use App\Models\Withdrawal;
use App\Models\DailyBalance;
use App\Models\Notification;
use App\Models\EmployeeLog;
use App\Models\CreditSale;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use App\Support\ArabicPdf as PDF;
use App\Services\ShiftLifecycleService;
use App\Services\Accounting\AccountingOperationFeedService;
use App\Services\Shifts\ShiftGapInfoService;
use App\Services\Shifts\ShiftOperationBinderService;
use App\Modules\PurchaseOrders\Models\StorePurchaseOrder;

class DashboardController extends Controller
{
    public function index()
    {
        $accountant = auth('accountant')->user();
        $storeId = $accountant->store_id;
        $pendingIncomingTransfersCount = \App\Models\StoreTransfer::where('receiver_store_id', $storeId)->where('status', 'pending')->count();
        $pendingOutgoingTransfersCount = \App\Models\StoreTransfer::where('sender_store_id', $storeId)
            ->pendingForReview()
            ->count();
        $pendingPurchaseOrderAlerts = StorePurchaseOrder::with(['items:id,store_purchase_order_id,inventory_count_attempt'])->where('store_id', $storeId)
            ->where(fn ($query) => $query->where('accountant_id', $accountant->id)->orWhereNull('accountant_id'))
            ->whereIn('workflow_status', ['returned_for_edit', 'returned_for_count', 'pending_receipt_confirmation'])
            ->latest('updated_at')
            ->limit(5)
            ->get();
        $lastBalance = null;

        try {
            // 1. البحث عن آخر إقفال يدوي مسجل
            $lastBalance = DailyBalance::where('store_id', $storeId)
                ->with(['accountant'])
                ->latest()
                ->first();

            if ($lastBalance) {
                $startTime = $lastBalance->end_time ?? $lastBalance->created_at;
                $lastBalanceTime = $lastBalance->end_time
                    ? $lastBalance->end_time->format('Y-m-d h:i A')
                    : $lastBalance->created_at->format('Y-m-d h:i A');
                $lastBalanceAmount = $lastBalance->system_sales_total;
                $lastBalanceAccountant = optional($lastBalance->accountant)->name ?? 'غير معروف';
            } else {
                $startTime = Carbon::parse('2024-01-01');
                $lastBalanceTime = 'بانتظار أول إقفال';
                $lastBalanceAmount = 0;
                $lastBalanceAccountant = '--';
            }

            $shiftLifecycleContext = app(ShiftLifecycleService::class)->currentShiftContext($accountant->store ?: $storeId, now(), true);
            $currentBusinessDate = $shiftLifecycleContext['business_date'];
            $currentShiftNumber = $shiftLifecycleContext['shift_number'];
            $requiresSecondShiftConfirmation = $shiftLifecycleContext['requires_second_shift_confirmation'];
            $canChooseNextShiftBusinessDate = $shiftLifecycleContext['can_choose_next_shift_business_date'];
            $nextBusinessDateAfterCurrent = Carbon::parse($currentBusinessDate)->addDay()->toDateString();
            $missingBusinessDates = $shiftLifecycleContext['missing_business_dates'];
            $this->ensurePreviousDayShiftRequest($storeId, (int) $accountant->id, (string) $accountant->name, $missingBusinessDates);
            $pendingShiftGapRequests = $this->pendingShiftGapRequests($storeId, $missingBusinessDates, (int) $accountant->id);
            $activeShiftGapBusinessDate = session('accountant_shift_gap_business_date');
            $activeBusinessDate = $activeShiftGapBusinessDate ?: $currentBusinessDate;
            $isShiftGapProcessing = ! empty($activeShiftGapBusinessDate);
            $startTime = Carbon::parse($shiftLifecycleContext['shift_start']);

            $shiftDuration = $startTime->diffInHours(now());
            $shiftDurationText = $this->formatShiftDuration($shiftDuration);

            // تحسين: استعلام واحد لجمع إحصائيات المبيعات
            $salesStats = $this->getSalesStatistics($storeId, $startTime, $activeBusinessDate);

            $totalSinceBalance = $salesStats['total_sales'];
            $totalCost = (float) ($salesStats['total_cost'] ?? 0);
            $cashSales = $salesStats['cash_sales'];
            $mixedSales = (float) ($salesStats['mixed_sales'] ?? 0);
            $cardSales = $salesStats['card_sales'];
            $officialCreditSales = $salesStats['official_credit_sales'];
            $paymentGaps = $salesStats['payment_gaps'];
            $pendingCreditTotal = $officialCreditSales + $paymentGaps;

            $currentShiftExpenses = Expense::where('store_id', $storeId)
                ->forOpenAccountingShift($activeBusinessDate, $startTime)
                ->sum('amount');

            $currentShiftWithdrawals = Withdrawal::where('store_id', $storeId)
                ->forOpenAccountingShift($activeBusinessDate, $startTime)
                ->sum('amount');

            $cashFromCollectionsResult = $isShiftGapProcessing
                ? $this->emptyCreditCollections()
                : $this->getCreditCollections($storeId, $startTime, now(), $activeBusinessDate);
            $accountantFinanceMovements = $this->getAccountantFinanceMovements(
                $storeId,
                $startTime,
                now(),
                $activeBusinessDate
            );
            // تحصيلات المديونية/الآجل التي قام بها المحاسب تُفصل إلى كاش/شبكة حسب طريقة التحصيل.
            $cashFromCollections = $accountantFinanceMovements['collections_cash_total'] ?? ($accountantFinanceMovements['collections_total'] ?? 0);
            $cardFromCollections = (float) ($accountantFinanceMovements['collections_card_total'] ?? 0);
            $totalCollectionsReceived = (float) ($accountantFinanceMovements['collections_total'] ?? ($cashFromCollections + $cardFromCollections));
            $totalSinceBalance += $totalCollectionsReceived;
            $cardSales += $cardFromCollections;
            $collectedFromCurrentPeriod = $cashFromCollectionsResult['from_current_period'] ?? 0;
            $collectedFromOldPeriod = $cashFromCollectionsResult['from_old_period'] ?? 0;

            // ✅ التصحيح: جميع التحصيلات تضاف للكاش
            $cashInSafe = ($cashSales + $cashFromCollections) - ($currentShiftExpenses + $currentShiftWithdrawals);
            $totalCashInShift = $cashSales + $cashFromCollections;

            // إحصائيات الشهر
            $startOfMonth = $activeBusinessDate
                ? Carbon::parse($activeBusinessDate)->startOfMonth()
                : now()->startOfMonth();
            $stats = $isShiftGapProcessing
                ? ['monthly_withdrawals' => 0, 'monthly_expenses' => 0]
                : [
                    'monthly_withdrawals' => Withdrawal::where('store_id', $storeId)
                        ->when($activeBusinessDate,
                            fn ($query) => $query->whereBetween('business_date', [$startOfMonth->toDateString(), $startOfMonth->copy()->endOfMonth()->toDateString()]),
                            fn ($query) => $query->where('created_at', '>=', $startOfMonth)
                        )
                        ->sum('amount'),
                    'monthly_expenses' => Expense::where('store_id', $storeId)
                        ->when($activeBusinessDate,
                            fn ($query) => $query->whereBetween('business_date', [$startOfMonth->toDateString(), $startOfMonth->copy()->endOfMonth()->toDateString()]),
                            fn ($query) => $query->where('created_at', '>=', $startOfMonth)
                        )
                        ->sum('amount'),
                ];

            $workingDays = $isShiftGapProcessing
                ? 0
                : DailyBalance::where('store_id', $storeId)
                    ->whereMonth('created_at', now()->month)
                    ->count();
            $dailyAverage = $workingDays > 0 ? ($totalSinceBalance / $workingDays) : 0;

            $lowStockProducts = \App\Models\Product::where('store_id', $storeId)
                ->whereColumn('quantity', '<=', 'min_stock')
                ->take(5)
                ->get();
            $lowStockProductsCount = $lowStockProducts->count();

            $pendingCollections = DB::table('credit_sales')
                ->where('store_id', $storeId)
                ->where('remaining_amount', '>', 0)
                ->where('status', 'pending')
                ->count();

            // سجل آخر العمليات مستقل عن الشفت/اليوم المرجع؛ يعرض آخر 10 عمليات حسب التاريخ دائمًا.
            $lastOperations = app(AccountingOperationFeedService::class)->latestForStore(
                $storeId,
                10,
                max(1, (int) request('operations_page', 1)),
                request()->url(),
                request()->query()
            );

            $shiftStatus = ($shiftDuration > 15) ? 'warning' : 'normal';
            $shiftStatusClass = ($shiftDuration > 15) ? 'warning' : 'success';
            $shiftStatusMessage = ($shiftDuration > 15)
                ? 'لم يتم إغلاق الحسابات منذ فترة طويلة'
                : '';

            $salesEfficiency = $lastBalanceAmount > 0
                ? (($totalSinceBalance - $lastBalanceAmount) / $lastBalanceAmount) * 100
                : 0;

            $quickStats = $this->getDashboardQuickStats($storeId, $startTime, $activeBusinessDate);

            $pendingCreditCount = Sale::where('store_id', $storeId)
                ->forOpenAccountingShift($activeBusinessDate, $startTime)
                ->where('remaining_amount', '>', 0)
                ->count();

            // تحصيلات البيع الآجل
            $creditCollections = $isShiftGapProcessing
                ? $this->emptyCreditCollections()
                : array_merge($this->getCreditCollections($storeId, $startTime, now(), $activeBusinessDate), [
                    'total' => $accountantFinanceMovements['collections_total'] ?? 0,
                    'details' => $accountantFinanceMovements['collection_rows'] ?? [],
                    'count' => count($accountantFinanceMovements['collection_rows'] ?? []),
                ]);
            $shiftOperationDetails = app(AccountingOperationFeedService::class)->shiftDetails($storeId, $startTime, $creditCollections, $activeBusinessDate);

            // تنظيف المتغيرات الكبيرة بعد استخدامها
            unset($salesStats);

            return view('dashboard.accountant.index', compact(
                'totalSinceBalance', 'currentShiftExpenses', 'currentShiftWithdrawals', 'stats',
                'lastOperations', 'startTime', 'lastBalanceTime', 'lastBalanceAmount', 'lastBalanceAccountant',
                'shiftDuration', 'shiftDurationText', 'workingDays', 'dailyAverage', 'cashInSafe',
                'lowStockProducts', 'lowStockProductsCount', 'pendingCreditTotal', 'officialCreditSales',
                'paymentGaps', 'pendingCollections', 'shiftStatus', 'shiftStatusClass',
                'shiftStatusMessage', 'salesEfficiency', 'cashFromCollections', 'cashSales', 'mixedSales', 'totalCost',
                'cardSales', 'totalCashInShift', 'quickStats', 'pendingCreditCount', 'lastBalance', 'accountant',
                'creditCollections', 'collectedFromCurrentPeriod', 'collectedFromOldPeriod',
                'shiftOperationDetails', 'pendingIncomingTransfersCount', 'pendingOutgoingTransfersCount',
                'shiftLifecycleContext', 'currentBusinessDate', 'currentShiftNumber',
                'requiresSecondShiftConfirmation', 'canChooseNextShiftBusinessDate',
                'nextBusinessDateAfterCurrent', 'missingBusinessDates',
                'pendingShiftGapRequests', 'activeShiftGapBusinessDate', 'isShiftGapProcessing',
                'accountantFinanceMovements', 'pendingPurchaseOrderAlerts'
            ));

        } catch (\Exception $e) {
            \Log::error('Dashboard error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return redirect()->back()->with('error', 'حدث خطأ في تحميل البيانات');
        }
    }


    public function activateShiftGap(Log $log)
    {
        $accountant = auth('accountant')->user();

        if ((int) $log->store_id !== (int) $accountant->store_id || $log->action !== 'shift_gap_accountant_request') {
            abort(403);
        }

        $assignedAccountantId = data_get($log->details, 'accountant_id');
        if ($assignedAccountantId && (int) $assignedAccountantId !== (int) $accountant->id) {
            abort(403);
        }

        $businessDate = data_get($log->details, 'business_date');
        if (! $businessDate) {
            return back()->with('error', 'طلب الشفت لا يحتوي تاريخًا صالحًا.');
        }

        $businessDate = Carbon::parse($businessDate)->toDateString();
        $details = is_array($log->details) ? $log->details : [];
        $activeSessionDate = session('accountant_shift_gap_business_date');
        if ($activeSessionDate && Carbon::parse($activeSessionDate)->toDateString() !== $businessDate) {
            return back()->with('error', 'يوجد يوم مرجع مفعل بالفعل. أغلقه أو أجله قبل فتح طلب مرجع آخر.');
        }

        $missingDates = app(ShiftLifecycleService::class)->missingBusinessDates((int) $accountant->store_id);
        $isDeferredShift = $this->isDeferredOrResumableShiftGap($details);
        $deferredMaxShifts = max((int) data_get($details, 'max_shifts', 0), app(ShiftLifecycleService::class)->maxShiftsPerBusinessDate($accountant->store));
        $canActivateDeferredShift = $isDeferredShift && $this->closedShiftsCount($accountant->store, $businessDate) < $deferredMaxShifts;

        if (! in_array($businessDate, $missingDates, true) && ! $canActivateDeferredShift) {
            return back()->with('error', 'هذا اليوم لم يعد ضمن الشفتات الناقصة أو الشفتات المؤجلة.');
        }

        session([
            'accountant_shift_gap_store_id' => (int) $accountant->store_id,
            'accountant_shift_gap_business_date' => $businessDate,
            'accountant_shift_gap_log_id' => (int) $log->id,
        ]);

        $details['status'] = 'in_progress';
        $details['accountant_id'] = (int) $accountant->id;
        $details['accountant_started_at'] = now()->toDateTimeString();
        $log->update(['details' => $details]);

        return redirect()->route('accountant.dashboard')
            ->with('success', 'تم تفعيل يوم '.$businessDate.' للإدخال. العمليات الجديدة ستسجل على هذا التاريخ حتى إنهاء وضع المعالجة.');
    }


    public function closeActiveShiftGap(Request $request)
    {
        $accountant = auth('accountant')->user();
        $store = $accountant->store;

        if (! $store) {
            return back()->with('error', 'المحاسب غير مرتبط بأي متجر.');
        }

        $businessDate = session('accountant_shift_gap_business_date');
        if (! $businessDate || (int) session('accountant_shift_gap_store_id') !== (int) $store->id) {
            return back()->with('error', 'لا يوجد يوم مرجع مفعل للإغلاق.');
        }

        $validated = $request->validate([
            'actual_cash' => 'required|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
            'include_debt_adjustments' => 'nullable|array',
            'include_debt_adjustments.*' => 'integer',
        ]);

        $businessDate = Carbon::parse($businessDate)->toDateString();

        $activeGapLog = $this->activeShiftGapLog($store, $businessDate);
        $configuredMaxShifts = app(ShiftLifecycleService::class)->maxShiftsPerBusinessDate($store);
        $requiredShifts = max((int) data_get($activeGapLog?->details, 'max_shifts', 0), $configuredMaxShifts);
        if ($this->closedShiftsCount($store, $businessDate) >= $requiredShifts) {
            $this->clearShiftGapSession();

            return redirect()->route('accountant.dashboard')
                ->with('error', 'اكتملت كل شفتات هذا التاريخ بالفعل. تم إنهاء وضع المعالجة.');
        }

        DB::beginTransaction();

        try {
            $salesQuery = Sale::query()
                ->where('store_id', $store->id)
                ->forOpenAccountingShift($businessDate)
                ->where(function ($query) {
                    $query->whereNull('description')
                        ->orWhere('description', '!=', 'manual_invoice_entry');
                });

            $gapSales = (clone $salesQuery)
                ->withCount('items')
                ->orderBy('created_at')
                ->get();

            $totalSales = (float) $gapSales->sum('paid_amount');
            $cashSales = (float) $gapSales->where('sale_type', 'cash')->sum('paid_amount');
            $mixedCash = (float) $gapSales->where('sale_type', 'mixed')->sum('cash_amount');
            $cardSales = (float) $gapSales->where('sale_type', 'card')->sum('paid_amount')
                + (float) $gapSales->where('sale_type', 'mixed')->sum('card_amount');
            $productsSalesValue = (float) $gapSales->sum('products_total');
            $laborTotal = (float) $gapSales->sum('labor_total');
            $legacyCostValue = (float) $gapSales->sum(fn (Sale $sale) => max((float) (($sale->products_total + $sale->labor_total) - $sale->profit), 0));
            $expenses = (float) Expense::where('store_id', $store->id)
                ->forOpenAccountingShift($businessDate)
                ->sum('amount');
            $withdrawals = (float) Withdrawal::where('store_id', $store->id)
                ->forOpenAccountingShift($businessDate)
                ->sum('amount');

            $startTime = Carbon::parse($businessDate)->startOfDay();
            $accountantFinanceMovements = $this->getAccountantFinanceMovements($store->id, $startTime, now(), $businessDate);
            $selectedDebtAdjustmentIds = collect($request->input('include_debt_adjustments', []))
                ->map(fn ($id) => (int) $id)
                ->filter()
                ->values();
            $includedDebtAdjustments = collect($accountantFinanceMovements['debt_rows'] ?? [])
                ->filter(fn ($row) => $selectedDebtAdjustmentIds->contains((int) ($row['id'] ?? 0)))
                ->values();
            $includedDebtAdjustmentTotal = (float) $includedDebtAdjustments->sum('amount');
            $cashFromCollections = (float) ($accountantFinanceMovements['collections_cash_total'] ?? ($accountantFinanceMovements['collections_total'] ?? 0));
            $expectedCash = max(0, $cashSales + $mixedCash + $cashFromCollections - $expenses - $withdrawals - $includedDebtAdjustmentTotal);
            $actualCash = (float) $validated['actual_cash'];
            $difference = $actualCash - $expectedCash;
            $closedAt = now();
            $notes = trim((string) ($validated['notes'] ?? ''));

            $dailyBalance = DailyBalance::create([
                'store_id' => $store->id,
                'accountant_id' => $accountant->id,
                'system_sales_total' => $totalSales,
                'system_cash_expected' => $expectedCash,
                'actual_cash_submitted' => $actualCash,
                'difference' => $difference,
                'start_time' => $startTime,
                'end_time' => $closedAt,
                'business_date' => $businessDate,
                'closed_at' => $closedAt,
                'notes' => $notes,
            ]);

            $reportData = $this->buildShiftGapReportData(
                $businessDate,
                $startTime,
                $closedAt,
                $gapSales,
                $totalSales,
                $cashSales + $mixedCash,
                $cardSales,
                $productsSalesValue,
                $legacyCostValue,
                $laborTotal,
                $expenses,
                $withdrawals,
                $expectedCash,
                $actualCash,
                $difference,
                $notes,
                $accountantFinanceMovements,
                $includedDebtAdjustments->all(),
                $includedDebtAdjustmentTotal
            );

            app(ShiftOperationBinderService::class)->attachByBusinessDate($dailyBalance, $businessDate);
            $this->markActiveShiftGapResolved($dailyBalance, $businessDate);
            $waUrl = $this->generateReportAndWhatsApp($store, $accountant, $reportData);
            $this->clearShiftGapSession();

            DB::commit();

            return redirect()->route('accountant.dashboard')->with([
                'success' => 'تم إصدار موازنة اليوم المرجع '.$businessDate.' وربط عملياته بالشفت.',
                'balance_id' => $dailyBalance->id,
                'wa_url' => $waUrl,
            ]);
        } catch (\Throwable $exception) {
            DB::rollBack();
            \Log::error('Failed to close active shift gap: '.$exception->getMessage(), [
                'store_id' => $store->id,
                'business_date' => $businessDate,
            ]);

            return back()->with('error', 'تعذر إغلاق اليوم المرجع: '.$exception->getMessage());
        }
    }

    public function clearShiftGap()
    {
        if ($logId = session('accountant_shift_gap_log_id')) {
            $log = Log::where('id', $logId)
                ->where('action', 'shift_gap_accountant_request')
                ->first();

            if ($log) {
                $details = is_array($log->details) ? $log->details : [];
                $details['status'] = 'pending';
                $details['accountant_paused_at'] = now()->toDateTimeString();
                $log->update(['details' => $details]);
            }
        }

        $this->clearShiftGapSession();

        return redirect()->route('accountant.dashboard')
            ->with('success', 'تم تأجيل معالجة اليوم المرجع والعودة للشفت الحالي. سيبقى الطلب ظاهرًا للمحاسب لاحقًا.');
    }

    private function ensurePreviousDayShiftRequest(int $storeId, int $accountantId, string $accountantName, array $missingBusinessDates): void
    {
        // طلب اليوم السابق ينشأ تلقائيًا للمحاسب حتى يظهر كطلب مراجعة بدون تدخل المالك عند بقاء شفت الأمس غير مغلق.
        $previousDate = now()->subDay()->toDateString();

        if (! in_array($previousDate, $missingBusinessDates, true)) {
            return;
        }

        $store = Store::find($storeId);
        if (! $store) {
            return;
        }

        $systemAccountantId = (int) Accountant::query()
            ->where('store_id', $storeId)
            ->where('status', 'active')
            ->orderBy('id')
            ->value('id');

        if ($systemAccountantId && $systemAccountantId !== $accountantId) {
            return;
        }

        $shiftInfo = app(ShiftGapInfoService::class)->shiftInfo($store, $previousDate);
        if ($shiftInfo['closed_shifts_count'] >= $shiftInfo['max_shifts']) {
            return;
        }

        $missingShiftNumber = (int) $shiftInfo['missing_shift_number'];
        $alreadyExists = Log::query()
            ->where('store_id', $storeId)
            ->where('action', 'shift_gap_accountant_request')
            ->latest()
            ->limit(30)
            ->get()
            ->contains(function (Log $log) use ($previousDate, $missingShiftNumber) {
                $businessDate = data_get($log->details, 'business_date');
                $status = data_get($log->details, 'status', 'pending');

                return $businessDate
                    && Carbon::parse($businessDate)->toDateString() === $previousDate
                    && (int) data_get($log->details, 'missing_shift_number', 1) === $missingShiftNumber
                    && in_array($status, ['pending', 'in_progress'], true);
            });

        if ($alreadyExists) {
            return;
        }

        $shiftLabel = 'الشفت ' . $missingShiftNumber . ' من ' . $shiftInfo['max_shifts'];

        Log::create([
            'store_id' => $storeId,
            'user_id' => $store->user_id,
            'action' => 'shift_gap_accountant_request',
            'description' => 'طلب نظامي لمراجعة ' . $shiftLabel . ' لليوم السابق بتاريخ ' . $previousDate,
            'model_type' => Store::class,
            'model_id' => $storeId,
            'details' => [
                'business_date' => $previousDate,
                'status' => 'pending',
                'requested_at' => now()->toDateTimeString(),
                'accountant_id' => $accountantId,
                'accountant_name' => $accountantName,
                'closed_shifts_count' => $shiftInfo['closed_shifts_count'],
                'missing_shift_number' => $missingShiftNumber,
                'max_shifts' => $shiftInfo['max_shifts'],
                'shift_label' => $shiftLabel,
                'shift_key' => $previousDate . '#' . $missingShiftNumber,
                'source' => 'system_previous_day',
            ],
        ]);
    }

    private function pendingShiftGapRequests(int $storeId, array $missingBusinessDates, int $accountantId)
    {
        $store = Store::find($storeId);

        return Log::query()
            ->where('store_id', $storeId)
            ->where('action', 'shift_gap_accountant_request')
            ->latest()
            ->limit(30)
            ->get()
            ->filter(function (Log $log) use ($missingBusinessDates, $accountantId, $store) {
                $assignedAccountantId = data_get($log->details, 'accountant_id');
                if ($assignedAccountantId && (int) $assignedAccountantId !== $accountantId) {
                    return false;
                }

                $businessDate = data_get($log->details, 'business_date');
                if (! $businessDate) {
                    return false;
                }

                $businessDate = Carbon::parse($businessDate)->toDateString();
                $details = is_array($log->details) ? $log->details : [];
                $status = data_get($details, 'status', 'pending');
                if (! in_array($status, ['pending', 'in_progress'], true)) {
                    return false;
                }

                if (in_array($businessDate, $missingBusinessDates, true)) {
                    return true;
                }

                $isDeferredShift = $this->isDeferredOrResumableShiftGap($details);

                if (! $store || ! $isDeferredShift) {
                    return false;
                }

                $requiredShifts = max((int) data_get($log->details, 'max_shifts', 0), app(ShiftLifecycleService::class)->maxShiftsPerBusinessDate($store));

                return $this->closedShiftsCount($store, $businessDate) < $requiredShifts;
            })
            ->unique(fn (Log $log) => Carbon::parse(data_get($log->details, 'business_date'))->toDateString().'#'.data_get($log->details, 'missing_shift_number', 1))
            ->values();
    }


    private function isDeferredOrResumableShiftGap(array $details): bool
    {
        $status = data_get($details, 'status', 'pending');

        return data_get($details, 'source') === 'accountant_deferred_shift'
            || filled(data_get($details, 'accountant_paused_at'))
            || ($status === 'in_progress' && filled(data_get($details, 'accountant_started_at')));
    }

    private function activeShiftGapLog(Store $store, string $businessDate): ?Log
    {
        $logId = session('accountant_shift_gap_log_id');
        if (! $logId) {
            return null;
        }

        return Log::query()
            ->where('id', $logId)
            ->where('store_id', $store->id)
            ->where('action', 'shift_gap_accountant_request')
            ->get()
            ->first(function (Log $log) use ($businessDate) {
                $logBusinessDate = data_get($log->details, 'business_date');

                return $logBusinessDate && Carbon::parse($logBusinessDate)->toDateString() === $businessDate;
            });
    }

    private function closedShiftsCount(Store $store, string $businessDate): int
    {
        return DailyBalance::query()
            ->where('store_id', $store->id)
            ->whereNotNull('end_time')
            ->where(function ($query) use ($businessDate) {
                $query->whereDate('business_date', $businessDate)
                    ->orWhere(function ($legacyQuery) use ($businessDate) {
                        $legacyQuery->whereNull('business_date')
                            ->whereDate('start_time', $businessDate);
                    });
            })
            ->count();
    }

    private function syncDeferredShiftRequest(Store $store, Accountant $accountant, string $businessDate, ?string $nextShiftDecision): void
    {
        if ($nextShiftDecision !== 'next_business_date') {
            return;
        }

        $closedShiftsCount = $this->closedShiftsCount($store, $businessDate);
        $maxShifts = app(ShiftLifecycleService::class)->maxShiftsPerBusinessDate($store);
        if ($closedShiftsCount >= $maxShifts) {
            return;
        }

        $missingShiftNumber = min($closedShiftsCount + 1, $maxShifts);
        $alreadyExists = Log::query()
            ->where('store_id', $store->id)
            ->where('action', 'shift_gap_accountant_request')
            ->latest()
            ->limit(30)
            ->get()
            ->contains(function (Log $log) use ($businessDate, $missingShiftNumber) {
                $logBusinessDate = data_get($log->details, 'business_date');
                $status = data_get($log->details, 'status', 'pending');

                return $logBusinessDate
                    && Carbon::parse($logBusinessDate)->toDateString() === $businessDate
                    && (int) data_get($log->details, 'missing_shift_number', 1) === $missingShiftNumber
                    && in_array($status, ['pending', 'in_progress'], true);
            });

        if ($alreadyExists) {
            return;
        }

        $shiftLabel = 'الشفت ' . $missingShiftNumber . ' من ' . $maxShifts;

        Log::create([
            'store_id' => $store->id,
            'user_id' => $store->user_id,
            'action' => 'shift_gap_accountant_request',
            'description' => 'شفت مؤجل أضافه المحاسب عند إقفال ' . $shiftLabel . ' بتاريخ ' . $businessDate,
            'model_type' => Store::class,
            'model_id' => $store->id,
            'details' => [
                'business_date' => $businessDate,
                'status' => 'pending',
                'requested_at' => now()->toDateTimeString(),
                'accountant_id' => (int) $accountant->id,
                'accountant_name' => (string) $accountant->name,
                'closed_shifts_count' => $closedShiftsCount,
                'missing_shift_number' => $missingShiftNumber,
                'max_shifts' => $maxShifts,
                'shift_label' => $shiftLabel,
                'shift_key' => $businessDate . '#' . $missingShiftNumber,
                'source' => 'accountant_deferred_shift',
                'deferred_by_accountant_id' => (int) $accountant->id,
                'deferred_at' => now()->toDateTimeString(),
                'next_business_date' => Carbon::parse($businessDate)->addDay()->toDateString(),
            ],
        ]);
    }

    private function getSalesStatistics($storeId, $startTime, ?string $businessDate = null)
    {
        return Sale::where('store_id', $storeId)
            ->forOpenAccountingShift($businessDate, $startTime)
            ->where(function ($query) {
                $query->whereNull('description')
                    ->orWhere('description', '!=', 'manual_invoice_entry');
            })
            ->selectRaw('
                COALESCE(SUM(paid_amount), 0) as total_sales,
                COALESCE(SUM((products_total + labor_total) - profit), 0) as total_cost,

                -- المبالغ النقدية: من مبيعات كاش + الجزء النقدي من المختلط
                COALESCE(SUM(CASE WHEN sale_type = "cash" THEN paid_amount ELSE 0 END), 0) +
                COALESCE(SUM(CASE WHEN sale_type = "mixed" THEN cash_amount ELSE 0 END), 0) as cash_sales,

                -- مبالغ الشبكة: من مبيعات شبكة + الجزء الشبكي من المختلط
                COALESCE(SUM(CASE WHEN sale_type = "card" THEN paid_amount ELSE 0 END), 0) +
                COALESCE(SUM(CASE WHEN sale_type = "mixed" THEN card_amount ELSE 0 END), 0) as card_sales,

                COALESCE(SUM(CASE WHEN sale_type = "mixed" THEN paid_amount ELSE 0 END), 0) as mixed_sales,

                -- مدفوعات الآجل (ما تم دفعه من أصل آجل)
                COALESCE(SUM(CASE WHEN sale_type = "credit" THEN paid_amount ELSE 0 END), 0) as credit_payments,

                -- الآجل الرسمي (مع موظف)
                COALESCE(SUM(CASE
                    WHEN (sale_type = "credit" OR has_partial_credit = 1)
                    AND employee_id IS NOT NULL
                    AND remaining_amount > 0
                    THEN remaining_amount
                    ELSE 0
                END), 0) as official_credit_sales,

                -- فروقات الدفع (بدون موظف)
                COALESCE(SUM(CASE
                    WHEN (sale_type = "credit" OR has_partial_credit = 1)
                    AND employee_id IS NULL
                    AND remaining_amount > 0
                    THEN remaining_amount
                    ELSE 0
                END), 0) as payment_gaps
            ')
            ->first()
            ->toArray();
    }

    private function formatShiftDuration($hours)
    {
        if ($hours < 1) {
            $minutes = $hours * 60;
            return round($minutes) . ' دقيقة';
        } elseif ($hours < 24) {
            $hoursInt = floor($hours);
            $minutes = round(($hours - $hoursInt) * 60);

            if ($minutes > 0) {
                return $hoursInt . ' ساعة و ' . $minutes . ' دقيقة';
            }
            return $hoursInt . ' ساعة';
        } else {
            $days = floor($hours / 24);
            $remainingHours = floor($hours % 24);

            if ($remainingHours > 0) {
                return $days . ' يوم و ' . $remainingHours . ' ساعة';
            }
            return $days . ' يوم';
        }
    }

    private function getDashboardQuickStats($storeId, $startTime, ?string $businessDate = null)
    {
        $topEmployee = Sale::where('store_id', $storeId)
            ->forOpenAccountingShift($businessDate, $startTime)
            ->where(function ($query) {
                $query->whereNull('description')
                    ->orWhere('description', '!=', 'manual_invoice_entry');
            })
            ->whereNotNull('employee_id')
            ->select('employee_id', DB::raw('SUM(final_total) as total_sales'))
            ->groupBy('employee_id')
            ->orderBy('total_sales', 'desc')
            ->first();

        return [
            'avg_sale_amount' => Sale::where('store_id', $storeId)
                ->forOpenAccountingShift($businessDate, $startTime)
                ->where(function ($query) {
                    $query->whereNull('description')
                        ->orWhere('description', '!=', 'manual_invoice_entry');
                })
                ->avg('final_total') ?? 0,

            'invoice_count' => Sale::where('store_id', $storeId)
                ->forOpenAccountingShift($businessDate, $startTime)
                ->where(function ($query) {
                    $query->whereNull('description')
                        ->orWhere('description', '!=', 'manual_invoice_entry');
                })
                ->count(),

            'highest_sale' => Sale::where('store_id', $storeId)
                ->forOpenAccountingShift($businessDate, $startTime)
                ->where(function ($query) {
                    $query->whereNull('description')
                        ->orWhere('description', '!=', 'manual_invoice_entry');
                })
                ->max('final_total') ?? 0,

            'top_employee' => $topEmployee ? [
                'employee_id' => $topEmployee->employee_id,
                'total_sales' => $topEmployee->total_sales
            ] : null,
        ];
    }

    public function viewReport($filename)
    {
        // البحث في المسار الموحد للتخزين
        $path = storage_path('app/public/reports/' . $filename);

        if (!file_exists($path)) {
            \Log::error("التقرير غير موجود في المسار: " . $path);
            abort(404);
        }

        return response()->file($path, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"'
        ]);
    }

    private function emptyCreditCollections(): array
    {
        return [
            'total' => 0,
            'from_current_period' => 0,
            'from_old_period' => 0,
            'details' => [],
            'count' => 0,
        ];
    }


    private function enrichDebtCollectionRow(array $row, EmployeeLog $log): array
    {
        $collectionAmount = abs((float) ($log->amount ?? 0));
        $operationDate = data_get($log->meta, 'operation_date') ?: optional($log->created_at)->toDateString();

        $debt = DB::table('debts as collection')
            ->leftJoin('debts as parent', 'collection.debt_parent_id', '=', 'parent.id')
            ->where('collection.store_id', $log->store_id)
            ->where('collection.person_id', $log->person_id)
            ->where('collection.person_type', $log->person_type)
            ->whereDate('collection.date', $operationDate)
            ->whereRaw('ABS(ABS(COALESCE(collection.amount, 0)) - ?) < 0.01', [$collectionAmount])
            ->orderByDesc('collection.id')
            ->first([
                'collection.id as collection_id',
                'collection.debt_parent_id',
                'collection.description',
                'collection.date',
                'parent.amount as parent_remaining_amount',
                'parent.description as parent_description',
            ]);

        if (! $debt) {
            return $row;
        }

        $previousCollected = (float) DB::table('debts')
            ->where('debt_parent_id', $debt->debt_parent_id)
            ->where('amount', '<', 0)
            ->where('id', '<', $debt->collection_id)
            ->sum(DB::raw('ABS(amount)'));

        $parentRemaining = max(0, (float) ($debt->parent_remaining_amount ?? 0));
        $parentOriginalEstimate = $parentRemaining + $previousCollected + $collectionAmount;

        $row['debt_collection_id'] = (int) $debt->collection_id;
        $row['debt_parent_id'] = $debt->debt_parent_id ? (int) $debt->debt_parent_id : null;
        $row['operation_name'] = $debt->parent_description ?: $debt->description ?: $row['description'];
        $row['operation_amount'] = $parentOriginalEstimate;
        $row['debt_parent_amount'] = $parentOriginalEstimate;
        $row['remaining_before_collection'] = max(0, $parentOriginalEstimate - $previousCollected);
        $row['remaining_after_collection'] = $parentRemaining;
        $row['operation_date'] = $operationDate;

        return $row;
    }

    private function enrichCreditCollectionRow(array $row, EmployeeLog $log): array
    {
        $collectionAmount = (float) ($row['amount'] ?? 0);
        $collectionDate = Carbon::parse($row['operation_date'] ?? $row['collection_date'] ?? $log->created_at)->toDateString();
        $actorId = (int) data_get($log->meta, 'actor_id', 0);

        $collection = DB::table('employee_credit_collections')
            ->join('credit_sales', 'credit_sales.id', '=', 'employee_credit_collections.credit_sale_id')
            ->where('employee_credit_collections.store_id', $log->store_id)
            ->where('employee_credit_collections.person_id', $log->person_id)
            ->where('employee_credit_collections.person_type', $log->person_type)
            ->whereDate('employee_credit_collections.collection_date', $collectionDate)
            ->whereRaw('ABS(COALESCE(employee_credit_collections.amount, 0) - ?) < 0.01', [$collectionAmount])
            ->when($actorId > 0, fn ($query) => $query->where(function ($actorQuery) use ($actorId) {
                $actorQuery->whereNull('employee_credit_collections.collected_by')
                    ->orWhere('employee_credit_collections.collected_by', $actorId);
            }))
            ->orderByDesc('employee_credit_collections.id')
            ->select([
                'employee_credit_collections.id as collection_id',
                'employee_credit_collections.credit_sale_id',
                'employee_credit_collections.collection_date',
                'credit_sales.amount as operation_amount',
                'credit_sales.credit_note',
                'credit_sales.description',
                'credit_sales.date as credit_operation_date',
            ])
            ->first();

        if (!$collection) {
            return $row;
        }

        $previousCollected = (float) DB::table('employee_credit_collections')
            ->where('credit_sale_id', $collection->credit_sale_id)
            ->where('id', '<', $collection->collection_id)
            ->sum('amount');

        $row['credit_sale_id'] = (int) $collection->credit_sale_id;
        $row['credit_note'] = $collection->credit_note;
        $row['operation_name'] = $collection->credit_note ?: $collection->description;
        $row['operation_amount'] = (float) $collection->operation_amount;
        $row['remaining_before_collection'] = max(0, (float) $collection->operation_amount - $previousCollected);
        $row['remaining_after_collection'] = max(0, (float) $collection->operation_amount - $previousCollected - $collectionAmount);
        $row['operation_date'] = $collection->credit_operation_date
            ? Carbon::parse($collection->credit_operation_date)->toDateString()
            : ($row['operation_date'] ?? null);

        return $row;
    }

    private function emptyAccountantFinanceMovements(): array
    {
        return [
            'collections_total' => 0.0,
            'collections_cash_total' => 0.0,
            'collections_card_total' => 0.0,
            'credit_collections_total' => 0.0,
            'debt_collections_total' => 0.0,
            'debt_total' => 0.0,
            'collection_rows' => [],
            'debt_rows' => [],
        ];
    }

    private function getAccountantFinanceMovements(int $storeId, $startTime, $endTime, ?string $businessDate = null): array
    {
        $logs = EmployeeLog::query()
            ->where('store_id', $storeId)
            ->when(
                $businessDate,
                // في يوم مرجع/مرتجع تُسجل العمليات الآن لكن meta.operation_date يحمل تاريخ اليوم المرجع؛ لذلك نفلتر بتاريخ العملية لا بوقت الإدخال.
                fn ($query) => $query->where('meta->operation_date', Carbon::parse($businessDate)->toDateString()),
                fn ($query) => $query->whereBetween('created_at', [$startTime, $endTime])
            )
            ->whereIn('action_name', ['debt', 'debt_collect_full', 'debt_collect_partial', 'credit_sale_deducted', 'credit_sale_partial'])
            ->where('meta->actor_type', 'accountant')
            ->with(['person'])
            ->orderBy('created_at')
            ->get();

        $collectionRows = [];
        $debtRows = [];

        foreach ($logs as $log) {
            $employeeName = optional($log->person)->name ?: 'موظف #' . $log->person_id;
            $row = [
                'id' => (int) $log->id,
                'employee_id' => (int) $log->person_id,
                'employee_name' => $employeeName,
                'amount' => abs((float) ($log->amount ?? 0)),
                'description' => $log->description,
                'collection_date' => optional($log->created_at)->toDateTimeString(),
                'time' => optional($log->created_at)->format('h:i A'),
                'actor_name' => data_get($log->meta, 'actor_name', 'المحاسب'),
                'operation_date' => data_get($log->meta, 'operation_date'),
                'action_name' => $log->action_name,
                'payment_method' => data_get($log->meta, 'payment_method', 'cash'),
                'payment_method_label' => data_get($log->meta, 'payment_method_label', 'كاش'),
                'cash_amount' => (float) data_get($log->meta, 'cash_amount', abs((float) ($log->amount ?? 0))),
                'card_amount' => (float) data_get($log->meta, 'card_amount', 0),
            ];

            if ($log->action_name === 'debt') {
                $row['type'] = 'debt_addition';
                $debtRows[] = $row;
                continue;
            }

            if ($row['amount'] <= 0) {
                continue;
            }

            $isDebtCollection = in_array($log->action_name, ['debt_collect_full', 'debt_collect_partial'], true);
            $row['collection_kind'] = $isDebtCollection ? 'debt' : 'credit';
            $row['collection_label'] = $isDebtCollection ? 'تحصيل مديونية' : 'تحصيل آجل';
            $row['type'] = in_array($log->action_name, ['debt_collect_full', 'credit_sale_deducted'], true)
                ? 'full_collection'
                : 'partial_collection';
            $row['collected_in_shift'] = $row['amount'];
            if ($isDebtCollection) {
                $row = $this->enrichDebtCollectionRow($row, $log);
            } else {
                $row = $this->enrichCreditCollectionRow($row, $log);
            }
            $collectionRows[] = $row;
        }

        return [
            'collections_total' => (float) collect($collectionRows)->sum('amount'),
            'collections_cash_total' => (float) collect($collectionRows)->sum('cash_amount'),
            'collections_card_total' => (float) collect($collectionRows)->sum('card_amount'),
            'credit_collections_total' => (float) collect($collectionRows)->where('collection_kind', 'credit')->sum('amount'),
            'debt_collections_total' => (float) collect($collectionRows)->where('collection_kind', 'debt')->sum('amount'),
            'debt_total' => (float) collect($debtRows)->sum('amount'),
            'collection_rows' => $collectionRows,
            'debt_rows' => $debtRows,
        ];
    }

    private function getCreditCollections($storeId, $startTime, $endTime, ?string $businessDate = null)
    {
        try {
            $collectionRows = DB::table('employee_credit_collections')
                ->join('credit_sales', 'credit_sales.id', '=', 'employee_credit_collections.credit_sale_id')
                ->where('employee_credit_collections.store_id', $storeId)
                ->whereNull('credit_sales.deleted_at')
                ->when(
                    $businessDate,
                    fn ($query) => $query->where('employee_credit_collections.collection_date', Carbon::parse($businessDate)->toDateString()),
                    fn ($query) => $query->whereBetween('employee_credit_collections.collection_date', [
                        Carbon::parse($startTime)->toDateString(),
                        Carbon::parse($endTime)->toDateString(),
                    ])
                )
                ->select([
                    'employee_credit_collections.id',
                    'employee_credit_collections.credit_sale_id',
                    'employee_credit_collections.sale_id',
                    'employee_credit_collections.person_id',
                    'employee_credit_collections.amount as collected_amount',
                    'employee_credit_collections.cash_amount',
                    'employee_credit_collections.card_amount',
                    'employee_credit_collections.payment_method',
                    'employee_credit_collections.collection_date',
                    'employee_credit_collections.created_at as collection_created_at',
                    'credit_sales.amount as original_amount',
                    'credit_sales.remaining_amount',
                    'credit_sales.created_at as credit_created_at',
                    'credit_sales.credit_note',
                    'credit_sales.description',
                ])
                ->orderBy('employee_credit_collections.collection_date')
                ->orderBy('employee_credit_collections.id')
                ->get();

            $totalCollected = 0.0;
            $collectedFromCurrentPeriod = 0.0;
            $collectedFromOldPeriod = 0.0;
            $collectionDetails = [];

            foreach ($collectionRows as $collection) {
                $collectedInShift = (float) $collection->collected_amount;

                if ($collectedInShift <= 0) {
                    continue;
                }

                $totalCollected += $collectedInShift;
                $isFromCurrentPeriod = Carbon::parse($collection->credit_created_at)->gte(Carbon::parse($startTime));

                if ($isFromCurrentPeriod) {
                    $collectedFromCurrentPeriod += $collectedInShift;
                } else {
                    $collectedFromOldPeriod += $collectedInShift;
                }

                $employeeName = $this->getEmployeeName($collection->person_id);

                $collectionDetails[] = [
                    'id' => $collection->credit_sale_id,
                    'collection_id' => $collection->id,
                    'employee_id' => $collection->person_id,
                    'employee_name' => $employeeName,
                    'original_amount' => (float) $collection->original_amount,
                    'collected_in_shift' => $collectedInShift,
                    'remaining_amount' => (float) $collection->remaining_amount,
                    'is_full_payment' => (float) $collection->remaining_amount == 0.0,
                    'is_partial_payment' => (float) $collection->remaining_amount > 0.0,
                    'collection_date' => $collection->collection_date,
                    'credit_created_at' => $collection->credit_created_at,
                    'is_from_current_period' => $isFromCurrentPeriod,
                    'type' => $isFromCurrentPeriod ? 'current' : 'old',
                    'description' => $collection->credit_note ?: $collection->description,
                    'payment_method' => $collection->payment_method,
                    'cash_amount' => (float) $collection->cash_amount,
                    'card_amount' => (float) $collection->card_amount,
                    'note' => $isFromCurrentPeriod
                        ? 'تحصيل أجل من عمليات هذا الشفت'
                        : 'تحصيل أجل من عمليات سابقة',
                ];
            }

            return [
                'total' => $totalCollected,
                'from_current_period' => $collectedFromCurrentPeriod,
                'from_old_period' => $collectedFromOldPeriod,
                'details' => $collectionDetails,
                'count' => count($collectionDetails),
                'warning' => null,
            ];

        } catch (\Exception $e) {
            \Log::error('Error getting credit collections: ' . $e->getMessage());
            return [
                'total' => 0,
                'from_current_period' => 0,
                'from_old_period' => 0,
                'details' => [],
                'count' => 0,
            ];
        }
    }

    private function getEmployeeName($personId)
    {
        try {
            $employee = DB::table('employees')
                ->where('id', $personId)
                ->select('name')
                ->first();

            return $employee ? $employee->name : 'موظف #' . $personId;
        } catch (\Exception $e) {
            \Log::error('Error getting employee name: ' . $e->getMessage());
            return 'غير معروف';
        }
    }

  public function storeBalance(Request $request)
{

	    $validator = Validator::make($request->all(), [
	        'actual_cash' => 'required|numeric|min:0',
	        'notes' => 'nullable|string|max:500',
	        'next_shift_decision' => 'nullable|in:same_business_date,next_business_date',
	        'include_debt_adjustments' => 'nullable|array',
	        'include_debt_adjustments.*' => 'integer',
	    ]);

    if ($validator->fails()) {
        return redirect()->back()
            ->withErrors($validator)
            ->withInput();
    }

    if (session('accountant_shift_gap_business_date')) {
        return $this->closeActiveShiftGap($request);
    }

    DB::beginTransaction();

    try {
        $accountant = auth('accountant')->user();
        $store = $accountant->store;

        if (!$store) {
            throw new \Exception('المحاسب غير مرتبط بأي متجر');
        }

        // ✅ تحقق من وجود إقفال حديث
        $recentBalance = DailyBalance::where('store_id', $store->id)
            ->where('created_at', '>', now()->subMinutes(1))
            ->first();

        if ($recentBalance) {
            throw new \Exception('تم إصدار الموازنة مؤخراً. الرجاء الانتظار قليلاً.');
        }

        $endTime = now();
        $shiftContext = app(ShiftLifecycleService::class)->currentShiftContext($store, $endTime);
        $startTime = $shiftContext['shift_start'];
        $businessDate = $shiftContext['business_date'];
        $canChooseNextShiftBusinessDate = (bool) ($shiftContext['can_choose_next_shift_business_date'] ?? false);
        $nextShiftDecision = $canChooseNextShiftBusinessDate
            ? $request->input('next_shift_decision', 'same_business_date')
            : null;

        if ($canChooseNextShiftBusinessDate && ! in_array($nextShiftDecision, ['same_business_date', 'next_business_date'], true)) {
            throw new \Exception('قرار الشفت التالي غير صالح.');
        }

        $nextShiftBusinessDate = match ($nextShiftDecision) {
            'same_business_date' => $businessDate,
            'next_business_date' => Carbon::parse($businessDate)->addDay()->toDateString(),
            default => null,
        };

        \Log::info('Balance closure started', [
            'store_id' => $store->id,
            'accountant_id' => $accountant->id,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'actual_cash' => $request->actual_cash
        ]);

        $salesSummary = Sale::where('store_id', $store->id)
            ->forOpenAccountingShift($businessDate, $startTime)
            ->selectRaw('
                COALESCE(SUM(CASE WHEN (description IS NULL OR description != "manual_invoice_entry") THEN paid_amount ELSE 0 END), 0) as total_sales,
                COALESCE(SUM(CASE WHEN (description IS NULL OR description != "manual_invoice_entry") THEN (products_total + labor_total) - profit ELSE 0 END), 0) as total_cost,
                COALESCE(SUM(CASE WHEN sale_type = "cash" THEN paid_amount ELSE 0 END), 0) +
                COALESCE(SUM(CASE WHEN sale_type = "mixed" THEN cash_amount ELSE 0 END), 0) as cash_sales,
                COALESCE(SUM(CASE WHEN sale_type = "card" THEN paid_amount ELSE 0 END), 0) +
                COALESCE(SUM(CASE WHEN sale_type = "mixed" THEN card_amount ELSE 0 END), 0) as card_sales,
                COALESCE(SUM(CASE WHEN sale_type = "credit" THEN paid_amount ELSE 0 END), 0) as credit_payments,
                COALESCE(SUM(CASE WHEN sale_type = "credit" THEN final_total ELSE 0 END), 0) as credit_sales,
                COALESCE(SUM(CASE WHEN sale_type = "internal_use" THEN final_total ELSE 0 END), 0) as internal_use_sales,
                COALESCE(SUM(CASE WHEN (sale_type = "credit" OR has_partial_credit = 1) AND employee_id IS NOT NULL AND remaining_amount > 0 THEN remaining_amount ELSE 0 END), 0) as official_credit_sales,
                COALESCE(SUM(CASE WHEN (sale_type = "credit" OR has_partial_credit = 1) AND employee_id IS NULL AND remaining_amount > 0 THEN remaining_amount ELSE 0 END), 0) as payment_gaps,
                COALESCE(SUM(CASE WHEN (description IS NULL OR description != "manual_invoice_entry") THEN labor_total ELSE 0 END), 0) as total_labor
            ')
            ->first();

        \Log::info('Sales summary calculated', ['total_sales' => $salesSummary->total_sales]);

        $totalSales = $salesSummary->total_sales ?? 0;
        $cashSales = $salesSummary->cash_sales ?? 0;
        $cardSales = $salesSummary->card_sales ?? 0;
        $creditSales = $salesSummary->credit_sales ?? 0;
        $officialCreditSales = $salesSummary->official_credit_sales ?? 0;
        $paymentGaps = $salesSummary->payment_gaps ?? 0;
        $laborTotal = $salesSummary->total_labor ?? 0;
        $internalUseSales = $salesSummary->internal_use_sales ?? 0;

        $creditCollections = $this->getCreditCollections($store->id, $startTime, $endTime, $businessDate);
        $accountantFinanceMovements = $this->getAccountantFinanceMovements($store->id, $startTime, $endTime, $businessDate);
        $selectedDebtAdjustmentIds = collect($request->input('include_debt_adjustments', []))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->values();
        $includedDebtAdjustments = collect($accountantFinanceMovements['debt_rows'] ?? [])
            ->filter(fn ($row) => $selectedDebtAdjustmentIds->contains((int) ($row['id'] ?? 0)))
            ->values();
        $includedDebtAdjustmentTotal = (float) $includedDebtAdjustments->sum('amount');
        // تحصيلات المحاسب فقط تدخل ككاش، والمديونيات التي يحددها المحاسب فقط تخصم من الكاش المتوقع.
        $cashFromCollections = (float) ($accountantFinanceMovements['collections_cash_total'] ?? ($accountantFinanceMovements['collections_total'] ?? 0));
        $collectedFromCurrentPeriod = $creditCollections['from_current_period'];
        $collectedFromOldPeriod = $creditCollections['from_old_period'];

        $totalCashInShift = $cashSales + $cashFromCollections;

        $productsStats = $this->calculateProductsProfit($store->id, $startTime, $endTime, $businessDate);
        $profitRecognitionStats = app(\App\Services\Accounting\ProfitRecognitionService::class)->fromSales(
            Sale::where('store_id', $store->id)
                ->forOpenAccountingShift($businessDate, $startTime)
                ->whereIn('sale_type', ['cash', 'card', 'credit', 'mixed'])
                ->where(function ($query) {
                    $query->whereNull('description')
                        ->orWhere('description', '!=', 'manual_invoice_entry');
                })
                ->get(['products_total', 'labor_total', 'profit', 'final_total', 'paid_amount', 'cash_amount', 'card_amount', 'remaining_amount'])
        );
        $productsProfit = $productsStats['profit'];
        $totalProductsSalesValue = $productsStats['sales_value'];
        $totalProductsCostValue = $productsStats['cost_value'];

        $netProfit = ($totalSales - $totalProductsCostValue) + $collectedFromCurrentPeriod;

        $expenses = Expense::where('store_id', $store->id)
            ->forOpenAccountingShift($businessDate, $startTime)
            ->sum('amount') ?? 0;

        $withdrawals = Withdrawal::where('store_id', $store->id)
            ->forOpenAccountingShift($businessDate, $startTime)
            ->sum('amount') ?? 0;

        $totalOutgoing = $expenses + $withdrawals;
        $remainingBalance = $netProfit - $totalOutgoing;
	        $expectedCashInHand = $totalCashInShift - $totalOutgoing - $includedDebtAdjustmentTotal;
        $actualCash = (float) $request->actual_cash;
        $cashDifference = $actualCash - $expectedCashInHand;

        \Log::info('Cash calculation', [
            'expected' => $expectedCashInHand,
            'actual' => $actualCash,
            'difference' => $cashDifference
        ]);



        $detailedSales = Sale::where('store_id', $store->id)
            ->forOpenAccountingShift($businessDate, $startTime)
            ->where(function ($query) {
                $query->whereNull('description')
                    ->orWhere('description', '!=', 'manual_invoice_entry');
            })
            ->with(['employee', 'accountant', 'items.product'])
            // نرتب العمليات داخل التقرير حسب وقتها ثم رقمها، ثم نستعمل رقمًا تسلسليًا خاصًا بالملف
            // بدل رقم قاعدة البيانات حتى يكون ترقيم PDF واضحًا ومتتابعًا للقارئ.
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()->values()->map(function($s, $index) {
                 $productsList = [];
        foreach ($s->items as $item) {
            $productsList[] = [
                'name' => $item->historical_product_name,
                'quantity' => $item->quantity,
                'price' => $item->price,
                'total' => $item->total
            ];
        }
                return [
                    'id' => $index + 1,
                    'database_id' => $s->id,
                    // قيمة عرض داخل التقرير فقط، ولا تعتمد على أي عمود إضافي في قاعدة البيانات.
                    'operation_name' => (mb_stripos((string) $s->description, 'تضليل') !== false
                        || mb_stripos((string) $s->description, 'تظليل') !== false)
                            ? $s->description
                            : null,
                    'time' => $s->created_at->format('h:i A'),
                    'type' => $s->sale_type,
                    'has_partial_credit' => (bool) $s->has_partial_credit,
                    'remaining_amount' => (float) $s->remaining_amount,
                    'received' => $s->paid_amount,
                    'total' => $s->final_total,
                    'labor_total' => $s->labor_total,
                    'labor_desc' => $s->description,
                    // نحتفظ بنفس معادلة التكلفة القديمة، ونمنع العرض السالب فقط لعمليات شغل اليد أو المنتجات بلا تكلفة.
                    'cost' => max(0, $s->products_total - $s->profit),
                    'profit' => $s->profit,
                    'employee' => $s->employee->name ?? '',
                    'accountant' => $s->accountant->name ?? '---',
                      'products' => $productsList, // إضافة المنتجات
            'products_count' => count($productsList) // عدد المنتجات
                ];
            });

        $detailedExpenses = Expense::where('store_id', $store->id)
            ->forOpenAccountingShift($businessDate, $startTime)
            ->get()->map(function($e) {
                return [
                    'time' => $e->created_at->format('h:i A'),
                    // نعتمد على الحقول الفعلية في جدول المصروفات (type / description)
                    'category' => $e->type ?? 'مصروف عام',
                    'reason' => $e->description ?? '—',
                    'amount' => $e->amount
                ];
            });

        $detailedWithdrawals = Withdrawal::with(['person', 'addedBy:id,name'])
            ->where('store_id', $store->id)
            ->forOpenAccountingShift($businessDate, $startTime)
            ->get()->map(function($w) {
                return [
                    'time' => $w->created_at->format('h:i A'),
                    'employee_name' => optional($w->person)->name ?? '—',
                    'actor_name' => optional($w->addedBy)->name ?? '—',
                    'reason' => $w->description ?: ($w->reason ?? 'سحب نقدي'),
                    'amount' => $w->amount
                ];
            });

        $reportData = [
            'store_id' => $store->id,
            'store_name' => $store->name,
            'accountant_id' => $accountant->id,
            'accountant_name' => $accountant->name,
            'business_date' => $businessDate,
            'start_time' => $startTime->format('Y-m-d H:i'),
            'end_time' => $endTime->format('Y-m-d H:i'),
            'report_date' => now()->format('Y-m-d H:i'),

            'total_sales' => $totalSales,
            'sales_breakdown' => [
                'cash_from_new_sales' => $cashSales,
                'card_from_new_sales' => $cardSales,
                'credit_sales' => $creditSales,
                'official_credit' => $officialCreditSales,
                'payment_gaps' => $paymentGaps,
                'internal_use' => $internalUseSales,
            ],

            'details_tables' => [
                'all_sales' => $detailedSales,
                'withdrawals_list' => $detailedWithdrawals,
                'expenses_list' => $detailedExpenses,
	                'collections' => $accountantFinanceMovements['collection_rows'] ?? [],
	                'accountant_debts' => $accountantFinanceMovements['debt_rows'] ?? [],
	            ],

            'credit_collections' => [
	                'total' => $cashFromCollections,
	                'from_current_period' => $collectedFromCurrentPeriod,
	                'from_old_period' => $collectedFromOldPeriod,
	                'details' => $accountantFinanceMovements['collection_rows'] ?? [],
	                'count' => count($accountantFinanceMovements['collection_rows'] ?? []),
	            ],
	            'accountant_finance_movements' => [
	                'collections_total' => $cashFromCollections,
	                'credit_collections_total' => (float) ($accountantFinanceMovements['credit_collections_total'] ?? 0),
	                'debt_collections_total' => (float) ($accountantFinanceMovements['debt_collections_total'] ?? 0),
	                'debt_total' => (float) ($accountantFinanceMovements['debt_total'] ?? 0),
	                'included_debt_total' => $includedDebtAdjustmentTotal,
	                'debt_rows' => $accountantFinanceMovements['debt_rows'] ?? [],
	                'included_debt_rows' => $includedDebtAdjustments->all(),
	                'collection_rows' => $accountantFinanceMovements['collection_rows'] ?? [],
	            ],

            'products_details' => [
                'sales_value' => $totalProductsSalesValue,
                'cost_value' => $totalProductsCostValue,
                'recognized_cost' => $profitRecognitionStats['recognized_cost'] ?? 0,
                'uncovered_cost' => $profitRecognitionStats['uncovered_cost'] ?? 0,
                'profit' => $productsProfit,
                'recognized_profit' => $profitRecognitionStats['recognized_profit'] ?? 0,
                'deferred_profit' => $profitRecognitionStats['deferred_profit'] ?? 0,
            ],
            'labor_total' => $laborTotal,
            'net_profit' => $netProfit,

            'outgoing_today' => [
                'expenses' => $expenses,
                'withdrawals' => $withdrawals,
                'total' => $totalOutgoing,
            ],

            'remaining_balance' => $remainingBalance,

            'cash_details' => [
                'cash_from_new_sales' => $cashSales,
                'cash_from_current_collections' => $collectedFromCurrentPeriod,
                'cash_from_old_collections' => $collectedFromOldPeriod,
	                'total_cash_collections' => $cashFromCollections,
	                'included_debt_adjustments' => $includedDebtAdjustmentTotal,
	                'total_cash_in_shift' => $totalCashInShift,
                'expected' => $expectedCashInHand,
                'actual' => $actualCash,
                'difference' => $cashDifference,
            ],

            'notes' => $request->notes,
        ];

        // \Log::info('Creating DailyBalance record...');

        $dailyBalance = DailyBalance::create([
            'store_id' => $store->id,
            'accountant_id' => $accountant->id,
            'system_sales_total' => $totalSales,
            'system_cash_expected' => $expectedCashInHand,
            'actual_cash_submitted' => $actualCash,
            'difference' => $cashDifference,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'business_date' => $businessDate,
            'closed_at' => $endTime,
            'next_shift_business_date' => $nextShiftBusinessDate,
            'next_shift_decision' => $nextShiftDecision,
            'next_shift_decided_by' => $nextShiftDecision ? $accountant->id : null,
            'notes' => $request->notes,
        ]);

        app(ShiftOperationBinderService::class)->attachByBusinessDate($dailyBalance, $businessDate, false, true);
        $this->syncDeferredShiftRequest($store, $accountant, $businessDate, $nextShiftDecision);

        // \Log::info('DailyBalance created with ID: ' . $dailyBalance->id);

        // Log::create([
        //     'store_id' => $store->id,
        //     'user_id' => null,
        //     'actor_type' => 'accountant',
        //     'actor_id' => $accountant->id,
        //     'model_type' => 'DailyBalance',
        //     'model_id' => $dailyBalance->id,
        //     'action' => 'balance_done',
        //     'description' => 'تم إصدار الموازنة اليومية',
        //     'details' => json_encode($reportData, JSON_UNESCAPED_UNICODE),
        //     'ip' => $request->ip(),
        //     'user_agent' => $request->userAgent(),
        // ]);

        $waUrl = $this->generateReportAndWhatsApp($store, $accountant, $reportData);

        DB::commit();

        Cache::forget('shift_sales_' . $store->id . '_' . $startTime->timestamp);
        Cache::forget('shift_expenses_' . $store->id . '_' . $startTime->timestamp);
        Cache::forget('shift_withdrawals_' . $store->id . '_' . $startTime->timestamp);

        \Log::info('✅ Balance closed successfully', ['balance_id' => $dailyBalance->id]);

        return redirect()->route('accountant.dashboard')->with([
            'success' => 'تم اصدار الموازنة بنجاح',
            'balance_id' => $dailyBalance->id,
            'wa_url' => $waUrl
        ]);

    } catch (\Exception $e) {
        DB::rollBack();
        \Log::error('❌ Balance closure failed: ' . $e->getMessage());
        \Log::error('Stack trace: ' . $e->getTraceAsString());
        return redirect()->back()->with('error', 'فشل إصدار الموازنة: ' . $e->getMessage())->withInput();
    }
}



    private function buildShiftGapReportData(
        string $businessDate,
        Carbon $startTime,
        Carbon $closedAt,
        $gapSales,
        float $totalSales,
        float $cashSales,
        float $cardSales,
        float $productsSalesValue,
        float $productsCostValue,
        float $laborTotal,
        float $expenses,
        float $withdrawals,
        float $expectedCash,
        float $actualCash,
        float $difference,
        string $notes,
        array $accountantFinanceMovements = [],
        array $includedDebtRows = [],
        float $includedDebtTotal = 0.0
    ): array {
        $salesRows = $gapSales->map(function (Sale $sale) {
            return [
                'id' => $sale->id,
                'time' => optional($sale->created_at)->format('h:i A'),
                'type' => $sale->sale_type,
                'products_count' => (int) ($sale->items_count ?? 0),
                'labor_total' => (float) $sale->labor_total,
                'total' => (float) $sale->final_total,
                'received' => (float) $sale->paid_amount,
            ];
        })->values()->all();

        $outgoingTotal = $expenses + $withdrawals;
        $productsProfit = max(0, $productsSalesValue - $productsCostValue);
        $profitRecognitionStats = app(\App\Services\Accounting\ProfitRecognitionService::class)->fromSales($gapSales);

        return [
            'start_time' => $startTime->format('Y-m-d h:i A'),
            'end_time' => $closedAt->format('Y-m-d h:i A'),
            'hide_period' => true,
            'business_date' => $businessDate,
            'report_date' => $closedAt->format('Y-m-d H:i'),
            'total_sales' => $totalSales,
            'sales_breakdown' => [
                'cash_from_new_sales' => $cashSales,
                'card_from_new_sales' => $cardSales,
            ],
            'credit_collections' => [
                'count' => count($accountantFinanceMovements['collection_rows'] ?? []),
                'total' => (float) ($accountantFinanceMovements['collections_total'] ?? 0),
                'details' => $accountantFinanceMovements['collection_rows'] ?? [],
            ],
            'details_tables' => [
                'all_sales' => $salesRows,
                'collections' => $accountantFinanceMovements['collection_rows'] ?? [],
                'accountant_debts' => $accountantFinanceMovements['debt_rows'] ?? [],
            ],
            'accountant_finance_movements' => [
                'collections_total' => (float) ($accountantFinanceMovements['collections_total'] ?? 0),
                'credit_collections_total' => (float) ($accountantFinanceMovements['credit_collections_total'] ?? 0),
                'debt_collections_total' => (float) ($accountantFinanceMovements['debt_collections_total'] ?? 0),
                'debt_total' => (float) ($accountantFinanceMovements['debt_total'] ?? 0),
                'included_debt_total' => $includedDebtTotal,
                'debt_rows' => $accountantFinanceMovements['debt_rows'] ?? [],
                'included_debt_rows' => $includedDebtRows,
                'collection_rows' => $accountantFinanceMovements['collection_rows'] ?? [],
            ],
            'outgoing_today' => [
                'expenses' => $expenses,
                'withdrawals' => $withdrawals,
                'total' => $outgoingTotal,
            ],
            'products_details' => [
                'sales_value' => $productsSalesValue,
                'cost_value' => $productsCostValue,
                'recognized_cost' => $profitRecognitionStats['recognized_cost'] ?? 0,
                'uncovered_cost' => $profitRecognitionStats['uncovered_cost'] ?? 0,
                'profit' => $productsProfit,
                'recognized_profit' => $profitRecognitionStats['recognized_profit'] ?? 0,
                'deferred_profit' => $profitRecognitionStats['deferred_profit'] ?? 0,
            ],
            'labor_total' => $laborTotal,
            'net_profit' => $totalSales - $productsCostValue - $outgoingTotal,
            'cash_details' => [
                'total_cash_collections' => (float) ($accountantFinanceMovements['collections_cash_total'] ?? ($accountantFinanceMovements['collections_total'] ?? 0)),
                'included_debt_adjustments' => $includedDebtTotal,
                'expected' => $expectedCash,
                'actual' => $actualCash,
                'difference' => $difference,
            ],
            'notes' => $notes,
        ];
    }

    private function clearShiftGapSession(): void
    {
        session()->forget([
            'accountant_shift_gap_store_id',
            'accountant_shift_gap_business_date',
            'accountant_shift_gap_log_id',
        ]);
    }

    private function markActiveShiftGapResolved(DailyBalance $dailyBalance, string $businessDate): void
    {
        $logId = session('accountant_shift_gap_log_id');

        if (! $logId) {
            return;
        }

        $log = Log::where('id', $logId)
            ->where('store_id', $dailyBalance->store_id)
            ->where('action', 'shift_gap_accountant_request')
            ->first();

        if (! $log) {
            return;
        }

        $details = is_array($log->details) ? $log->details : [];
        $details['status'] = 'resolved';
        $details['resolved_at'] = now()->toDateTimeString();
        $details['daily_balance_id'] = (int) $dailyBalance->id;
        $details['business_date'] = $businessDate;

        $log->update(['details' => $details]);
    }

    private function calculateProductsProfit($storeId, $startTime, $endTime, ?string $businessDate = null)
    {
        $summary = Sale::where('store_id', $storeId)
            ->forOpenAccountingShift($businessDate, $startTime)
            ->where(function ($query) {
                $query->whereNull('description')
                    ->orWhere('description', '!=', 'manual_invoice_entry');
            })
            ->selectRaw('COALESCE(SUM(products_total), 0) as sales_value')
            // الربح يُحسب ويحفظ وقت البيع من تكلفة كل سطر. لذلك نستخرج التكلفة
            // من سجل العملية نفسه ولا نعيد حساب الرولات من جدول المنتجات الحالي.
            ->selectRaw('COALESCE(SUM((products_total + labor_total) - profit), 0) as cost_value')
            ->first();

        $totalSalesValue = (float) ($summary->sales_value ?? 0);
        $totalCostValue = max(0, (float) ($summary->cost_value ?? 0));

        return [
            'sales_value' => $totalSalesValue,
            'cost_value' => $totalCostValue,
            'profit' => $totalSalesValue - $totalCostValue,
        ];
    }


    private function generateReportAndWhatsApp($store, $accountant, $reportData)
    {
        // 1. التحقق من الحد اليومي
        $cacheKey = 'whatsapp_messages_' . $store->id . '_' . now()->format('Ymd');
        $todayMessages = Cache::get($cacheKey, 0);

        if ($todayMessages >= 10) {
            \Log::warning('WhatsApp rate limit exceeded for store: ' . $store->id);
            return null;
        }

        // تنظيف التقارير القديمة مرة واحدة يومياً لمنع تراكم ملفات PDF.
        // نستخدم Key عام لليوم حتى لا يتكرر التنظيف مع كل عملية إقفال.
        $cleanupKey = 'reports_cleanup_' . now()->format('Ymd');
        if (!Cache::has($cleanupKey)) {
            $this->cleanupOldReports();
            Cache::put($cleanupKey, true, now()->endOfDay());
        }

        // 2. تجهيز رقم الهاتف
        $storeOwner = $store->user;
        $managerPhone = $storeOwner->phone ?? $store->phone ?? null;

        if (!$managerPhone) {
            \Log::warning('No phone number found for store: ' . $store->id);
            return null;
        }

        $cleanPhone = preg_replace('/[^0-9]/', '', $managerPhone);
        if (!str_starts_with($cleanPhone, '966')) {
            $cleanPhone = '966' . ltrim($cleanPhone, '0');
        }

        // 3. ✅ إنشاء PDF
        $reportTitle = $this->buildShiftReportTitle($store->name, $reportData['notes'] ?? null);
        $safeReportTitle = preg_replace('/[^\p{Arabic}\p{L}\p{N}\-_ ]+/u', '', $reportTitle) ?: 'تقرير اغلاق متجر';
        $safeReportTitle = str_replace(' ', '_', trim(preg_replace('/\s+/u', ' ', $safeReportTitle)));
        $fileName = 'Report_' . $safeReportTitle . '_' . time() . '_' . $store->id . '.pdf';
        $filePath = public_path('reports/' . $fileName);

        try {
            if (!file_exists(dirname($filePath))) {
                mkdir(dirname($filePath), 0755, true);
            }

            $pdfData = [
                'store' => $store,
                'accountant' => $accountant,
                'data' => $reportData,
                'report_title' => $reportTitle,
            ];

            PDF::loadView('pdf.pdf_report', $pdfData)
               ->setOption('encoding', 'utf-8')
               ->setOption('enable-local-file-access', true)
               ->save($filePath);

            \Log::info('✅ PDF created successfully: ' . $fileName);

        } catch (\Exception $e) {
            \Log::error('❌ PDF creation failed: ' . $e->getMessage());
            $fileName = null;
        }

        $reportUrl = $fileName ? url('reports/' . $fileName) : 'غير متوفر';
        $message = $this->buildWhatsAppMessage($store, $accountant, $reportData, $reportUrl);

        $encodedMessage = rawurlencode($message);
        $waUrl = "https://wa.me/{$cleanPhone}?text={$encodedMessage}";

        Cache::put($cacheKey, $todayMessages + 1, now()->addDay());

        return $waUrl;
    }

    /**
     * يبني اسم تقرير الإغلاق حسب طلب المالك:
     * - إذا كانت الملاحظات تحتوي تاريخًا رقميًا مثل 15-6 أو 15/6 نستعمل هذا التاريخ.
     * - إذا كانت الملاحظات فارغة أو نصًا عاديًا بدون تاريخ رقمي نستعمل تاريخ اليوم.
     */
    private function buildShiftReportTitle(string $storeName, ?string $notes): string
    {
        $datePart = $this->extractReportDateFromNotes($notes) ?? now()->format('j-n');

        return "تقرير اغلاق متجر {$storeName} {$datePart}";
    }

    /**
     * استخراج أول تاريخ رقمي من حقل الملاحظات بدون الاعتماد على أي تغيير في قاعدة البيانات.
     */
    private function extractReportDateFromNotes(?string $notes): ?string
    {
        $notes = trim((string) $notes);
        if ($notes === '') {
            return null;
        }

        return preg_match('/[0-9٠-٩]{1,2}\s*[-\/]\s*[0-9٠-٩]{1,2}(?:\s*[-\/]\s*[0-9٠-٩]{2,4})?/u', $notes, $matches)
            ? preg_replace('/\s+/u', '', $matches[0])
            : null;
    }

    private function buildWhatsAppMessage($store, $accountant, $reportData, $reportUrl)
{
    $date = $reportData['business_date'] ?? now()->format('Y-m-d');
    $time = now()->format('h:i A');

    $cashSales = (float) ($reportData['sales_breakdown']['cash_from_new_sales'] ?? 0);
    $cardSales = (float) ($reportData['sales_breakdown']['card_from_new_sales'] ?? 0);
    $salesCount = isset($reportData['details_tables']['all_sales']) && is_countable($reportData['details_tables']['all_sales'])
        ? count($reportData['details_tables']['all_sales'])
        : 0;
    $collectionsCount = (int) ($reportData['credit_collections']['count'] ?? 0);
    $operationsCount = $salesCount + $collectionsCount;
    $totalSales = (float) ($reportData['total_sales'] ?? 0);
    $totalOutgoing = (float) ($reportData['outgoing_today']['total'] ?? 0);
    $productsSalesValue = (float) ($reportData['products_details']['sales_value'] ?? 0);
    $productsCostValue = (float) ($reportData['products_details']['cost_value'] ?? 0);
    $recognizedProfit = (float) ($reportData['products_details']['recognized_profit'] ?? 0);
    $deferredProfit = (float) ($reportData['products_details']['deferred_profit'] ?? 0);
    $uncoveredCost = (float) ($reportData['products_details']['uncovered_cost'] ?? 0);
    $cashCollections = (float) ($reportData['credit_collections']['total'] ?? 0);
    $creditCollectionsTotal = (float) ($reportData['accountant_finance_movements']['credit_collections_total'] ?? $cashCollections);
    $financeCollectionRows = $reportData['accountant_finance_movements']['collection_rows'] ?? [];
    $creditCollectionRows = collect($financeCollectionRows)->where('collection_kind', 'credit')->values();
    $creditSaleRows = collect($reportData['details_tables']['all_sales'] ?? [])
        ->filter(fn ($sale) => ($sale['type'] ?? null) === 'credit' || ! empty($sale['has_partial_credit']))
        ->values();
    $newCreditSalesTotal = (float) $creditSaleRows->sum('remaining_amount');
    $financeDebtRows = $reportData['accountant_finance_movements']['debt_rows'] ?? [];
    $includedDebtRows = collect($reportData['accountant_finance_movements']['included_debt_rows'] ?? [])->pluck('id')->map(fn ($id) => (int) $id)->all();
    $includedDebtTotal = (float) ($reportData['accountant_finance_movements']['included_debt_total'] ?? 0);

    $message = "📊 *تقرير إقفال المتجر*\n";
    $message .= "🏪 " . $store->name . "\n";
    $message .= "👤 " . $accountant->name . "\n";
    $message .= "📅 التاريخ: " . $date . " | إصدار: " . $time . "\n\n";

    $message .= "🧾 *ملخص الشفت :*\n";
    if (empty($reportData['hide_period'])) {
        $message .= "🕒 الفترة: " . ($reportData['start_time'] ?? '-') . " → " . ($reportData['end_time'] ?? '-') . "\n";
    }
    if (!empty($reportData['notes'])) {
        $message .= "📝 ملاحظة الإغلاق: " . $reportData['notes'] . "\n";
    }
    $message .= "💰 اجمالي العمليات: " . number_format($totalSales, 2) . " ريال\n";
    $message .= "🛒 قيمة المبيعات (بسعر البيع): " . number_format($productsSalesValue, 2) . " ريال\n";
    $message .= "📦 قيمة التكلفة: " . number_format($productsCostValue, 2) . " ريال\n";
    $message .= "📈 الربح المحتسب: " . number_format($recognizedProfit, 2) . " ريال\n";
    if ($newCreditSalesTotal > 0 || $deferredProfit > 0 || $uncoveredCost > 0) {
        $message .= "🧾 بيع آجل: " . number_format($newCreditSalesTotal > 0 ? $newCreditSalesTotal : $deferredProfit, 2) . " ريال";
        if ($uncoveredCost > 0) {
            $message .= " | تكلفة غير مغطاة: " . number_format($uncoveredCost, 2) . " ريال";
        }
        $message .= "\n";
    }
    $message .= "💵 عمليات الكاش: " . number_format($cashSales, 2) . " ريال\n";
    $message .= "💳 عمليات الشبكة: " . number_format($cardSales, 2) . " ريال\n";
    if ($creditCollectionsTotal > 0) {
        $message .= "🤝 تحصيلات أجل: " . number_format($creditCollectionsTotal, 2) . " ريال\n";
    }
    if ($creditSaleRows->isNotEmpty()) {
        $message .= "\n🧾 *تفاصيل بيع آجل جديد:*\n";
        foreach ($creditSaleRows as $creditSaleRow) {
            $operationName = trim((string) ($creditSaleRow['operation_name'] ?? $creditSaleRow['labor_desc'] ?? '')) ?: 'بيع آجل';
            $message .= "• العملية: {$operationName} | المبلغ: "
                . number_format((float) ($creditSaleRow['remaining_amount'] ?? $creditSaleRow['total'] ?? 0), 2)
                . " ريال | الموظف: " . (($creditSaleRow['employee'] ?? '') ?: '—') . "\n";
        }
        $message .= "(هذه العمليات توضيحية ولا تدخل في مطابقة الكاش قبل التحصيل.)\n";
    }
    if ($creditCollectionRows->isNotEmpty()) {
        $message .= "\n🤝 *تفاصيل تحصيلات الآجل:*\n";
        foreach ($creditCollectionRows as $collectionRow) {
            $method = $collectionRow['payment_method_label'] ?? match ($collectionRow['payment_method'] ?? 'cash') {
                'card' => 'شبكة',
                'mixed' => 'ميكس',
                default => 'كاش',
            };
            $parts = ($collectionRow['payment_method'] ?? 'cash') === 'mixed'
                ? ' (كاش '.number_format((float) ($collectionRow['cash_amount'] ?? 0), 2).' / شبكة '.number_format((float) ($collectionRow['card_amount'] ?? 0), 2).')'
                : '';
            $message .= "• " . number_format((float) ($collectionRow['amount'] ?? 0), 2) . " ريال — {$method}{$parts} — " . ($collectionRow['employee_name'] ?? '—') . "\n";
        }
    }
    foreach ($financeDebtRows as $debtRow) {
        $isIncluded = in_array((int) ($debtRow['id'] ?? 0), $includedDebtRows, true);
        $message .= "🧾 إضافة مديونية بمبلغ " . number_format((float) ($debtRow['amount'] ?? 0), 2)
            . " ريال للموظف " . ($debtRow['employee_name'] ?? '—')
            . ($isIncluded ? " (مضافة للموازنة وتخصم من الكاش)\n" : " (توضيحي فقط خارج الحساب)\n");
    }
    $message .= "📤 مصاريف: " . number_format($totalOutgoing, 2) . " ريال\n";
    $message .= "🔢 عدد العمليات: " . number_format($operationsCount) . "\n\n";

    if (($reportData['labor_total'] ?? 0) > 0) {
        $message .= "👷 *أجرة اليد:* " . number_format((float) $reportData['labor_total'], 2) . " ريال\n\n";
    }

    $message .= "💵 *مطابقة الصندوق:*\n";
    $message .= "💰 الكاش المتوقع: " . number_format((float) ($reportData['cash_details']['expected'] ?? 0), 2) . " ريال\n";
    if ($includedDebtTotal > 0) {
        $message .= "➖ مديونيات مختارة ضمن الموازنة: " . number_format($includedDebtTotal, 2) . " ريال\n";
    }
    $message .= "💵 الكاش المستلم: " . number_format((float) ($reportData['cash_details']['actual'] ?? 0), 2) . " ريال\n";

    $diff = (float) ($reportData['cash_details']['difference'] ?? 0);
    if ($diff > 0) {
        $message .= "➕ فائض: " . number_format($diff, 2) . " ريال ✅\n";
    } elseif ($diff < 0) {
        $message .= "➖ عجز: " . number_format(abs($diff), 2) . " ريال ⚠️\n";
    } else {
        $message .= "✓ مطابق تماماً ✅\n";
    }

    if ($creditCollectionRows->isNotEmpty()) {
        $message .= "\n🤝 *تفاصيل تحصيلات أجل:*\n";
        foreach ($creditCollectionRows as $row) {
            $collectionType = (($row['type'] ?? '') === 'full_collection') ? 'كلي' : 'جزئي';
            $operationName = trim((string) ($row['operation_name'] ?? $row['credit_note'] ?? ''));
            if ($operationName === '') {
                $operationName = 'عملية أجل';
            }

            $operationValue = (float) ($row['operation_amount'] ?? 0);
            if ($operationValue <= 0) {
                $operationValue = (float) ($row['remaining_before_collection'] ?? 0) + (float) ($row['amount'] ?? 0);
            }

            $operationDate = $row['operation_date'] ?? null;
            $message .= "- تحصيل {$collectionType}: " . number_format((float) ($row['amount'] ?? 0), 2)
                . " ريال | من عملية: {$operationName}"
                . " | إجمالي قيمة الأجل: " . number_format(max(0, $operationValue), 2) . " ريال"
                . " | الموظف: " . ($row['employee_name'] ?? '—');
            if ($operationDate) {
                $message .= " | التاريخ: " . Carbon::parse($operationDate)->format('Y-m-d');
            }
            $message .= "\n";
        }
    }

    $message .= "\n📄 *تقرير PDF:*\n";
    $message .= $reportUrl;

    return $message;
}

    public function showReport($id)
    {
        $accountant = auth('accountant')->user();
        $balance = DailyBalance::where('store_id', $accountant->store_id)->findOrFail($id);

        $logDetails = Log::where('model_type', 'DailyBalance')
            ->where('model_id', $balance->id)
            ->where('action', 'balance_done')
            ->first();

        $data = $logDetails ? json_decode($logDetails->details, true) : [];

        return view('accountant.balance.report', [
            'balance' => $balance,
            'store' => $accountant->store,
            'accountant' => $accountant,
            'data' => $data,
        ]);
    }

    private function cleanupOldReports()
    {
        try {
            // سياسة الاحتفاظ: حذف تقارير PDF الأقدم من 10 أيام.
            $cutoffDate = now()->subDays(10)->getTimestamp();
            $folder = public_path('reports/');

            if (file_exists($folder)) {
                $files = glob($folder . 'Report_*.pdf');

                foreach ($files as $file) {
                    if (filemtime($file) < $cutoffDate) {
                        @unlink($file);
                    }
                }
            }
        } catch (\Exception $e) {
            \Log::error('Error cleaning up old reports: ' . $e->getMessage());
        }
    }
}
