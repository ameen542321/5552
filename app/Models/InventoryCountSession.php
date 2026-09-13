<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InventoryCountSession extends Model
{
    use SoftDeletes;

    public const OPEN_STATUSES = ['draft', 'sent_to_accountant', 'counting', 'pending_owner', 'partially_approved', 'returned_to_accountant'];

    protected $fillable = ['store_id', 'owner_id', 'accountant_id', 'status', 'source_type', 'source_id', 'note', 'sent_to_accountant_at', 'submitted_to_owner_at', 'approved_at', 'cancelled_at', 'cancellation_reason'];
    protected $casts = ['sent_to_accountant_at' => 'datetime', 'submitted_to_owner_at' => 'datetime', 'approved_at' => 'datetime', 'cancelled_at' => 'datetime'];

    public function store() { return $this->belongsTo(Store::class); }
    public function owner() { return $this->belongsTo(User::class, 'owner_id'); }
    public function accountant() { return $this->belongsTo(Accountant::class); }
    public function items() { return $this->hasMany(InventoryCountSessionItem::class); }

    public function referenceCode(): string
    {
        $date = ($this->created_at ?? now())->format('Ymd');

        return 'INV-' . $date . '-' . str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }

    public function statusLabel(): string
    {
        return [
            'draft' => 'مسودة', 'sent_to_accountant' => 'مرسلة للمحاسب', 'counting' => 'قيد الجرد',
            'pending_owner' => 'بانتظار مراجعة المالك', 'partially_approved' => 'معتمدة جزئيًا',
            'returned_to_accountant' => 'معادة للمحاسب', 'approved' => 'معتمدة', 'cancelled' => 'ملغاة',
        ][$this->status] ?? $this->status;
    }
}
