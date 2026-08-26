<?php

namespace App\Domain\EmployeeOperations\ViewModels;

use App\Models\Accountant;
use App\Models\Employee;
use App\Models\User;
use App\Services\Employees\EmployeePayrollService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final class EmployeeOperationPageViewModel
{
    public function forPerson(Model $person, string $returnTo, ?string $month = null): array
    {
        $selectedMonth = $this->normalizeMonth($month);
        [$periodStart, $periodEnd] = $this->monthBounds($selectedMonth);
        $operationDetails = $this->operationDetails($person, $periodStart, $periodEnd);
        $operationSummary = $this->operationSummary($person, $operationDetails, $periodStart, $periodEnd);
        $person?->loadMissing(['accountant', 'activeAccountant']);
        $personLabel = $person instanceof Employee && $person->activeAccountant ? 'المحاسب' : 'الموظف';

        return [
            'employee' => $person,
            'personLabel' => $personLabel,
            'returnTo' => $returnTo,
            'selectedMonth' => $selectedMonth,
            'operationSummaryCards' => $this->operationSummaryCards($operationSummary),
            'operationDetails' => $operationDetails,
            'actionCards' => $this->actionCards($person, $selectedMonth),
            'recentLogs' => $this->paginatedLogs($operationDetails, $periodStart),
            'logActionMap' => $this->logActionMap(),
        ];
    }

    private function operationSummary(Model $person, array $details, Carbon $periodStart, Carbon $periodEnd): array
    {
        $payrollService = app(EmployeePayrollService::class);
        $historicalSalary = $person instanceof Employee
            ? $payrollService->salaryAtPeriodEnd($person, $periodEnd)
            : (float) ($person->salary ?? 0);

        if ($person instanceof Employee) {
            $salaryInfo = $payrollService->salaryInfoWithSalaryChanges($person, $periodStart, $periodEnd);
        } else {
            $salaryInfo = ['payable_salary' => $historicalSalary];
        }

        return [
            'withdrawals_total' => $details['withdrawals']->sum('amount'),
            'salary_payable' => (float) ($salaryInfo['payable_salary'] ?? 0),
            'historical_salary' => $historicalSalary,
            'worked_days' => (int) ($salaryInfo['worked_days'] ?? $periodStart->daysInMonth),
            'suspended_days' => (int) ($salaryInfo['suspended_days'] ?? 0),
            'debts_total' => $details['debts']->where('amount', '>', 0)->sum('amount'),
            'debt_collections_total' => abs((float) $details['debts']->where('amount', '<', 0)->sum('amount')),
            'credit_remaining_total' => $details['credit_sales']->sum('remaining_amount'),
            'credit_sales_total' => $details['credit_sales']->sum('amount'),
            'absences_count' => $details['absences']->count(),
        ];
    }

    private function operationSummaryCards(array $operationSummary): array
    {
        return [
            [
                'modal' => null,
                'label' => 'راتب الشهر',
                'value' => number_format($operationSummary['salary_payable'] ?? 0, 2),
                'suffix' => 'ريال',
                'color' => 'text-sky-300',
                'hint' => 'الأيام: ' . (int) ($operationSummary['worked_days'] ?? 0) . ' عمل / ' . (int) ($operationSummary['suspended_days'] ?? 0) . ' إيقاف',
            ],
            [
                'modal' => 'withdrawalsDetailsModal',
                'label' => 'إجمالي السحوبات',
                'value' => number_format($operationSummary['withdrawals_total'] ?? 0, 2),
                'suffix' => 'ريال',
                'color' => 'text-rose-300',
                'hint' => 'تفاصيل سحوبات الشهر المحدد',
            ],
            [
                'modal' => 'debtsDetailsModal',
                'label' => 'إجمالي المديونيات',
                'value' => number_format($operationSummary['debts_total'] ?? 0, 2),
                'suffix' => 'ريال',
                'color' => 'text-rose-300',
                'hint' => 'التحصيلات: ' . number_format($operationSummary['debt_collections_total'] ?? 0, 2) . ' ريال',
            ],
            [
                'modal' => 'creditSalesLogModal',
                'label' => 'عمليات الأجل',
                'value' => number_format($operationSummary['credit_remaining_total'] ?? 0, 2),
                'suffix' => 'ريال',
                'color' => 'text-violet-300',
                'hint' => 'إجمالي المتبقي من الأجل',
            ],
            [
                'modal' => 'absencesDetailsModal',
                'label' => 'أيام الغياب',
                'value' => (int) ($operationSummary['absences_count'] ?? 0),
                'suffix' => 'يوم',
                'color' => 'text-amber-300',
                'hint' => 'تفاصيل الغياب للشهر المحدد',
            ],
        ];
    }

    private function actionCards(Model $person, string $selectedMonth): array
    {
        // عناوين البطاقات تصف نطاق الإدارة داخل كل نافذة، أما التعديل الفعلي فيبقى مقيدًا بما تسمح به المسارات الحالية.
        return array_values(array_filter([
            ['modal' => 'withdrawalModal', 'title' => 'إدارة السحب اليومي', 'hint' => 'إضافة / تعديل', 'icon' => 'fa-money-bill-transfer', 'accent' => 'sky', 'type' => 'modal'],
            $person->status === 'active'
                ? ['modal' => 'absenceModal', 'title' => 'إدارة الغياب', 'hint' => 'إضافة / تعديل', 'icon' => 'fa-user-xmark', 'accent' => 'amber', 'type' => 'modal']
                : null,
            ['modal' => 'debtModal', 'title' => 'إدارة المديونية', 'hint' => 'عرض / إضافة / تحصيل', 'icon' => 'fa-hand-holding-dollar', 'accent' => 'rose', 'type' => 'modal'],
            ['modal' => 'creditSalesDetailsModal', 'title' => 'إدارة الأجل', 'hint' => 'عرض / تعديل', 'icon' => 'fa-file-invoice-dollar', 'accent' => 'violet', 'type' => 'modal'],
            ['url' => route('user.employees.exportLog', $person->id) . '?month=' . $selectedMonth, 'title' => 'إصدار تقرير شهري', 'hint' => 'تصدير PDF', 'icon' => 'fa-file-pdf', 'accent' => 'red', 'type' => 'link'],
            ['url' => route('user.employees.edit', $person->id), 'title' => 'تعديل البيانات', 'hint' => 'تحديث بيانات الموظف', 'icon' => 'fa-user-pen', 'accent' => 'indigo', 'type' => 'link'],
            [
                'url' => $person->status === 'active' ? route('user.employees.suspend', $person->id) : route('user.employees.activate', $person->id),
                'title' => 'إيقاف / تفعيل',
                'hint' => $person->status === 'active' ? 'إيقاف الموظف مؤقتًا' : 'إعادة تفعيل الموظف',
                'icon' => $person->status === 'active' ? 'fa-user-slash' : 'fa-user-check',
                'accent' => $person->status === 'active' ? 'orange' : 'emerald',
                'type' => 'status',
                'method' => 'PATCH',
                // نصوص SweetAlert مفصولة حتى تبقى نافذة التأكيد واضحة وسهلة التعديل من خدمة العرض.
                'confirm_title' => $person->status === 'active' ? 'إيقاف الموظف؟' : 'تفعيل الموظف؟',
                'confirm' => $person->status === 'active'
                    ? 'سيتم إيقاف الموظف ماليًا ووظيفيًا، وسيتم إيقاف حساب المحاسب المرتبط إن وجد.'
                    : 'سيتم تفعيل الموظف فقط واستئناف احتساب راتبه من تاريخ التفعيل.',
            ],
        ]));
    }

    private function logActionMap(): array
    {
        return [
            'withdrawal' => ['label' => 'سحب نقدي', 'color' => 'text-blue-400', 'icon' => 'fa-money-bill-transfer'],
            'absence' => ['label' => 'غياب', 'color' => 'text-yellow-400', 'icon' => 'fa-user-xmark'],
            'debt' => ['label' => 'مديونية', 'color' => 'text-red-400', 'icon' => 'fa-circle-exclamation'],
            'debt_collect_full' => ['label' => 'تحصيل مديونية كامل', 'color' => 'text-green-400', 'icon' => 'fa-hand-holding-dollar'],
            'debt_collect_partial' => ['label' => 'تحصيل مديونية جزئي', 'color' => 'text-green-400', 'icon' => 'fa-hand-holding-dollar'],
            'credit_sale' => ['label' => 'بيع آجل', 'color' => 'text-purple-400', 'icon' => 'fa-file-invoice-dollar'],
            'credit_sale_deducted' => ['label' => 'تحصيل بيع آجل كامل', 'color' => 'text-emerald-400', 'icon' => 'fa-sack-dollar'],
            'credit_sale_partial' => ['label' => 'تحصيل بيع آجل جزئي', 'color' => 'text-emerald-400', 'icon' => 'fa-sack-dollar'],
            'store_transfer' => ['label' => 'نقل بين المتاجر', 'color' => 'text-indigo-400', 'icon' => 'fa-right-left'],
            'salary_update' => ['label' => 'تعديل راتب', 'color' => 'text-gray-400', 'icon' => 'fa-sack-dollar'],
            'report_exported' => ['label' => 'تصدير تقرير', 'color' => 'text-red-400', 'icon' => 'fa-file-pdf'],
        ];
    }

    private function operationDetails(Model $person, Carbon $periodStart, Carbon $periodEnd): array
    {
        return [
            'withdrawals' => $person->withdrawals()
                ->with('addedBy:id,name')
                ->betweenAccountingDates($periodStart, $periodEnd)
                ->orderByRaw('COALESCE(business_date, date, DATE(created_at))')
                ->get()->each(fn ($operation) => $this->decorateOperationRow($operation)),
            'debts' => $person->debts()
                ->with('addedBy:id,name')
                // المديونية سجل تراكمي لا يرتبط بالشهر المحدد؛ نعرض العمليات القائمة والتحصيلات من الأحدث للأقدم.
                ->where('amount', '!=', 0)
                ->orderByRaw('COALESCE(date, DATE(created_at)) DESC')
                ->orderByDesc('id')
                ->get()->each(fn ($operation) => $this->decorateOperationRow($operation)),
            'credit_sales' => $person->creditSales()
                ->with('addedBy:id,name')
                // نظام الأجل تراكمي ولا يتبع فلتر الشهر؛ تظهر العمليات القائمة فقط حتى تُسوى أو تُحذف.
                ->where('remaining_amount', '>', 0)
                ->where('status', '!=', \App\Models\CreditSale::STATUS_DEDUCTED)
                ->orderByRaw('COALESCE(date, DATE(created_at)) DESC')
                ->orderByDesc('id')
                ->get()->each(fn ($operation) => $this->decorateOperationRow($operation)),
            'absences' => $person->absences()
                ->with('addedBy:id,name')
                ->betweenOperationDates($periodStart, $periodEnd)
                ->orderByRaw('COALESCE(date, DATE(created_at))')
                ->get()->each(fn ($operation) => $this->decorateOperationRow($operation)),
        ];
    }


    private function decorateOperationRow($operation): void
    {
        $operation->setAttribute('executed_by_name', $this->actorNameForOperation($operation));
        $accountingDate = $operation->business_date ?? $operation->date ?? $operation->created_at ?? null;
        $operation->setAttribute('accounting_date_display', $accountingDate ? Carbon::parse($accountingDate)->format('Y-m-d') : null);
    }

    private function paginatedLogs(array $details, Carbon $periodStart): LengthAwarePaginator
    {
        $operationLogRows = collect()
            ->merge($this->withdrawalLogRows($details['withdrawals']))
            ->merge($this->debtLogRows($details['debts']))
            ->merge($this->creditSaleLogRows($details['credit_sales']))
            ->merge($this->absenceLogRows($details['absences']))
            ->sortByDesc(fn ($operationLogRow) => $operationLogRow->meta['operation_date'] ?? '')
            ->values();

        $currentLogsPage = LengthAwarePaginator::resolveCurrentPage('logs_page');
        $logsPerPage = 10;

        return (new LengthAwarePaginator(
            $operationLogRows->forPage($currentLogsPage, $logsPerPage)->values(),
            $operationLogRows->count(),
            $logsPerPage,
            $currentLogsPage,
            ['pageName' => 'logs_page', 'path' => request()->url()]
        ))->appends(['month' => $periodStart->format('Y-m')]);
    }

    private function withdrawalLogRows(Collection $withdrawals): Collection
    {
        return $withdrawals->map(fn ($withdrawalOperation) => (object) [
            'action_name' => 'withdrawal',
            'description' => 'سحب نقدي ' . number_format((float) $withdrawalOperation->amount, 2) . ' ريال',
            'meta' => $this->rowMeta($withdrawalOperation, 'سحب', $withdrawalOperation->business_date ?? $withdrawalOperation->date ?? $withdrawalOperation->created_at),
        ]);
    }

    private function debtLogRows(Collection $debts): Collection
    {
        return $debts->map(function ($debtOperation) {
            $isDebtCollectionOperation = (float) $debtOperation->amount < 0;

            return (object) [
                'action_name' => $isDebtCollectionOperation ? 'debt_collect_partial' : 'debt',
                'description' => ($isDebtCollectionOperation ? 'تحصيل مديونية' : 'تسجيل مديونية') . ' بقيمة ' . number_format(abs((float) $debtOperation->amount), 2) . ' ريال',
                // السجلات القديمة قد لا تملك تاريخ عملية؛ تاريخ الإدخال هو البديل المعروض فقط.
                'meta' => $this->rowMeta($debtOperation, $isDebtCollectionOperation ? 'تحصيل مديونية' : 'مديونية', $debtOperation->date ?? $debtOperation->created_at),
            ];
        });
    }

    private function creditSaleLogRows(Collection $creditSales): Collection
    {
        return $creditSales->flatMap(function ($creditSaleOperation) {
            $creditSaleLogRows = collect([(object) [
                'action_name' => 'credit_sale',
                'description' => trim((string) ($creditSaleOperation->credit_note ?? '')) !== ''
                    ? trim((string) $creditSaleOperation->credit_note) . ' — آجل بقيمة ' . number_format((float) $creditSaleOperation->amount, 2) . ' ريال'
                    : 'بيع آجل بقيمة ' . number_format((float) $creditSaleOperation->amount, 2) . ' ريال',
                // نطبق قاعدة التاريخ نفسها على الأجل حتى تبقى القوائم متسقة.
                'meta' => $this->rowMeta($creditSaleOperation, 'آجل', $creditSaleOperation->date ?? $creditSaleOperation->created_at),
            ]]);

            foreach (($creditSaleOperation->collection_payments ?? []) as $creditSaleCollectionPayment) {
                $creditSaleLogRows->push((object) [
                    'action_name' => (($creditSaleCollectionPayment['description'] ?? '') === 'تحصيل كامل') ? 'credit_sale_deducted' : 'credit_sale_partial',
                    'description' => (($creditSaleCollectionPayment['description'] ?? '') === 'تحصيل كامل' ? 'تحصيل بيع آجل كامل' : 'تحصيل بيع آجل جزئي')
                        . (trim((string) ($creditSaleOperation->credit_note ?? '')) !== '' ? ' — ' . trim((string) $creditSaleOperation->credit_note) : '')
                        . ' بقيمة ' . number_format((float) ($creditSaleCollectionPayment['amount'] ?? 0), 2) . ' ريال',
                    'meta' => [
                        'type' => 'تحصيل آجل',
                        'actor_name' => $creditSaleCollectionPayment['added_by_name'] ?? 'غير محدد',
                        'operation_date' => isset($creditSaleCollectionPayment['date']) ? Carbon::parse($creditSaleCollectionPayment['date'])->format('Y-m-d') : null,
                    ],
                ]);
            }

            return $creditSaleLogRows;
        });
    }

    private function absenceLogRows(Collection $absences): Collection
    {
        return $absences->map(fn ($absenceOperation) => (object) [
            'action_name' => 'absence',
            'description' => 'تسجيل غياب',
            'meta' => $this->rowMeta($absenceOperation, 'غياب', $absenceOperation->date ?? $absenceOperation->created_at),
        ]);
    }

    private function rowMeta($employeeOperation, string $operationType, $operationDate): array
    {
        return [
            'type' => $operationType,
            'actor_name' => $this->actorNameForOperation($employeeOperation),
            'operation_date' => $operationDate ? Carbon::parse($operationDate)->format('Y-m-d') : null,
        ];
    }

    private function actorNameForOperation($employeeOperation): string
    {
        $actorId = $employeeOperation->added_by ?? null;

        if ($actorId) {
            return Accountant::find($actorId)?->name
                ?? $employeeOperation->addedBy?->name
                ?? User::find($actorId)?->name
                ?? 'غير محدد';
        }

        if ($employeeOperation->addedBy?->name) {
            return $employeeOperation->addedBy->name;
        }

        return 'غير محدد';
    }

    private function normalizeMonth(?string $month): string
    {
        return preg_match('/^\d{4}-\d{2}$/', (string) $month) === 1
            ? $month
            : now()->format('Y-m');
    }

    private function monthBounds(string $month): array
    {
        $start = Carbon::createFromFormat('Y-m-d', $month . '-01')->startOfMonth();

        return [$start, $start->copy()->endOfMonth()];
    }
}
