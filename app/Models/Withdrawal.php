<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\BelongsToStore;
use App\Models\Concerns\HasAccountingDateScopes;

/**
 * Class Withdrawal
 *
 * يمثل سحبًا ماليًا قام به موظف داخل متجر معيّن.
 * يحتوي على:
 * - قيمة السحب
 * - الشهر المحسوب عليه
 * - الشهر الذي سيتم الخصم فيه
 * - حالة العملية
 */
class Withdrawal extends Model
{
    use SoftDeletes, BelongsToStore, HasAccountingDateScopes;

    protected $table = 'employee_withdrawals';

    /**
     * الحقول القابلة للتعبئة
     */
    protected $fillable = [
        'store_id',
        'person_id',
        'person_type',        // المتجر الذي حدث فيه السحب
        'employee_id',      // الموظف
        'amount',           // قيمة السحب
        'description',      // وصف العملية
        'date',             // تاريخ السحب
        'status',           // pending / deducted
        'month',            // الشهر الذي وقع فيه السحب
        'deducted_month',   // الشهر الذي سيتم الخصم فيه
        'added_by',         // من سجّل العملية
        'created_at',       // لضبط تاريخ الإنشاء حسب التاريخ المدخل
        'business_date',
        'daily_balance_id',
    ];

    protected $casts = [
        'date' => 'date',
        'business_date' => 'date',
    ];

    /*
    |--------------------------------------------------------------------------
    | العلاقات
    |--------------------------------------------------------------------------
    */

    /**
     * علاقة السحب مع الموظف
     */
    public function dailyBalance()
    {
        return $this->belongsTo(DailyBalance::class);
    }

    public function person()
    {
        return $this->morphTo();
    }

    /**
     * علاقة السحب مع المستخدم الذي سجّله
     */
    public function addedBy()
    {
        return $this->belongsTo(User::class, 'added_by');
    }
}
