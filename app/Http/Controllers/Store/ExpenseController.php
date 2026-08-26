<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\Accountant;
use App\Models\Expense;
use App\Models\Notification;
use App\Models\Store;
use App\Models\User;
use App\Services\EmployeeLogService;
use App\Support\ArabicPdf;
use App\Services\ShiftLifecycleService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExpenseController extends Controller
{
    public function index(Request $request, ?int $store = null)
    {
        [$storeModel, $storeId, $accountant, $user] = $this->resolveStoreContext($store);

        $isAccountant = (bool) $accountant;
        $shiftContext = app(ShiftLifecycleService::class)->currentShiftContext($storeId);
        $currentBusinessDate = Carbon::parse($shiftContext['business_date'])->toDateString();
        $validatedFilter = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $selectedAccountingDate = Carbon::parse($validatedFilter['date'] ?? $currentBusinessDate)->toDateString();

        // الفلتر واليوم الافتراضي كلاهما يعتمدان تاريخ العملية، لا وقت created_at.
        $month = Carbon::parse($selectedAccountingDate)->month;
        $year = Carbon::parse($selectedAccountingDate)->year;

        $monthExpensesQuery = $this->expensesForAccountingMonth($storeId, $month, $year);
        $monthTotal = (float) (clone $monthExpensesQuery)->sum('amount');

        $selectedDayExpensesQuery = Expense::query()
            ->where('store_id', $storeId)
            ->forAccountingDate($selectedAccountingDate);
        $currentTotal = (float) (clone $selectedDayExpensesQuery)->sum('amount');

        $expenses = $selectedDayExpensesQuery
            ->orderByDesc('business_date')
            ->latest()
            ->get();

        $this->attachExpenseViewMetadata($expenses, $accountant, $user);

        return view('accountants.pos.expense', [
            'expenses' => $expenses,
            'total' => $monthTotal,
            'monthTotal' => $monthTotal,
            'currentTotal' => $currentTotal,
            'month' => $month,
            'year' => $year,
            'currentBusinessDate' => $currentBusinessDate,
            'selectedAccountingDate' => $selectedAccountingDate,
            'storeModel' => $storeModel,
            'isAccountant' => $isAccountant,
        ]);
    }


    private function expensesForAccountingMonth(int $storeId, int $month, int $year): Builder
    {
        $monthStart = Carbon::create($year, $month, 1)->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();

        return Expense::query()
            ->where('store_id', $storeId)
            ->betweenAccountingDates($monthStart, $monthEnd);
    }

    public function store(Request $request, ?int $store = null)
    {
        [$storeModel, $storeId, $accountant, $user, $actor] = $this->resolveStoreContext($store, true);

        $validated = $request->validate([
            'type' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'description' => 'nullable|string|max:500',
            'business_date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $isAccountant = (bool) $accountant;
        $type = trim((string) $validated['type']);
        $operationNote = trim((string) ($validated['description'] ?? ''));
        $description = $operationNote;
        // توضيح: عمود description في بعض قواعد البيانات غير قابل لـ NULL، لذلك نستخدم اسم المصروف كنص احتياطي عند ترك الملاحظات فارغة.
        $description = $description !== '' ? $description : $type;

        $shiftContext = app(ShiftLifecycleService::class)->currentShiftContext($storeId);
        $expenseDate = $isAccountant
            ? $shiftContext['business_date']
            : ($validated['business_date'] ?? $shiftContext['business_date']);
        $amount = number_format((float) $validated['amount'], 2);

        DB::transaction(function () use ($storeId, $storeModel, $actor, $isAccountant, $validated, $type, $description, $operationNote, $shiftContext, $expenseDate, $amount) {
            Expense::create([
                'store_id' => $storeId,
                'user_id' => $actor->id,
                'type' => $type,
                'description' => $description,
                'amount' => $validated['amount'],
                'actor_type' => $isAccountant ? 'accountant_expense' : 'owner_expense',
                'business_date' => $expenseDate,
                'daily_balance_id' => $expenseDate === $shiftContext['business_date'] ? $shiftContext['daily_balance_id'] : null,
            ]);

            $message = "قام {$actor->name} بتسجيل مصروف {$type} بقيمة {$amount} ريال";
            if ($operationNote !== '') {
                $message .= " — ملاحظة: {$operationNote}";
            }

            if ($isAccountant) {
                EmployeeLogService::add($actor, 'expense_added', $message, $validated['amount'], 'operation');
            } else {
                \App\Helpers\LogHelper::add('expense_added', $message, $storeId);
            }

            Notification::create([
                'sender_id' => $actor->id,
                'sender_type' => $isAccountant ? 'accountant' : 'user',
                'target_type' => 'user',
                'target_ids' => [$storeModel->user_id],
                'title' => 'مصروف جديد',
                'message' => $message,
                'template_key' => 'expense_added',
                'channel' => 'CARLED',
            ]);
        });

        return back()->with('success', 'تم تسجيل المصروف بنجاح');
    }

    public function exportPdf(Request $request, ?int $store = null)
    {
        if (auth('accountant')->check()) {
            abort(403, 'المحاسب لا يملك صلاحية تصدير المصروفات.');
        }

        [$storeModel, $storeId] = $this->resolveStoreContext($store);

        $month = max(1, min(12, (int) ($request->month ?? now()->month)));
        $year = (int) ($request->year ?? now()->year);

        $expenses = $this->expensesForAccountingMonth($storeId, $month, $year)
            ->orderByDesc('business_date')
            ->latest()
            ->get();

        $data = [
            'store' => $storeModel,
            'expenses' => $expenses,
            'month' => $month,
            'year' => $year,
            'total' => (float) $expenses->sum('amount'),
            'generatedAt' => now()->format('Y-m-d H:i'),
        ];

        $pdf = ArabicPdf::loadView('pdf.expense-consumption', $data)
            ->setOption('encoding', 'utf-8')
            ->setOption('margin-top', 10)
            ->setOption('margin-bottom', 10);

        return $pdf->download("تقرير-المصروفات-{$year}-" . str_pad((string) $month, 2, '0', STR_PAD_LEFT) . ".pdf");
    }

    public function destroy($storeOrId, $id = null)
    {
        $accountant = auth('accountant')->user();
        $owner = auth()->user();

        if (!$accountant && !$owner) {
            abort(403, 'غير مصرح بالدخول');
        }

        $expenseId = $id ?? $storeOrId;
        $expense = Expense::findOrFail($expenseId);
        $this->ensureRouteStoreMatchesExpense($expense);

        if (!$this->canMutateExpense($expense, $accountant, $owner)) {
            abort(403, 'لا يمكن حذف المصروف إلا بواسطة المالك أو المحاسب الذي أضافه قبل إغلاق الشفت.');
        }

        $expense->delete();

        return back()->with('success', 'تم حذف المصروف بنجاح');
    }

    public function update(Request $request, $storeOrId, $id = null)
    {
        $accountant = auth('accountant')->user();
        $owner = auth()->user();

        if (!$accountant && !$owner) {
            abort(403, 'غير مصرح بالدخول');
        }

        $expenseId = $id ?? $storeOrId;
        $expense = Expense::findOrFail($expenseId);
        $this->ensureRouteStoreMatchesExpense($expense);

        if (!$this->canMutateExpense($expense, $accountant, $owner)) {
            abort(403, 'لا يمكن تعديل المصروف إلا بواسطة المالك أو المحاسب الذي أضافه قبل إغلاق الشفت.');
        }

        $request->validate([
            'type' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'description' => 'nullable|string|max:500',
        ]);

        $type = trim((string) $request->type);
        $description = trim((string) $request->description);

        $expense->update([
            'type' => $type,
            'amount' => $request->amount,
            // توضيح: لا نرسل NULL للوصف حتى لا تفشل قواعد البيانات القديمة التي تجعل العمود إلزامياً.
            'description' => $description !== '' ? $description : $type,
        ]);

        return back()->with('success', 'تم تعديل المصروف بنجاح');
    }


    private function attachExpenseViewMetadata($expenses, ?Accountant $currentAccountant, ?User $currentOwner): void
    {
        if ($expenses->isEmpty()) {
            return;
        }

        $accountantIds = $expenses
            ->filter(fn (Expense $expense) => $expense->actor_type === 'accountant_expense' || $expense->actor_type === 'operational_expense')
            ->pluck('user_id')
            ->filter()
            ->unique()
            ->values();

        $ownerIds = $expenses
            ->filter(fn (Expense $expense) => $expense->actor_type === 'owner_expense' || $expense->actor_type === 'operational_expense')
            ->pluck('user_id')
            ->filter()
            ->unique()
            ->values();

        $accountants = Accountant::whereIn('id', $accountantIds)->get()->keyBy('id');
        $owners = User::whereIn('id', $ownerIds)->get()->keyBy('id');

        $expenses->each(function (Expense $expense) use ($accountants, $owners, $currentAccountant, $currentOwner) {
            $creatorName = null;

            if ($expense->actor_type === 'accountant_expense') {
                $creatorName = $accountants->get($expense->user_id)?->name;
            } elseif ($expense->actor_type === 'owner_expense') {
                $creatorName = $owners->get($expense->user_id)?->name;
            } else {
                $creatorName = $accountants->get($expense->user_id)?->name
                    ?: $owners->get($expense->user_id)?->name;
            }

            $expense->setAttribute('creator_name', $creatorName ?: 'غير محدد');
            $expense->setAttribute('can_mutate', $this->canMutateExpense($expense, $currentAccountant, $currentOwner));
            $expense->setAttribute('locked_note', $expense->daily_balance_id ? 'العملية مرتبطة بموازنة مغلقة؛ للحذف والتعديل تواصل مع الإدارة.' : null);
        });
    }

    private function canMutateExpense(Expense $expense, ?Accountant $accountant, ?User $owner): bool
    {
        $store = Store::find($expense->store_id);

        if ($owner && $store && (int) $store->user_id === (int) $owner->id) {
            return true;
        }

        if (!$accountant || (int) $expense->store_id !== (int) $accountant->store_id) {
            return false;
        }

        if ($expense->daily_balance_id) {
            return false;
        }

        return (int) $expense->user_id === (int) $accountant->id;
    }

    private function ensureRouteStoreMatchesExpense(Expense $expense): void
    {
        $routeStore = request()->route('store');
        $routeStoreId = is_object($routeStore) ? (int) $routeStore->id : (int) ($routeStore ?? 0);

        if ($routeStoreId > 0 && (int) $expense->store_id !== $routeStoreId) {
            abort(404);
        }
    }

    private function resolveStoreContext(?int $store = null, bool $withActor = false): array
    {
        $accountant = auth('accountant')->user();
        $user = auth()->user();
        $actor = $accountant ?: $user;

        if (!$actor) {
            abort(403, 'غير مصرح بالدخول');
        }

        if ($store) {
            $storeModel = Store::findOrFail($store);
        } elseif ($accountant) {
            $storeModel = Store::findOrFail((int) $accountant->store_id);
        } else {
            $currentStoreId = (int) ($user->current_store_id ?? 0);
            $storeModel = null;

            if ($currentStoreId > 0 && $user->stores()->whereKey($currentStoreId)->exists()) {
                $storeModel = Store::find($currentStoreId);
            }

            $storeModel ??= $user->stores()->orderBy('id')->first();

            if (!$storeModel) {
                abort(404, 'لا يوجد متجر مرتبط بهذا المالك.');
            }
        }

        if ($accountant && (int) $storeModel->id !== (int) $accountant->store_id) {
            abort(403, 'لا تملك صلاحية الوصول لهذا المتجر');
        }

        if (!$accountant && (int) $storeModel->user_id !== (int) $user->id) {
            abort(403, 'لا تملك صلاحية الوصول لهذا المتجر');
        }

        $payload = [$storeModel, (int) $storeModel->id, $accountant, $user];
        if ($withActor) {
            $payload[] = $actor;
        }

        return $payload;
    }
}
