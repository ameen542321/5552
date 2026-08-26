<?php

namespace App\Http\Controllers\Store;

use Carbon\Carbon;
use App\Models\Debt;
use App\Models\Absence;
use App\Models\Employee;
use App\Models\CreditSale;
use App\Models\Withdrawal;
use App\Models\EmployeeLog;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Domain\EmployeeOperations\Exceptions\EmployeeOperationException;
use App\Domain\EmployeeOperations\Policies\EmployeeOperationAccessPolicy;
use App\Domain\EmployeeOperations\Services\EmployeeOperationService;
use App\Services\ShiftLifecycleService;

class EmployeeFinanceController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Helpers: findPerson + authorizePerson
    |--------------------------------------------------------------------------
    */
    private function findPerson($id)
    {
        return Employee::findOrFail($id);
    }

    private function authorizePerson($person): void
    {
        app(EmployeeOperationAccessPolicy::class)->authorizePerson($person);
    }


    /*
    |--------------------------------------------------------------------------
    | 2) تنفيذ عملية السحب (مع منع التكرار)
    |--------------------------------------------------------------------------
    */
    public function storeWithdrawal(Request $request, $id)
    {
        $person = $this->findPerson($id);
        $this->authorizePerson($person);

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'date' => 'required|date',
            'description' => 'nullable|string|max:255',
        ]);

        $employeeOperationService = app(EmployeeOperationService::class);

        try {
            $employeeOperationService->recordWithdrawal(
                $person,
                $validated,
                $employeeOperationService->actorFromCurrentAuth(),
                ['use_shift_gap_date' => true]
            );
        } catch (EmployeeOperationException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'تم إضافة السحب بنجاح');
    }



    /*
    |--------------------------------------------------------------------------
    | 3) تنفيذ عملية الغياب (جاهزة مسبقًا)
    |--------------------------------------------------------------------------
    */
    public function storeAbsence(Request $request, $id)
    {
        $person = $this->findPerson($id);
        $this->authorizePerson($person);

        $validated = $request->validate([
            'date' => 'required|date',
            'description' => 'nullable|string|max:255',
        ]);

        $employeeOperationService = app(EmployeeOperationService::class);

        try {
            $employeeOperationService->recordAbsence(
                $person,
                $validated,
                $employeeOperationService->actorFromCurrentAuth(),
                ['use_shift_gap_date' => true]
            );
        } catch (EmployeeOperationException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'تم تسجيل الغياب بنجاح');
    }



    /*
    |--------------------------------------------------------------------------
    | 5) تنفيذ عملية التحصيل (مع منع التكرار)
    |--------------------------------------------------------------------------
    */
public function storeCollection(Request $request, $saleId)
{
    $validated = $request->validate([
        'amount' => ['nullable', 'numeric', 'gt:0'],
        'payment_method' => ['nullable', 'in:cash,card,mixed'],
        'cash_amount' => ['nullable', 'numeric', 'min:0'],
        'card_amount' => ['nullable', 'numeric', 'min:0'],
    ]);

    $accountant = auth('accountant')->user();

    $sale = CreditSale::where('store_id', $accountant->store_id)
        ->where('id', $saleId)
        ->firstOrFail();

    $person = $sale->person;
    if (! $person) {
        return $this->collectionErrorResponse($request, 'لم يتم العثور على صاحب البيع الآجل.');
    }

    if ($person->id == $accountant->employee_id) {
        return $this->collectionErrorResponse($request, 'غير مصرح لك بتحصيل البيع الآجل الخاص بك.');
    }

    $isPartialCollection = array_key_exists('amount', $validated) && $validated['amount'] !== null;
    $collectionAmount = $isPartialCollection ? (float) $validated['amount'] : (float) $sale->remaining_amount;
    $paymentMethod = $validated['payment_method'] ?? 'cash';
    $cashAmount = $paymentMethod === 'card' ? 0.0 : ($paymentMethod === 'mixed' ? (float) ($validated['cash_amount'] ?? 0) : $collectionAmount);
    $cardAmount = $paymentMethod === 'cash' ? 0.0 : ($paymentMethod === 'mixed' ? (float) ($validated['card_amount'] ?? 0) : $collectionAmount);

    if ($paymentMethod === 'mixed' && abs(($cashAmount + $cardAmount) - $collectionAmount) > 0.01) {
        return $this->collectionErrorResponse($request, 'في تحصيل الميكس يجب أن يساوي مجموع الكاش والشبكة مبلغ التحصيل.');
    }

    try {
        app(EmployeeOperationService::class)->collectCreditSale(
            $sale,
            $collectionAmount,
            app(EmployeeOperationService::class)->actorFromCurrentAuth(),
            [
                'full' => ! $isPartialCollection,
                'use_shift_gap_date' => true,
                'payment_method' => $paymentMethod,
                'cash_amount' => $cashAmount,
                'card_amount' => $cardAmount,
            ]
        );
    } catch (EmployeeOperationException $exception) {
        return $this->collectionErrorResponse($request, $exception->getMessage());
    }

    if ($request->expectsJson() || $request->ajax() || $isPartialCollection) {
        return response()->json(['message' => 'تم تحصيل البيع الآجل بنجاح']);
    }

    return back()->with('success', 'تم تحصيل البيع الآجل بنجاح');
}






    /*
    |--------------------------------------------------------------------------
    | 7) تنفيذ عملية المديونية (مع منع التكرار)
    |--------------------------------------------------------------------------
    */
  public function storeDebt(Request $request, $id)
{
    $person = $this->findPerson($id);
    $this->authorizePerson($person);

    $validated = $request->validate([
        'amount'      => 'required|numeric|min:0.01',
        'description' => 'nullable|string|max:255',
        'date'        => 'required|date',
    ]);

    $employeeOperationService = app(EmployeeOperationService::class);

    try {
        $employeeOperationService->recordDebt(
            $person,
            $validated,
            $employeeOperationService->actorFromCurrentAuth(),
            ['use_accounting_date' => true, 'notify_store_owner' => true]
        );
    } catch (EmployeeOperationException $exception) {
        return back()->with('error', $exception->getMessage());
    }

    return back()->with('success', 'تم تسجيل المديونية بنجاح');
}




