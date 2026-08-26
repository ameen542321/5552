<?php

namespace App\Http\Controllers\Employees;

use App\Support\ArabicPdf as PDF;
use App\Models\Debt;
use App\Traits\FindPersonTrait;
use App\Services\EmployeeLogService;
use Illuminate\Support\Carbon;
use App\Models\Employee;
use App\Services\Employees\EmployeePayrollService;

/**
 * --------------------------------------------------------------------------
 * EmployeeReports
 * --------------------------------------------------------------------------
 * هذا الملف مسؤول عن:
 * - تصدير تقارير PDF الخاصة بالموظف أو المحاسب
 * - يعتمد على FindPersonTrait لتحديد نوع الشخص (موظف / محاسب)
 * --------------------------------------------------------------------------
 */
class EmployeeReports
{
    use FindPersonTrait;

    /**
     * ----------------------------------------------------------------------
     * تصدير تقرير PDF باستخدام mPDF الداعم للعربية
     * ----------------------------------------------------------------------
     * - يقوم بجلب بيانات الشخص (موظف أو محاسب)
     * - يجمع جميع العمليات المرتبطة به
     * - ينشئ ملف PDF جاهز للتحميل
     * ----------------------------------------------------------------------
     */
    public static function exportPdf($id)
    {
        // إنشاء نسخة من الكلاس لاستخدام الـ trait
        $self = new self();

        // جلب الموظف أو المحاسب
        $person = $self->findPerson($id);

        $person->loadMissing('store.user');

        // الشهر المستهدف يأتي من فلتر صفحة العمليات، مع fallback محافظ للسلوك القديم.
        $requestedMonth = request()->query('month');
        $reportMonth = preg_match('/^\d{4}-\d{2}$/', (string) $requestedMonth) === 1
            ? Carbon::createFromFormat('Y-m-d', $requestedMonth . '-01')
            : (Carbon::now()->day <= 3 ? Carbon::now()->subMonthNoOverflow() : Carbon::now());
        $reportMonthKey = $reportMonth->format('Y-m');
        $monthStart = $reportMonth->copy()->startOfMonth()->toDateString();
        $monthEnd = $reportMonth->copy()->endOfMonth()->toDateString();
        $periodStart = Carbon::parse($monthStart)->startOfDay();
        $periodEnd = Carbon::parse($monthEnd)->endOfDay();

        // حسابات المديونية
        $remainingDebt = $person->debts()->sum('amount');

        // نعتمد على تاريخ العملية/الشفت، وليس تاريخ الإدخال، مع fallback موحد للسجلات القديمة.
        $debtOperations = $person->debts()
            ->with('addedBy')
            ->betweenOperationDates($monthStart, $monthEnd)
            ->orderBy('date')
            ->get();

        $collectedThisMonth = $debtOperations->where('amount', '<', 0)->sum('amount');
        $addedThisMonth = $debtOperations->where('amount', '>', 0)->sum('amount');

        // تقرير الموظف يعرض عمليات الشهر كاملة عبر كل المتاجر المرتبطة بالموظف، حتى لو تم نقله أثناء الشهر.
        $withdrawals = $person->withdrawals()
            ->with(['addedBy', 'store'])
            ->betweenAccountingDates($periodStart, $periodEnd)
            ->orderBy('business_date')
            ->orderBy('date')
            ->get();

        $absences = $person->absences()
            ->with(['addedBy', 'store'])
            ->betweenOperationDates($monthStart, $monthEnd)
            ->orderBy('date')
            ->get();

        $payrollService = app(EmployeePayrollService::class);
        $historicalSalary = $person instanceof Employee
            ? $payrollService->salaryAtPeriodEnd($person, $periodEnd)
            : (float) ($person->salary ?? 0);
        if ($person instanceof Employee) {
            $salaryInfo = $payrollService->salaryInfoWithSalaryChanges($person, $periodStart, $periodEnd);
        } else {
            $salaryInfo = [
                'payable_salary' => $historicalSalary,
                'worked_days' => $periodStart->daysInMonth,
                'suspended_days' => 0,
            ];
        }
        $absencePenalty = ($historicalSalary / max(1, $periodStart->daysInMonth)) * $absences->count();
        $salaryNet = max(0, (float) $salaryInfo['payable_salary'] - (float) $withdrawals->sum('amount') - $absencePenalty);

        $creditSalesPending = $person->creditSales()
            ->with('addedBy')
            ->betweenOperationDates($monthStart, $monthEnd)
            ->where('status', 'pending')
            ->orderBy('date')
            ->get();

        $creditSalesCollected = $person->creditSales()
            ->where('status', 'deducted')
            ->where(function ($query) use ($monthStart, $monthEnd, $reportMonthKey) {
                $query->where('deducted_month', $reportMonthKey)
                    ->orWhereBetween('date', [$monthStart, $monthEnd]);
            })
            ->with('addedBy')
            ->orderBy('date')
            ->get();

        $emptySections = collect([
            'السحوبات' => $withdrawals->isEmpty(),
            'الغيابات' => $absences->isEmpty(),
            'المديونيات والتحصيلات' => $debtOperations->isEmpty(),
            'البيع الآجل غير المحصل' => $creditSalesPending->isEmpty(),
            'البيع الآجل المحصل' => $creditSalesCollected->isEmpty(),
        ])->filter()->keys()->values();

        // تجهيز البيانات للعرض داخل الـ PDF
        $data = [
            'person'               => $person,
            'report_month'         => $reportMonthKey,
            'withdrawals'          => $withdrawals,
            'withdrawals_total'    => (float) $withdrawals->sum('amount'),
            'absences'             => $absences,
            'absences_count'       => $absences->count(),
            'absence_penalty'      => $absencePenalty,
            'salary_payable'       => (float) $salaryInfo['payable_salary'],
            'historical_salary'    => $historicalSalary,
            'salary_worked_days'   => (int) ($salaryInfo['worked_days'] ?? $periodStart->daysInMonth),
            'salary_total_days'    => (int) $periodStart->daysInMonth,
            'salary_net'           => $salaryNet,
            'debts'                => $debtOperations, // العمليات كاملة
            'remainingDebt'        => $remainingDebt,  // الرصيد النهائي
            'addedThisMonth'       => $addedThisMonth, // مجموع الإضافات
            'collectedThisMonth'   => abs($collectedThisMonth), // التحصيل الشهري (موجب)
            'creditSalesPending'   => $creditSalesPending,
            'creditSalesCollected' => $creditSalesCollected,
            'emptySections'        => $emptySections,
            'created_by'           => auth()->user(),
        ];

        // تسجيل عملية التصدير
        EmployeeLogService::add(
            $person,
            'report_exported',
            "تم تصدير تقرير PDF للموظف/المحاسب {$person->name} لشهر {$reportMonthKey}"
        );

        // إنشاء ملف PDF
        $pdf = PDF::loadView('pdf.employee-pdf', $data)
            ->setPaper('a4')
            ->setOption('encoding', 'UTF-8');

        // قرار مالي مثبت: تصدير PDF لا يحذف ولا يصفر أي عملية.
        // التقرير أصبح لقطة قراءة فقط؛ التحصيل أو التسوية تتم من شاشات العمليات المخصصة.
        session()->flash(
            'success',
            "تم تصدير التقرير بنجاح دون حذف أو تصفير أي بيانات للشهر {$reportMonthKey}."
        );

        return $pdf->download("تقرير {$reportMonthKey} - {$person->name}.pdf");
    }

    /**
     * 2026-05-16: دالة توافق مؤقتة لأي استدعاء قديم ما زال يستخدم اسم exportSnappy.
     */
    public static function exportSnappy($id)
    {
        return self::exportPdf($id);
    }
}
