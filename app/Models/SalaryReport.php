<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\BelongsToStore;

/**
 * Class SalaryReport
 *
 * يمثل تقرير الراتب النهائي للموظف لشهر معيّن.
 * يحتوي على:
 * - الراتب الأساسي
 * - إجمالي السحبيات
 * - إجمالي الغيابات
 * - إجمالي المديونيات العادية
 * - إجمالي البيع الآجل
 * - الديون السابقة
 * - المكافآت
 * - الخصومات الإضافية
 * - الراتب النهائي
 */
class SalaryReport extends Model
{
    protected $table = 'employee_salary_reports';
    use SoftDeletes, BelongsToStore;

    /**
     * الحقول القابلة للتعبئة
     */
    protected $fillable = [
        'store_id',             // المتجر التابع له التقرير
        'employee_id',          // الموظف
        'user_id',              // من قام بإنشاء التقرير (المالك)
        'month',                // الشهر
        'year',                 // السنة
        'base_salary',          // الراتب الأساسي
        'total_withdrawals',    // مجموع السحبيات
        'total_absences',       // مجموع الغيابات
        'total_normal_debts',   // مجموع المديونيات العادية
        'total_credit_sales',   // مجموع البيع الآجل
        'previous_debts',       // الديون السابقة
        'bonus',                // المكافآت
        'extra_deduction',      // الخصومات الإضافية
        'final_salary',         // الراتب النهائي
        'notes',                // ملاحظات
    ];
public function person() { return $this->morphTo(); }
    /*
    |--------------------------------------------------------------------------
    | العلاقات
    |--------------------------------------------------------------------------
    */

    /**
     * علاقة التقرير مع الموظف
     */
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * علاقة التقرير مع المتجر (موروثة من BelongsToStore)
     * store()
     */

    /**
     * علاقة التقرير مع المستخدم الذي أنشأه
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