public function collectPartial(Request $request, $debtId)
{
    $validated = $request->validate([
        'amount' => ['required', 'numeric', 'gt:0'],
        'payment_method' => ['nullable', 'in:cash,card,mixed'],
        'cash_amount' => ['nullable', 'numeric', 'min:0'],
        'card_amount' => ['nullable', 'numeric', 'min:0'],
    ]);

    $debt = Debt::findOrFail($debtId);
    $person = $this->authorizeDebtAccess($debt);
    $accountant = auth('accountant')->user();

    if ($person->id == $accountant->employee_id) {
        return back()->with('error', 'غير مصرح لك بتحصيل مديونيتك الشخصية.');
    }

    try {
        app(EmployeeOperationService::class)->collectDebt(
            $debt,
            (float) $validated['amount'],
            app(EmployeeOperationService::class)->actorFromCurrentAuth(),
            [
                'use_accounting_date' => true,
                'notify_store_owner' => true,
                'payment_method' => $validated['payment_method'] ?? 'cash',
                'cash_amount' => (float) ($validated['cash_amount'] ?? 0),
                'card_amount' => (float) ($validated['card_amount'] ?? 0),
            ]
        );
    } catch (EmployeeOperationException $exception) {
        return back()->with('error', $exception->getMessage());
    }

    return back()->with('success', 'تم التحصيل الجزئي بنجاح');
}


public function collectFull(Request $request, $debtId)
{
    $validated = $request->validate([
        'payment_method' => ['nullable', 'in:cash,card,mixed'],
        'cash_amount' => ['nullable', 'numeric', 'min:0'],
        'card_amount' => ['nullable', 'numeric', 'min:0'],
    ]);

    $debt = Debt::findOrFail($debtId);
    $person = $this->authorizeDebtAccess($debt);
    $accountant = auth('accountant')->user();

    if ($person->id == $accountant->employee_id) {
        return back()->with('error', 'غير مصرح لك بتحصيل مديونيتك الشخصية.');
    }

    try {
        app(EmployeeOperationService::class)->collectDebt(
            $debt,
            (float) $debt->amount,
            app(EmployeeOperationService::class)->actorFromCurrentAuth(),
            [
                'use_accounting_date' => true,
                'notify_store_owner' => true,
                'full' => true,
                'payment_method' => $validated['payment_method'] ?? 'cash',
                'cash_amount' => (float) ($validated['cash_amount'] ?? 0),
                'card_amount' => (float) ($validated['card_amount'] ?? 0),
            ]
        );
    } catch (EmployeeOperationException $exception) {
        return back()->with('error', $exception->getMessage());
    }

    return back()->with('success', 'تم التحصيل الكامل بنجاح');
}




private function authorizeDebtAccess(Debt $debt)
{
    $person = $debt->person;

    if (! $person) {
        abort(404, 'لم يتم العثور على صاحب المديونية');
    }

    $this->authorizePerson($person);

    return $person;
}

