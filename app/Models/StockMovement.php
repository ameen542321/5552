<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    protected $fillable = [
        'store_id',
        'product_id',
        'product_name_snapshot',
        'sale_price_snapshot',
        'cost_price_snapshot',
        'user_id',
        'type',
        'quantity',
        'requested_quantity',
        'unit_type_at_movement',
        'product_type_at_movement',
        'is_splittable_at_movement',
        'items_per_unit_at_movement',
        'roll_length_value_at_movement',
        'display_unit_label',
        'balance_before',
        'balance_after',
        'meters',
        'roll_length_at_movement',
        'note',
        'business_date',
    ];

    protected $casts = [
        'quantity' => 'float',
        'sale_price_snapshot' => 'float',
        'cost_price_snapshot' => 'float',
        'requested_quantity' => 'float',
        'is_splittable_at_movement' => 'boolean',
        'items_per_unit_at_movement' => 'float',
        'roll_length_value_at_movement' => 'float',
        'balance_before' => 'float',
        'balance_after' => 'float',
        'meters' => 'float',
        'roll_length_at_movement' => 'float',
        'business_date' => 'date',
    ];

    protected $appends = [
        'previous_balance',
        'current_balance',
    ];

    protected static function booted(): void
    {
        static::creating(function (StockMovement $movement): void {
            if (!is_null($movement->balance_before) && !is_null($movement->balance_after)) {
                $product = Product::find($movement->product_id);
                $movement->product_name_snapshot ??= $product?->name;
                $movement->sale_price_snapshot ??= $product?->price;
                $movement->cost_price_snapshot ??= $product?->cost_price;
                return;
            }

            $product = $movement->relationLoaded('product')
                ? $movement->product
                : Product::query()->select('id', 'name', 'price', 'cost_price', 'quantity', 'product_type')->find($movement->product_id);

            if (!$product) {
                return;
            }

            $movement->product_name_snapshot ??= $product->name;
            $movement->sale_price_snapshot ??= $product->price;
            $movement->cost_price_snapshot ??= $product->cost_price;

            // إصلاح سجل الحركة من مكان واحد: عند إنشاء حركة مباشرة بعد تحديث المخزون
            // نحسب الرصيد قبل/بعد تلقائياً حتى تظهر صفحة إدارة المخزون بأرقام صحيحة.
            $afterQuantity = (float) $product->getRawOriginal('quantity');
            $movementQuantity = abs((float) $movement->quantity);

            if ($movement->type === 'increase') {
                $beforeQuantity = $afterQuantity - $movementQuantity;
            } elseif ($movement->type === 'decrease') {
                $beforeQuantity = $afterQuantity + $movementQuantity;
            } else {
                return;
            }

            $movement->balance_after ??= $afterQuantity;
            $movement->balance_before ??= $beforeQuantity;

            if ($product->product_type === 'fractional') {
                $movement->meters ??= $afterQuantity;
                $movement->roll_length_at_movement ??= $beforeQuantity;
            }
        });
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getPreviousBalanceAttribute(): float
    {
        return (float) ($this->balance_before ?? $this->roll_length_at_movement ?? 0);
    }

    public function getCurrentBalanceAttribute(): float
    {
        return (float) ($this->balance_after ?? $this->meters ?? 0);
    }

    public function snapshotUnitLabel(?Product $fallbackProduct = null): string
    {
        if ($this->display_unit_label) {
            return (string) $this->display_unit_label;
        }

        if ($this->product_type_at_movement === 'fractional') {
            return in_array($this->unit_type_at_movement, ['meter', 'meters', 'custom'], true) ? 'متر' : 'رول';
        }

        if ((bool) $this->is_splittable_at_movement && (float) $this->items_per_unit_at_movement > 1) {
            return $this->unit_type_at_movement === 'piece' ? 'حبة' : 'طقم';
        }

        return $fallbackProduct ? static::unitLabelForSnapshot($fallbackProduct, (string) $this->unit_type_at_movement) : 'حبة';
    }

    public function quantityInSnapshotUnit(float $normalizedQuantity, ?Product $fallbackProduct = null): float
    {
        $label = $this->snapshotUnitLabel($fallbackProduct);

        if ($this->product_type_at_movement === 'fractional' && $label === 'رول') {
            $rollLength = (float) $this->roll_length_value_at_movement;

            return $rollLength > 0 ? $normalizedQuantity / $rollLength : $normalizedQuantity;
        }

        if ((bool) $this->is_splittable_at_movement && $label === 'حبة') {
            return $normalizedQuantity * max(1, (float) $this->items_per_unit_at_movement);
        }

        return $normalizedQuantity;
    }

    public function getOperationLabelAttribute(): string
    {
        // تستنتج التسمية من مصدر الحركة المسجل دون تغيير بيانات الحركات التاريخية.
        $note = (string) $this->note;

        return match (true) {
            str_starts_with($note, 'تأكيد جرد المنتج') => 'تأكيد جرد',
            str_contains($note, 'توريد طلبية') => 'توريد طلبية',
            str_contains($note, 'استرجاع') => 'استرجاع',
            str_contains($note, 'استهلاك داخلي') || str_contains($note, 'استهلاك محاسب') => 'استهلاك محاسب',
            str_contains($note, 'مبيعات') || str_contains($note, 'عملية بيع') => 'بيع',
            $this->type === 'increase' => 'توريد',
            $this->type === 'decrease' => 'سحب',
            default => 'حركة مخزون',
        };
    }

    public function getOperationUnitLabelAttribute(): ?string
    {
        // يضاف نوع الوحدة إلى البيع فقط عندما حفظه وصف الحركة صراحةً.
        if ($this->operation_label !== 'بيع') {
            return null;
        }

        $note = (string) $this->note;
        if (str_contains($note, 'حبة')) {
            return 'حبة';
        }
        if (str_contains($note, 'طقم')) {
            return 'طقم';
        }

        return null;
    }

    public static function recordForProduct(
        Product $product,
        string $type,
        float $quantity,
        float $before,
        float $after,
        ?int $userId = null,
        ?string $note = null,
        ?float $requestedQuantity = null,
        ?string $unitType = null,
        $businessDate = null
    ): self {
        $unitType = $unitType ?: 'unit';

        return static::create([
            'store_id' => $product->store_id,
            'product_id' => $product->id,
            'product_name_snapshot' => $product->name,
            'sale_price_snapshot' => $product->price,
            'cost_price_snapshot' => $product->cost_price,
            'user_id' => $userId,
            'type' => $type,
            'quantity' => $quantity,
            'requested_quantity' => $requestedQuantity ?? $quantity,
            'unit_type_at_movement' => $unitType,
            'product_type_at_movement' => $product->product_type,
            'is_splittable_at_movement' => (bool) $product->is_splittable,
            'items_per_unit_at_movement' => $product->items_per_unit,
            'roll_length_value_at_movement' => $product->roll_length,
            'display_unit_label' => static::unitLabelForSnapshot($product, $unitType),
            'balance_before' => $before,
            'balance_after' => $after,
            'roll_length_at_movement' => $product->product_type === 'fractional' ? $before : null,
            'meters' => $product->product_type === 'fractional' ? $after : null,
            'note' => $note,
            'business_date' => $businessDate,
        ]);
    }

    public static function unitLabelForSnapshot(Product $product, string $unitType): string
    {
        if ($product->product_type === 'fractional') {
            return in_array($unitType, ['meter', 'meters', 'custom'], true) ? 'متر' : 'رول';
        }

        if ((bool) $product->is_splittable && (int) $product->items_per_unit > 1) {
            return $unitType === 'piece' ? 'حبة' : 'طقم';
        }

        return 'حبة';
    }
}
