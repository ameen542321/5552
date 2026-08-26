<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\BelongsToStore;

/**
 * Class EmployeeLog
 *
 * يمثل سجلًا لأي عملية تمت على الموظف:
 * - سحب
 * - غياب
 * - خصم
 * - إضافة
 * - تعديل راتب
 */
class EmployeeLog extends Model
{
    use HasFactory, SoftDeletes, BelongsToStore;

    /**
     * الحقول القابلة للتعبئة
     */
    protected $fillable = [
    'person_id',     // رقم الكيان (موظف أو محاسب)
    'person_type',   // نوع الكيان (App\Models\Employee أو App\Models\Accountant)
    'store_id',      // المتجر
    'action_name',   // نوع العملية (credit_sale_deducted, credit_sale_partial ...)
    'amount',        // المبلغ
    'description',   // وصف العملية
    'meta',          // بيانات إضافية JSON
];


    /**
     * تحويلات تلقائية
     */
   

    protected $casts = [
        'logged_at' => 'datetime',
        'meta' => 'array',
    ];

    public function person()
{
    return $this->morphTo();
}

    /*
    |--------------------------------------------------------------------------
    | العلاقات
    |--------------------------------------------------------------------------
    */

    /**
     * علاقة السجل مع الموظف
     */
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
public function loggable()
{
    return $this->morphTo();
}


    /**
     * علاقة السجل مع المستخدم الذي سجّله (اختياري)
     */
    public function addedBy()
    {
        return $this->belongsTo(User::class, 'added_by');
    }
}
