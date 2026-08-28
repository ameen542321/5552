<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryCountSessionItem extends Model
{
    protected $fillable = ['inventory_count_session_id', 'product_id', 'product_name_snapshot', 'product_description_snapshot', 'count_type', 'unit_type', 'accountant_quantity', 'count_business_date', 'accountant_updated_at', 'accountant_note', 'system_quantity_snapshot', 'system_snapshot_at', 'owner_quantity', 'owner_adjustment_reason', 'decision', 'attempt', 'approved_at'];
    protected $casts = ['accountant_quantity' => 'float', 'system_quantity_snapshot' => 'float', 'owner_quantity' => 'float', 'count_business_date' => 'date', 'accountant_updated_at' => 'datetime', 'system_snapshot_at' => 'datetime', 'approved_at' => 'datetime'];

    public function session() { return $this->belongsTo(InventoryCountSession::class, 'inventory_count_session_id'); }
    public function product() { return $this->belongsTo(Product::class); }
    public function finalQuantity(): ?float { return $this->owner_quantity ?? $this->accountant_quantity; }
    public function isMatching(): bool { return $this->accountant_quantity !== null && abs($this->accountant_quantity - (float) $this->system_quantity_snapshot) < 0.0001; }
}
