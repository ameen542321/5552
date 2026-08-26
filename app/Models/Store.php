<?php

namespace App\Models;

use App\Models\Sale;
use App\Models\User;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Category;
use App\Models\Employee;
use App\Models\SaleItem;
use App\Models\Accountant;
use App\Models\Withdrawal;
use App\Models\EmployeeDebt;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log as LaravelLog;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Store extends Model
{
    use HasFactory, SoftDeletes;

    public const DELETED_STORE_ACCOUNTANT_SUSPENSION_REASON = 'تم إيقاف الحساب تلقائيًا لأن المتجر نُقل إلى سلة المهملات.';

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'phone',
        'address',
        'logo',
        'slug',
        'status',
        'suspension_reason',
        'tax_number',
        'commercial_registration',
        'bank_accounts',
        'invoice_terms',
         'number_of_shifts',
         'shift_1_start',
         'shift_2_start',
    'shift_3_start',
     'force_shift_closure',
     'inventory_audit_cycle_months',
     'inventory_audit_start_mode',
     'inventory_audit_start_date',
     'labor_description_options',
    ];

    /**
     * تحويل الحقول إلى أنواع بيانات محددة تلقائياً
     */
    protected $casts = [
        'bank_accounts' => 'array', // ليتعامل مع الحسابات كـ Array بدلاً من نص
        'number_of_shifts' => 'integer',
        'force_shift_closure' => 'boolean',
        'inventory_audit_cycle_months' => 'integer',
        'inventory_audit_start_date' => 'date',
        'labor_description_options' => 'array',
    ];

    /*
    |--------------------------------------------------------------------------
    | العلاقات (Relationships)
    |--------------------------------------------------------------------------
    */

    public function user() { return $this->belongsTo(User::class); }
    public function accountants() { return $this->hasMany(Accountant::class); }
    public function categories() { return $this->hasMany(Category::class); }
    public function products() { return $this->hasMany(Product::class); }
    public function sales() { return $this->hasMany(Sale::class); }
    public function expenses() { return $this->hasMany(Expense::class); }
    public function stockMovements() { return $this->hasMany(StockMovement::class); }
    public function withdrawals() { return $this->hasMany(Withdrawal::class); }
    public function employees() { return $this->hasMany(Employee::class); }
    public function archivedItem() { return $this->morphOne(ArchivedItem::class, 'archivable'); }
    // داخل Model Store.php
public function saleItems()
{
    // علاقة "Has Many Through" تجلب البنود مباشرة عبر المبيعات
    return $this->hasManyThrough(SaleItem::class, Sale::class);
}

public function invoices()
{
    return $this->hasManyThrough(Invoice::class, Sale::class);
}

