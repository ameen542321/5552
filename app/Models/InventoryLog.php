<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\BelongsToStore;

class InventoryLog extends Model
{
    use SoftDeletes, BelongsToStore;

    protected $fillable = [
        'store_id',
        'user_id',
        'product_id',
        'product_name_snapshot',
        'quantity_change',
        'quantity_snapshot',
        'type',
        'business_date',
    ];

    protected $casts = [
        'business_date' => 'date',
        'quantity_snapshot' => 'float',
    ];

    protected static function booted(): void
    {
        static::creating(function (InventoryLog $log): void {
            $log->product_name_snapshot ??= $log->product_id
                ? Product::find($log->product_id)?->name
                : null;
            $log->quantity_snapshot ??= $log->quantity_change;
        });
    }

    /*
    |--------------------------------------------------------------------------
    | العلاقات
    |--------------------------------------------------------------------------
    */

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
