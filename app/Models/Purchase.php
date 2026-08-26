<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\BelongsToStore;

/**
 * Class Purchase
 *
 * يمثل عملية شراء (إضافة مخزون) داخل متجر معيّن.
 */
class Purchase extends Model
{
    use SoftDeletes, BelongsToStore;

    protected $fillable = [
        'store_id',
        'user_id',
        'product_id',
        'product_name_snapshot',
        'purchase_name',
        'quantity',
        'cost',
        'description',
        'business_date',
    ];

    protected $casts = [
        'business_date' => 'date',
    ];

    protected static function booted(): void
    {
        static::creating(function (Purchase $purchase): void {
            $purchase->product_name_snapshot ??= $purchase->purchase_name
                ?: ($purchase->product_id ? Product::find($purchase->product_id)?->name : null);
        });
    }

    /*
    |--------------------------------------------------------------------------
    | العلاقات
    |--------------------------------------------------------------------------
    */

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function archivedItem()
    {
        return $this->morphOne(ArchivedItem::class, 'archivable');
    }
}