public function getLaborDescriptionOptionsListAttribute(): array
{
    $options = is_array($this->labor_description_options) ? $this->labor_description_options : [];

    $options = array_values(array_unique(array_filter(array_map(
        static fn ($option) => trim((string) $option),
        $options
    ))));

    return $options ?: ['تضليل', 'تجليد', 'شغل يد'];
}
    // إضافة علاقة الإعدادات إذا كانت موجودة في جداولك
    // public function settings() { return $this->hasOne(StoreSetting::class); }

    /*
    |--------------------------------------------------------------------------
    | دوال الوصول (Accessors & Mutators)
    |--------------------------------------------------------------------------
    */

    /**
     * جلب رابط الشعار كاملاً، وإذا لم يوجد نضع صورة افتراضية
     */
    public function getLogoUrlAttribute()
    {
        if ($this->logo && Storage::disk('public')->exists($this->logo)) {
            return asset('storage/' . $this->logo);
        }
        return asset('images/default-store.png'); // صورة افتراضية للمتجر
    }

    /**
     * جلب بريد المالك بشكل مباشر
     */
    public function getOwnerEmailAttribute()
    {
        return $this->user ? $this->user->email : 'N/A';
    }

    /**
     * نص الحسابات البنكية المسموح عرضه في الفواتير.
     *
     * نخفي الحسابات إذا كانت فارغة أو تحتوي أرقامًا صفرية فقط حتى لا تظهر حسابات
     * وهمية مثل SA0000000000000000000000 في الفاتورة.
     */
    public function getBankAccountsInfoAttribute(): ?string
    {
        $bankAccounts = $this->bank_accounts;
        $bankAccountLines = is_array($bankAccounts)
            ? array_filter(array_map(static function ($bankAccount): string {
                return trim(is_array($bankAccount) ? implode(' ', $bankAccount) : (string) $bankAccount);
            }, $bankAccounts))
            : array_filter([trim((string) $bankAccounts)]);

        $displayableBankAccountLines = array_filter($bankAccountLines, static function (string $bankAccountLine): bool {
            if ($bankAccountLine === '') {
                return false;
            }

            $digitsOnly = preg_replace('/\D+/', '', $bankAccountLine) ?? '';

            return $digitsOnly === '' || trim($digitsOnly, '0') !== '';
        });

        return empty($displayableBankAccountLines)
            ? null
            : implode(' | ', $displayableBankAccountLines);
    }

    /*
    |--------------------------------------------------------------------------
    | دوال المساعدة (Helper Functions)
    |--------------------------------------------------------------------------
    */

    /**
     * تحقق هل المتجر نشط أم لا
     */
    public function isActive()
    {
        return $this->status === 'active';
    }

    /**
     * نافذة الشفت المجدولة حسب إعدادات المتجر.
     *
     * توضيح: نظام الشفت يعتمد ماليًا على daily_balances بعد الإغلاق، لكن بعض الواجهات
     * تحتاج وصفًا زمنيًا للشفت المخطط قبل إغلاقه؛ لذلك تبقى هذه الدالة مساعدة فقط ولا
     * تنشئ شفتًا ماليًا ولا تربط عمليات.
     */
    public function scheduledShiftWindow($shiftNumber = 1, $businessDate = null): array
    {
        // توافق خلفي: بعض مواضع لوحة المحاسب كانت تستدعي الدالة بوقت التشغيل كأول وسيط.
        if ($shiftNumber instanceof \DateTimeInterface || is_string($shiftNumber)) {
            $businessDate = $shiftNumber;
            $shiftNumber = 1;
        }

        // الواجهة والخدمات المالية تدعم شفتًا واحدًا أو شفتين فقط حتى لو بقيت أعمدة قديمة لشفت ثالث في قاعدة البيانات.
        $maxShifts = max(1, min(2, (int) ($this->number_of_shifts ?: 1)));
        $shiftNumber = max(1, min($maxShifts, (int) $shiftNumber));
        $businessDate = $businessDate
            ? \Carbon\Carbon::parse($businessDate)->toDateString()
            : now()->toDateString();

        $starts = collect(range(1, $maxShifts))
            ->mapWithKeys(function (int $number) use ($businessDate) {
                $configured = $this->{'shift_'.$number.'_start'} ?: ($number === 1 ? '00:00:00' : null);

                return [$number => $configured ? \Carbon\Carbon::parse($businessDate.' '.$configured) : null];
            })
            ->filter();

        $start = $starts->get($shiftNumber) ?: \Carbon\Carbon::parse($businessDate)->startOfDay();
        $nextStart = $starts->get($shiftNumber + 1);
        $end = $nextStart
            ? $nextStart->copy()
            : $start->copy()->addHours((int) floor(24 / $maxShifts));

        if ($end->lessThanOrEqualTo($start)) {
            $end->addDay();
        }

        return [
            'shift_number' => $shiftNumber,
            'max_shifts' => $maxShifts,
            'business_date' => $businessDate,
            'start' => $start,
            'end' => $end,
        ];
    }

    public function inventoryAuditCycleMonths(): int
    {
        return in_array((int) $this->inventory_audit_cycle_months, [6, 12], true)
            ? (int) $this->inventory_audit_cycle_months
            : 6;
    }

    public function inventoryAuditAnchorDate(): \Carbon\Carbon
    {
        if (($this->inventory_audit_start_mode ?? 'store_created_at') === 'manual'
            && $this->inventory_audit_start_date) {
            return \Carbon\Carbon::parse($this->inventory_audit_start_date)->startOfDay();
        }

        return \Carbon\Carbon::parse($this->created_at ?? now())->startOfDay();
    }

    /**
     * حساب إجمالي المبيعات للمتجر (مثال لاستخدامه في التقارير)
     */
    public function totalSales()
    {
        return $this->sales()->sum('total_amount');
    }


    protected static function booted()
    {
        static::deleting(function (Store $store): void {
            if (! $store->isForceDeleting()) {
                return;
            }

            // كل صف بقي على store_id القديم يعد تابعًا للمتجر ويحذف نهائيًا؛ سجلات الموظف المنقول تكون قد نُقلت إلى متجره الجديد.
            foreach ([
                'employee_credit_collections',
                'credit_sales',
                'employee_absences',
                'employee_withdrawals',
                'debts',
                'employee_salary_reports',
                'employee_logs',
                'inventory_logs',
                'purchases',
                'store_settings',
                'logs',
            ] as $table) {
                if (Schema::hasTable($table) && Schema::hasColumn($table, 'store_id')) {
                    DB::table($table)->where('store_id', $store->id)->delete();
                }
            }

            // نحذف توابع المبيعات صراحةً حتى تعمل العملية أيضًا في قواعد البيانات القديمة التي لم تطبق كل قيود cascade.
            $store->sales()->each(function (Sale $sale): void {
                $sale->items()->delete();

                // قد تكون البيعة بلا فاتورة؛ فحص الموديل يمنع استدعاء delete على قيمة null.
                $invoice = $sale->invoice;
                if ($invoice) {
                    $invoice->delete();
                }

                $sale->delete();
            });

            // المحاسب حساب تشغيلي تابع للمتجر؛ الحذف النهائي يزيل الحسابات الحالية والمحذوفة مؤقتًا قبل موظفيها.
            $store->accountants()->withTrashed()->forceDelete();

            // withTrashed ضرورية حتى لا يبقى موظف حالي أو محذوف مؤقتًا تابعًا للمتجر القديم.
            $store->employees()->withTrashed()->each(function (Employee $employee): void {
                $employee->forceDelete();
            });

            $store->products()->withTrashed()->forceDelete();
            $store->categories()->withTrashed()->forceDelete();
            $store->expenses()->withTrashed()->forceDelete();
            $store->stockMovements()->delete();

            // حذف الملف بعد نجاح commit يمنع فقدان الشعار إذا تراجعت معاملة قاعدة البيانات.
            if ($store->logo) {
                $logo = $store->logo;
                DB::afterCommit(static function () use ($logo, $store): void {
                    try {
                        $deleted = Storage::disk('public')->delete($logo);
                    } catch (\Throwable $exception) {
                        LaravelLog::error('Store logo deletion failed after database commit.', [
                            'store_id' => $store->id,
                            'logo' => $logo,
                            'exception' => $exception,
                        ]);

                        return;
                    }

                    if (! $deleted) {
                        LaravelLog::warning('Store logo could not be deleted after force deletion.', [
                            'store_id' => $store->id,
                            'logo' => $logo,
                        ]);
                    }
                });
            }
        });
    }
}
