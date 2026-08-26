<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\BelongsToStore;

/**
 * Class Debt
 *
 * يمثل مديونية على موظف داخل متجر معيّن.
 * تحتوي على:
 * - قيمة المديونية
 * - نوعها (خصم، سلفة، إلخ)
 * - الشهر المحسوب عليه
 * - الشهر الذي سيتم الخصم فيه
 */
class Debt extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_DEDUCTED = 'deducted';

    protected $table = 'debts';
    use SoftDeletes, BelongsToStore;
public function person()
 {
     return $this->morphTo();
}
    /**
     * الحقول القابلة للتعبئة
     */
    protected $fillable = [
        'store_id',
        'person_id',        // الموظف
        'person_type',      // المتجر الذي حدثت فيه المديونية
        'employee_id',      // الموظف
        'debt_parent_id',   // أصل المديونية عند كون السجل تحصيلًا
        'amount',           // قيمة المديونية
        'description',      // وصف المديونية
        'payment_method',   // طريقة تحصيل المديونية
        'payment_method_label',
        'cash_amount',
        'card_amount',
        'type',             // loan, deduction, etc.
        'status',           // pending / deducted
        'month',            // الشهر الذي حدثت فيه المديونية
        'deducted_month',   // الشهر الذي سيتم الخصم فيه
        'added_by',         // من سجّل المديونية
        'date',             // تاريخ العملية
        'created_at',       // لضبط تاريخ إنشاء العملية حسب التاريخ المدخل
    ];


    protected $casts = [
        'date' => 'date',
    ];

    public function scopeForOperationDate($query, $date)
    {
        return $query->whereDate('date', $date);
    }

    public function scopeBetweenOperationDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('date', [
            Carbon::parse($startDate)->toDateString(),
            Carbon::parse($endDate)->toDateString(),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | العلاقات
    |--------------------------------------------------------------------------
    */

    /**
     * علاقة المديونية مع الموظف
     */
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function parentDebt()
    {
        // عند كون السجل حركة تحصيل (مبلغ سالب)، هذه العلاقة ترجع المديونية الأصلية التي تم التحصيل منها.
        return $this->belongsTo(self::class, 'debt_parent_id');
    }

    public function collections()
    {
        // عند كون السجل مديونية أصلية (مبلغ موجب)، هذه العلاقة ترجع كل حركات التحصيل المرتبطة بها.
        return $this->hasMany(self::class, 'debt_parent_id');
    }

    /**
     * علاقة المديونية مع المستخدم الذي سجّلها
     */
    public function addedBy()
    {
        return $this->belongsTo(User::class, 'added_by');
    }
}
