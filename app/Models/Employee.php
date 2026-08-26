<?php

namespace App\Models;

use App\Models\CreditSale;
use App\Traits\BelongsToStore;
use App\Traits\HasEmployeeOperations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class Employee
 *
 * يمثل موظفًا داخل متجر معيّن.
 * الموظف يمكن أن يكون له:
 * - سحبيات
 * - مديونيات
 * - غيابات
 * - تقارير رواتب
 * - عمليات بيع آجل
 */
class Employee extends Model
{
    use SoftDeletes, BelongsToStore, HasEmployeeOperations;

    /**
     * الحقول القابلة للتعبئة
     */
    protected $fillable = [
        'user_id',      // مالك المتجر
        'store_id',     // المتجر التابع له الموظف
        'name',
        'phone',
        'salary',
        'status',       // active / inactive / suspended
        'notes',
        'added_by',     // من قام بإضافة الموظف (user أو accountant)
    ];

    /*
    |--------------------------------------------------------------------------
    | العلاقات
    |--------------------------------------------------------------------------
    */

    /**
     * علاقة الموظف مع مالك المتجر
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * علاقة الموظف مع حساب المحاسب (صلاحية دخول)
     * Employee hasOne Accountant
     */
   public function accountant()
{
    return $this->hasOne(Accountant::class)->withTrashed()->latestOfMany();
}

public function accountants()
{
    return $this->hasMany(Accountant::class)->withTrashed();
}

public function activeAccountant()
{
    return $this->hasOne(Accountant::class)->where('status', 'active')->latestOfMany();
}


    /**
     * سجلات الموظف (Logs)
     */
public function logs()
{
    return $this->morphMany(EmployeeLog::class, 'person');
}

public function archivedItem()
{
    return $this->morphOne(ArchivedItem::class, 'archivable');
}



    /**
     * علاقة polymorphic غير مستخدمة هنا (person)
     * تُترك كما هي
     */
    public function person()
    {
        return $this->morphTo();
    }

    /*
    |--------------------------------------------------------------------------
    | سكوبات
    |--------------------------------------------------------------------------
    */
public function creditSales()
{
    return $this->hasMany(CreditSale::class, 'person_id')
                ->where('person_type', self::class);
}
public function sales()
{
    return $this->hasMany(Sale::class);
}

    /**
     * سكوب لجلب الموظفين النشطين فقط
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * سكوب لجلب الموظفين للمتجر الحالي
     */
    public function scopeForCurrentStore($query)
    {
        $storeId = auth()->check()
            ? auth()->user()->current_store_id
            : (auth('accountant')->check() ? auth('accountant')->user()->store_id : null);

        if (!$storeId) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where('store_id', $storeId);
    }
}
