<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaleItem extends Model
{
    protected $fillable = [
        'sale_id',
        'product_id',
        'product_name_snapshot',
        'quantity',
        'price',
        'total',
        'fraction_id',
        'is_custom',
        'custom_name',
        'custom_consumption',
        'custom_meters',
        'roll_length_at_sale',
        'unit_type',
        'unit_label_snapshot',
        'product_type_snapshot',
        'product_usage_snapshot',
        'is_splittable_snapshot',
        'items_per_unit_snapshot',
        'roll_length_snapshot',
        'quantity_snapshot',
        'sale_price_snapshot',
        'cost_price_snapshot',
        'snapshot_source',
        // الحقول القديمة التالية أبقيناها للتوافق مع البيانات أو المسارات التاريخية.
        'cost_price',
        'total_price',
        'total_cost',
    ];

    protected $casts = [
        'is_splittable_snapshot' => 'boolean',
        'items_per_unit_snapshot' => 'float',
        'roll_length_snapshot' => 'float',
        'quantity_snapshot' => 'float',
        'sale_price_snapshot' => 'float',
        'cost_price_snapshot' => 'float',
    ];

    protected static function booted(): void
    {
        static::creating(function (SaleItem $item): void {
            $product = $item->product_id ? Product::find($item->product_id) : null;
            $item->product_name_snapshot ??= $item->custom_name ?: $product?->name;
            $item->product_type_snapshot ??= $product?->product_type;
            $item->product_usage_snapshot ??= $product?->usage_type;
            $item->is_splittable_snapshot ??= $product?->is_splittable;
            $item->items_per_unit_snapshot ??= $product?->items_per_unit;
            $item->roll_length_snapshot ??= $item->roll_length_at_sale ?: $product?->roll_length;
            $item->quantity_snapshot ??= $item->quantity;
            $item->sale_price_snapshot ??= $item->price;
            $item->cost_price_snapshot ??= $item->cost_price ?? $product?->cost_price;
            $item->unit_label_snapshot ??= static::snapshotUnitLabel($product, (string) ($item->unit_type ?: 'unit'));
            $item->snapshot_source ??= 'captured';
        });
    }

    public function getHistoricalProductNameAttribute(): string
    {
        return (string) ($this->product_name_snapshot ?: $this->custom_name ?: $this->product?->name ?: 'منتج غير معروف');
    }

    public function getHistoricalUnitLabelAttribute(): string
    {
        return (string) ($this->unit_label_snapshot ?: static::snapshotUnitLabel($this->product, (string) ($this->unit_type ?: 'unit')));
    }

    private static function snapshotUnitLabel(?Product $product, string $unitType): string
    {
        if ($product?->product_type === 'fractional') {
            return in_array($unitType, ['meter', 'meters', 'custom'], true) ? 'متر' : 'رول';
        }
        if ((bool) $product?->is_splittable && (float) $product?->items_per_unit > 1) {
            return $unitType === 'piece' ? 'حبة' : 'طقم';
        }
        return 'حبة';
    }

    // عملية البيع
    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    // المنتج
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
