<?php

namespace App\Modules\PurchaseOrders\Models;

use App\Models\Accountant;
use Illuminate\Database\Eloquent\Model;

class StorePurchaseOrderCountAttempt extends Model
{
    protected $fillable = ['store_purchase_order_id', 'store_purchase_order_item_id', 'attempt', 'counted_quantity', 'system_quantity_image', 'unit_type', 'accountant_id', 'note', 'submitted_at'];
    protected $casts = ['counted_quantity' => 'float', 'system_quantity_image' => 'float', 'submitted_at' => 'datetime'];
    public function order() { return $this->belongsTo(StorePurchaseOrder::class, 'store_purchase_order_id'); }
    public function item() { return $this->belongsTo(StorePurchaseOrderItem::class, 'store_purchase_order_item_id'); }
    public function accountant() { return $this->belongsTo(Accountant::class); }
}
