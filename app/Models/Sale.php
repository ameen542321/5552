<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Concerns\HasAccountingDateScopes;

class Sale extends Model
{
    use HasAccountingDateScopes;

    protected $fillable = [
        'store_id',
        'employee_id',     // الموظف المرتبط بالدين (بديل credit_user_id)
        'accountant_id',   // المحاسب الذي أجرى العملية
        'sale_type',       // نوع البيع (cash, card, credit, mixed) ✅ تعديل: إضافة mixed
        'products_total',  // مجموع قطع الغيار فقط (قبل الضريبة)
        'tax_rate',        // نسبة الضريبة المطبقة (0 أو 15)
        'labor_total',     // أجور اليد (صافي - لا تخضع للضريبة حسب طلبك)
        'final_total',     // الإجمالي النهائي (المنتجات + ضريبتها + أجور اليد)
        'paid_amount',     // المبلغ المدفوع فعلياً
        // ✅ إضافة الحقول الجديدة
        'cash_amount',     // المبلغ المدفوع نقداً (للدعم المختلط)
        'card_amount',     // المبلغ المدفوع بالشبكة (للدعم المختلط)
        'remaining_amount',// المبلغ المتبقي (الدين)
        // ✅ إضافة حقل الآجل الجزئي
        'has_partial_credit', // هل يوجد آجل جزئي مع كاش أو شبكة؟
        'profit',
        'total',
        'internal_notes',
        'has_invoice',     // هل العميل طلب فاتورة ضريبية؟
        'description',     // وصف أجور اليد أو ملاحظات عامة
        'business_date',
        'daily_balance_id',
        'client_operation_id',
    ];

    /**
     * تحويل البيانات لضمان الدقة الحسابية عند التعامل مع المبالغ والضرائب
     */
    protected $casts = [
        'products_total'   => 'double',
        'labor_total'      => 'double',
        'final_total'      => 'double',
        'paid_amount'      => 'double',
        // ✅ إضافة الحقول الجديدة في الـ casts
        'cash_amount'      => 'double',
        'card_amount'      => 'double',
        'remaining_amount' => 'double',
        'tax_rate'         => 'integer',
        'has_invoice'      => 'boolean',
        // ✅ إضافة حقل الآجل الجزئي
        'has_partial_credit' => 'boolean',
        'profit'           => 'double',
        'business_date'    => 'date',
    ];

    // --- العلاقات (Relationships) ---

    // المتجر الذي تمت فيه العملية
    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    // المحاسب (User الموظف في النظام)
    public function accountant()
    {
        return $this->belongsTo(Accountant::class);
    }

    // الموظف المرتبط بالدين (تم اعتماده كبديل لـ creditUser)
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    // تفاصيل المنتجات في هذه البيعة
    public function items()
    {
        return $this->hasMany(SaleItem::class);
    }

    // الفاتورة المرتبطة بالعملية
    public function invoice()
    {
        return $this->hasOne(Invoice::class);
    }

    // ✅ إضافة علاقة مع CreditSale (اختياري)
    public function dailyBalance()
    {
        return $this->belongsTo(DailyBalance::class);
    }

    public function creditSales()
    {
        return $this->hasMany(CreditSale::class);
    }

    /**
     * استبعاد القيود اليدوية التي لا تدخل في مؤشرات المبيعات.
     */
    public function scopeExcludeManualInvoiceEntries($query)
    {
        return $query->where(function ($query) {
            $query->whereNull('description')
                ->orWhere('description', '!=', 'manual_invoice_entry');
        });
    }

    /**
     * أنواع البيع التي تمثل مبالغ محصلة في لوحة المالك.
     */
    public function scopeCollectedDashboardSales($query)
    {
        return $query
            ->whereIn('sale_type', ['cash', 'card', 'credit', 'mixed'])
            ->excludeManualInvoiceEntries();
    }
}
