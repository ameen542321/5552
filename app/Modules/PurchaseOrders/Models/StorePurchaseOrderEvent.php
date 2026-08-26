<?php

namespace App\Modules\PurchaseOrders\Models;

use Illuminate\Database\Eloquent\Model;

class StorePurchaseOrderEvent extends Model
{
    protected $fillable = ['store_purchase_order_id', 'store_purchase_order_item_id', 'event', 'from_status', 'to_status', 'actor_type', 'actor_id', 'note', 'data'];
    protected $casts = ['data' => 'array'];
    public function order() { return $this->belongsTo(StorePurchaseOrder::class, 'store_purchase_order_id'); }
    public function item() { return $this->belongsTo(StorePurchaseOrderItem::class, 'store_purchase_order_item_id'); }
}
