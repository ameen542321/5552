<?php

namespace App\Traits;

use App\Models\Withdrawal;
use App\Models\Absence;
use App\Models\Debt;
use App\Models\CreditSale;
use App\Models\SalaryReport;

/**
 * --------------------------------------------------------------------------
 * HasEmployeeOperations
 * --------------------------------------------------------------------------
 * هذا الـ Trait يضيف للموظف أو المحاسب جميع العمليات المرتبطة به:
 * - السحوبات
 * - الغيابات
 * - الديون
 * - المبيعات الآجلة
 * - تقارير الرواتب
 *
 * جميع العلاقات تعمل بنظام polymorphic عبر الحقلين:
 * person_id + person_type
 * --------------------------------------------------------------------------
 */
trait HasEmployeeOperations
{
    /**
     * السحوبات
     */
    public function withdrawals()
    {
        return $this->morphMany(Withdrawal::class, 'person');
    }

    /**
     * الغيابات
     */
    public function absences()
    {
        return $this->morphMany(Absence::class, 'person');
    }

    /**
     * الديون
     */
    public function debts()
    {
        return $this->morphMany(Debt::class, 'person');
    }

    /**
     * المبيعات الآجلة
     */
    public function creditSales()
    {
        return $this->morphMany(CreditSale::class, 'person');
    }

    /**
     * تقارير الرواتب
     */
    public function salaryReports()
    {
        return $this->morphMany(SalaryReport::class, 'person');
    }
}
