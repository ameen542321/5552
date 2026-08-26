<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Traits\BelongsToStore;

/**
 * ClassCreditSale
 *
 * يمثل عملية بيع آجل قام بها موظف داخل متجر معيّن.
 * تحتوي على:
 * - قيمة البيع
 * - الشهر المحسوب عليه
 * - الشهر الذي سيتم الخصم فيه
 * - حالة العملية
 */
class CreditSale extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_DEDUCTED = 'deducted';

    protected $table = 'credit_sales';
    use SoftDeletes, BelongsToStore;

    /**
     * الحقول القابلة للتعبئة
     */
  protected $fillable = [
    'person_id',
    'person_type',
    'store_id',
    'sale_id',
    'amount',
    'remaining_amount',
    'description',
    'credit_note',
    'operation_name',
    'date',
    'status',
    'month',
    'deducted_month',
    'added_by',
];

public function person() { return $this->morphTo(); }

public function sale()
{
    return $this->belongsTo(Sale::class);
}

public function collections()
{
    return $this->hasMany(CreditCollection::class);
}

public function getCollectionPaymentsAttribute()
{
    if (! Schema::hasTable('employee_credit_collections')) {
        return collect();
    }

    return DB::table('employee_credit_collections')
        ->leftJoin('users', 'users.id', '=', 'employee_credit_collections.collected_by')
        ->where('employee_credit_collections.credit_sale_id', $this->id)
        ->orderBy('employee_credit_collections.collection_date')
        ->orderBy('employee_credit_collections.id')
        ->select([
            'employee_credit_collections.id',
            'employee_credit_collections.amount',
            'employee_credit_collections.collection_date',
            'employee_credit_collections.collected_by',
            'employee_credit_collections.payment_method',
            'employee_credit_collections.payment_method_label',
            'employee_credit_collections.cash_amount',
            'employee_credit_collections.card_amount',
            'employee_credit_collections.meta',
            'users.name as collected_by_name',
        ])
        ->get()
        ->map(function ($collection) {
            $meta = json_decode($collection->meta ?: '[]', true);
            $meta = is_array($meta) ? $meta : [];

            return [
                'id' => $collection->id,
                'amount' => (float) $collection->amount,
                'date' => $collection->collection_date ? Carbon::parse($collection->collection_date)->format('Y-m-d') : null,
                'added_by' => $collection->collected_by,
                'added_by_name' => $collection->collected_by_name ?: ($meta['added_by_name'] ?? null),
                'description' => $meta['description'] ?? ($collection->payment_method_label ? 'تحصيل آجل - ' . $collection->payment_method_label : 'تحصيل آجل'),
                'payment_method' => $collection->payment_method,
                'payment_method_label' => $collection->payment_method_label,
                'cash_amount' => (float) $collection->cash_amount,
                'card_amount' => (float) $collection->card_amount,
            ];
        })->values();
}

    public function getOperationNameAttribute(): ?string
    {
        return $this->credit_note;
    }

    public function setOperationNameAttribute($value): void
    {
        $this->attributes['credit_note'] = $value;
    }

    /*
    |--------------------------------------------------------------------------
    | العلاقات
    |--------------------------------------------------------------------------
    */
protected $casts = [
    'sale_id' => 'integer',
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

    /**
     * علاقة العملية مع الموظف
     */
    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    /**
     * علاقة العملية مع المتجر (موروثة من BelongsToStore)
     * store()
     */

    /**
     * علاقة العملية مع المستخدم الذي سجّلها
     */
    public function addedBy()
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    public function resolveLinkedSaleId(): ?int
    {
        if (!empty($this->sale_id)) {
            return (int) $this->sale_id;
        }

        if (preg_match('/#(\d+)/', (string) $this->description, $matches) === 1) {
            return (int) $matches[1];
        }

        return null;
    }

    public function resolveLinkedSale(): ?Sale
    {
        $saleId = $this->resolveLinkedSaleId();

        if (!$saleId) {
            return null;
        }

        return Sale::query()
            ->where('id', $saleId)
            ->where('store_id', $this->store_id)
            ->first();
    }

    public function syncLinkedSaleCollectionState(): void
    {
        $sale = $this->resolveLinkedSale();

        if (!$sale) {
            return;
        }

        $basePaidAmount = max(0, (float) ($sale->cash_amount ?? 0) + (float) ($sale->card_amount ?? 0));
        $remainingAmount = max(0, (float) ($this->remaining_amount ?? 0));
        $collectedCreditAmount = max(0, (float) ($this->amount ?? 0) - $remainingAmount);

        $sale->remaining_amount = $remainingAmount;
        $sale->paid_amount = min((float) ($sale->final_total ?? 0), $basePaidAmount + $collectedCreditAmount);
        $sale->has_partial_credit = $remainingAmount > 0 && $sale->sale_type !== 'credit';
        $sale->save();
    }
}