private function collectionErrorResponse(Request $request, string $message)
{
    if ($request->expectsJson() || $request->ajax() || $request->has('amount')) {
        return response()->json(['error' => $message], 422);
    }

    return back()->with('error', $message);
}


public function getDebts($id)
{
    $person = Employee::findOrFail($id);
    $this->authorizePerson($person);

    // نظام المديونية/الأجل تراكمي: نعرض القائم فقط، أما ما أصبح صفرًا أو تمت تسويته فلا يظهر في قوائم الإدارة.
    $debts = $person->debts()
        ->where('amount', '>', 0)
        ->where('status', '!=', Debt::STATUS_DEDUCTED)
        ->withCount('collections')
        ->withSum('collections', 'amount')
        // توضيح: قائمة مديونيات الموظف داخل المودال ترتب حسب تاريخ العملية المرتبط بالسجل.
        ->orderByRaw('COALESCE(date, DATE(created_at)) DESC')
        ->orderByDesc('id')
        // created_at مطلوب كبديل آمن للسجلات القديمة التي لا تحتوي تاريخ عملية.
        ->get(['id', 'amount', 'description', 'date', 'status', 'created_at', 'updated_at']);

    // تنسيق البيانات قبل الإرجاع
    $debts->transform(function ($d) {
        return [
            'id'          => $d->id,
            'amount'      => (float) $d->amount,
            'description' => $d->description ?: null,
            // الأولوية لتاريخ العملية، ثم تاريخ إدخال السجل عند غيابه.
            'date'        => optional($d->date ?? $d->created_at)->format('Y-m-d'),
            'status'      => $d->status,
            'collected_at' => $d->status === Debt::STATUS_DEDUCTED ? optional($d->updated_at)->format('Y-m-d') : null,
            'has_partial_collection' => (int) ($d->collections_count ?? 0) > 0,
            'collected_amount' => abs((float) ($d->collections_sum_amount ?? 0)),
        ];
    });

    return response()->json($debts);
}
    /*
    |--------------------------------------------------------------------------
    | صفحات العرض (بدون تعديل)
    |--------------------------------------------------------------------------
    */
public function withdrawalPage()
{
    $storeId = auth('accountant')->user()->store_id;

    $people = Employee::where('store_id', $storeId)->get();

    $withdrawalBusinessDate = Carbon::parse(app(ShiftLifecycleService::class)->currentShiftContext($storeId)['business_date'] ?? now()->toDateString());
    $withdrawalMonthStart = $withdrawalBusinessDate->copy()->startOfMonth()->toDateString();
    $withdrawalMonthEnd = $withdrawalBusinessDate->copy()->endOfMonth()->toDateString();

    // السجل الجانبي للسحوبات شهري حسب التاريخ، حتى عند العمل على يوم مرجع.
    $lastWithdrawals = Withdrawal::where('store_id', $storeId)
        ->with(['person'])
        ->whereRaw('COALESCE(business_date, date, DATE(created_at)) BETWEEN ? AND ?', [$withdrawalMonthStart, $withdrawalMonthEnd])
        ->orderByRaw('COALESCE(business_date, date, DATE(created_at)) DESC')
        ->orderByDesc('id')
        ->take(10)
        ->get();

    return view('accountants.pos.withdrawals', compact('people', 'lastWithdrawals'));
}
    public function absencePage()
{
    $storeId = auth('accountant')->user()->store_id;

    $people = Employee::with('accountant')
        ->where('store_id', $storeId)
        ->where('status', 'active')
        ->orderBy('name')
        ->get()
        ->each(function ($person) {
            $person->role = $person->accountant ? 'accountant' : 'employee';
        });

    // توضيح: نعرض آخر 10 سجلات غياب حسب تاريخ العملية حتى تظهر مراجعة المحاسب مرتبطة بالتاريخ لا وقت الإدخال.
    $lastAbsences = Absence::where('store_id', $storeId)
        ->with(['person', 'employee'])
        ->orderByRaw('COALESCE(date, DATE(created_at)) DESC')
        ->orderByDesc('id')
        ->take(10)
        ->get();

    return view('accountants.pos.absence', compact('people', 'lastAbsences'));
}


    public function debtPage()
{
    $storeId = auth('accountant')->user()->store_id;

    $people = Employee::where('store_id', $storeId)
        ->with(['debts' => function ($query) {
            $query->where('amount', '>', 0)->select(['id', 'person_id', 'person_type', 'description']);
        }])
        ->withCount([
            'debts as active_debt_count' => function ($query) {
                $query->where('amount', '>', 0);
            },
        ])
        ->withSum([
            'debts as active_debt_total' => function ($query) {
                $query->where('amount', '>', 0);
            },
        ], 'amount')
        ->get();

    // سجل المديونيات تراكمي ودائم، ويخفي فقط السجلات التي أصبحت صفرًا أو تمت تسويتها.
    $lastDebts = Debt::where('store_id', $storeId)
        ->with(['person', 'addedBy'])
        ->where('amount', '!=', 0)
        ->where('status', '!=', Debt::STATUS_DEDUCTED)
        ->orderByRaw('COALESCE(date, DATE(created_at)) DESC')
        ->orderByDesc('id')
        ->take(10)
        ->get();

    return view('accountants.pos.debt', compact('people', 'lastDebts'));
}

