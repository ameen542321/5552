<?php

namespace App\Modules\PurchaseOrders\Models;

use App\Models\Product;
use Illuminate\Database\Eloquent\Model;

class StorePurchaseOrderItem extends Model
{
    protected $fillable = [
        'store_purchase_order_id', 'product_id', 'matched_product_id', 'custom_product_name',
        'quantity_requested', 'quantity_received', 'unit_type', 'items_per_unit', 'roll_length', 'cost_price_at_order',
        'cost_price_at_receipt', 'price_variance', 'price_variance_percent',
        'update_product_cost', 'add_to_owner_purchases', 'receipt_notes',
        'stock_quantity_before', 'stock_quantity_after', 'cost_price_before',
        'cost_price_after', 'owner_purchase_id', 'inventory_count_required', 'inventory_count_quantity',
        'inventory_count_note', 'inventory_count_unit', 'system_quantity_snapshot', 'inventory_snapshot_at',
        'inventory_count_submitted_at', 'inventory_count_submitted_by', 'inventory_changed_after_count',
        'inventory_count_attempt', 'excluded_after_count', 'excluded_at', 'exclusion_reason', 'changed_by_type', 'changed_by_id',
    ];

    protected $casts = [
        'quantity_requested' => 'float',
        'quantity_received' => 'float',
        'items_per_unit' => 'integer',
        'roll_length' => 'float',
        'cost_price_at_order' => 'float',
        'cost_price_at_receipt' => 'float',
        'price_variance' => 'float',
        'price_variance_percent' => 'float',
        'update_product_cost' => 'boolean',
        'add_to_owner_purchases' => 'boolean',
        'stock_quantity_before' => 'float',
        'stock_quantity_after' => 'float',
        'cost_price_before' => 'float',
        'cost_price_after' => 'float',
        'inventory_count_required' => 'boolean',
        'inventory_count_quantity' => 'float',
        'system_quantity_snapshot' => 'float',
        'inventory_snapshot_at' => 'datetime',
        'inventory_count_submitted_at' => 'datetime',
        'inventory_changed_after_count' => 'boolean',
        'inventory_count_attempt' => 'integer',
        'excluded_after_count' => 'boolean',
        'excluded_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (StorePurchaseOrderItem $item): void {
            if ($item->custom_product_name) {
                return;
            }

            $productId = $item->product_id ?: $item->matched_product_id;
            $item->custom_product_name = $productId ? Product::find($productId)?->name : null;
        });
    }

    public function order()
    {
        return $this->belongsTo(StorePurchaseOrder::class, 'store_purchase_order_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function matchedProduct()
    {
        return $this->belongsTo(Product::class, 'matched_product_id');
    }

    public function countAttempts()
    {
        return $this->hasMany(StorePurchaseOrderCountAttempt::class, 'store_purchase_order_item_id');
    }

    public function productName(): string
    {
        return $this->custom_product_name ?? $this->product?->name ?? $this->matchedProduct?->name ?? 'منتج';
    }
}
