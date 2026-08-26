<?php

namespace App\Modules\PurchaseOrders\Models;

use App\Models\Store;
use App\Models\User;
use App\Models\Accountant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StorePurchaseOrder extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'store_id', 'user_id', 'accountant_id', 'supplier_name', 'status', 'workflow_status', 'inventory_review_status', 'edit_return_count', 'notes', 'inventory_review_note',
        'sent_at', 'inventory_returned_at', 'inventory_draft_saved_at', 'inventory_submitted_at', 'inventory_submitted_by',
        'received_at', 'approved_at', 'approved_business_date', 'cancelled_at', 'rejection_reason', 'rejected_at',
        'receipt_actor_type', 'receipt_actor_id', 'approval_operation_id', 'final_notice_until',
    ];

    protected $casts = [
        'edit_return_count' => 'integer',
        'sent_at' => 'datetime',
        'inventory_returned_at' => 'datetime',
        'inventory_draft_saved_at' => 'datetime',
        'inventory_submitted_at' => 'datetime',
        'received_at' => 'datetime',
        'approved_at' => 'datetime',
        'approved_business_date' => 'date',
        'cancelled_at' => 'datetime',
        'rejected_at' => 'datetime',
        'final_notice_until' => 'datetime',
    ];

    public function displayName(): string
    {
        $supplierName = trim((string) $this->supplier_name);
        $orderDate = $this->created_at?->format('j-n');

        if (! $supplierName) {
            $storeName = trim((string) ($this->store?->name ?? '')) ?: 'المتجر';
            $creatorName = trim((string) ($this->accountant?->name ?? $this->user?->name ?? ''));

            return trim('طلبية '.$storeName.' '.($orderDate ?: '').' '.$creatorName);
        }

        return trim('طلبية '.$supplierName.' '.($orderDate ?: ''));
    }

    public function referenceCode(): string
    {
        $year = $this->created_at?->format('Y') ?: now()->format('Y');

        return sprintf('PO-%d-%s-%05d', (int) $this->store_id, $year, (int) $this->id);
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function accountant()
    {
        return $this->belongsTo(Accountant::class);
    }

    public function events()
    {
        return $this->hasMany(StorePurchaseOrderEvent::class, 'store_purchase_order_id');
    }

    public function items()
    {
        return $this->hasMany(StorePurchaseOrderItem::class);
    }

    public function scopeForOwner($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }
}