public function collectionPage()
{
    $storeId = auth('accountant')->user()->store_id;

    // جلب الموظفين الذين لديهم عمليات بيع آجل معلّقة
    $people = Employee::where('store_id', $storeId)
        ->whereHas('creditSales', function ($q) {
            $q->where('status', 'pending');
        })
        ->get();

    // تجهيز بيانات البيع الآجل لكل موظف
    foreach ($people as $emp) {
        $emp->pending_credit_sales = $emp->creditSales()
            ->where('status', 'pending')
            ->with('addedBy')
            ->orderByRaw('COALESCE(date, DATE(created_at)) DESC')
            ->orderByDesc('id')
            ->get()
            ->map(function ($sale) {
                $linkedSale = $sale->resolveLinkedSale();
                $linkedSale?->load(['items.product', 'accountant']);

                return [
                    'id'               => $sale->id,
                    'amount'           => $sale->amount,
                    'remaining_amount' => $sale->remaining_amount ?? $sale->amount,
                    // يحافظ البديل على ظهور التاريخ في عمليات الأجل القديمة ناقصة التاريخ.
                    'date'             => optional($sale->date ?? $sale->created_at)->format('Y-m-d'),
                    'created_at'       => optional($sale->created_at)->format('Y-m-d H:i'),
                    'description'      => $sale->description,
                    'credit_note'      => $sale->credit_note,
                    'employee_name'    => $sale->person?->name,
                    'added_by_name'    => $sale->addedBy?->name,
                    'collection_payments' => $sale->collection_payments,
                    'linked_sale'      => $linkedSale ? [
                        'id' => $linkedSale->id,
                        'created_at' => optional($linkedSale->created_at)->format('Y-m-d H:i'),
                        'business_date' => optional($linkedSale->business_date)->format('Y-m-d'),
                        'accountant_name' => $linkedSale->accountant?->name,
                        'sale_type' => $linkedSale->sale_type,
                        'products_total' => (float) ($linkedSale->products_total ?? 0),
                        'labor_total' => (float) ($linkedSale->labor_total ?? 0),
                        'tax_rate' => (float) ($linkedSale->tax_rate ?? 0),
                        'final_total' => (float) ($linkedSale->final_total ?? $linkedSale->total ?? 0),
                        'paid_amount' => (float) ($linkedSale->paid_amount ?? 0),
                        'cash_amount' => (float) ($linkedSale->cash_amount ?? 0),
                        'card_amount' => (float) ($linkedSale->card_amount ?? 0),
                        'remaining_amount' => (float) ($linkedSale->remaining_amount ?? 0),
                        'description' => $linkedSale->description,
                        'items' => $linkedSale->items->map(fn ($item) => [
                            'name' => $item->historical_product_name,
                            'quantity' => (float) ($item->quantity ?? 0),
                            'price' => (float) ($item->price ?? 0),
                            'total' => (float) ($item->total ?? 0),
                            'unit_type' => $item->unit_type,
                            'is_splittable' => (bool) ($item->is_splittable_snapshot ?? $item->product?->is_splittable ?? false),
                            'is_custom' => (bool) ($item->is_custom ?? false),
                            'custom_meters' => (float) ($item->custom_meters ?? 0),
                        ])->values(),
                    ] : null,
                ];
            });
    }

    // سجل تحصيلات الأجل دائم وغير مرتبط بالشهر، وترتيبه حسب تاريخ العملية المسجل في meta.
    $lastCollections = EmployeeLog::where('store_id', $storeId)
        ->whereIn('action_name', ['credit_sale_deducted', 'credit_sale_partial'])
        ->with(['person'])
        ->orderByRaw('COALESCE(JSON_UNQUOTE(JSON_EXTRACT(meta, "$.operation_date")), DATE(created_at)) DESC')
        ->orderByDesc('id')
        ->take(10)
        ->get();

    return view('accountants.pos.collection', compact('people', 'lastCollections'));
}




}
