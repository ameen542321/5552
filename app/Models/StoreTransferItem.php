<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StoreTransferItem extends Model
{
    protected $fillable = [
        'store_transfer_id',
        'sender_product_id',
        'receiver_product_id',
        'product_name_snapshot',
        'requested_quantity',
        'normalized_quantity',
        'unit_type',
        'unit_label_snapshot',
        'product_type_snapshot',
        'is_splittable_snapshot',
        'items_per_unit_snapshot',
        'roll_length_snapshot',
        'cost_price',
        'sender_stock_before',
        'sender_stock_after',
        'receiver_stock_before',
        'receiver_stock_after',
    ];

    protected $casts = [
        'requested_quantity' => 'float',
        'normalized_quantity' => 'float',
        'cost_price' => 'float',
        'sender_stock_before' => 'float',
        'sender_stock_after' => 'float',
        'receiver_stock_before' => 'float',
        'receiver_stock_after' => 'float',
        'is_splittable_snapshot' => 'boolean',
        'items_per_unit_snapshot' => 'float',
        'roll_length_snapshot' => 'float',
    ];

    protected static function booted(): void
    {
        static::creating(function (StoreTransferItem $item): void {
            $product = Product::find($item->sender_product_id);
            if (! $product) {
                return;
            }
            $item->product_name_snapshot ??= $product->name;
            $item->product_type_snapshot ??= $product->product_type;
            $item->is_splittable_snapshot ??= $product->is_splittable;
            $item->items_per_unit_snapshot ??= $product->items_per_unit;
            $item->roll_length_snapshot ??= $product->roll_length;
            $item->unit_label_snapshot ??= StockMovement::unitLabelForSnapshot($product, (string) $item->unit_type);
        });
    }

    public function transfer()
    {
        return $this->belongsTo(StoreTransfer::class, 'store_transfer_id');
    }

    public function senderProduct()
    {
        return $this->belongsTo(Product::class, 'sender_product_id')->withTrashed();
    }

    public function receiverProduct()
    {
        return $this->belongsTo(Product::class, 'receiver_product_id')->withTrashed();
    }
}
