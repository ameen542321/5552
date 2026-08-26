<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StoreTransfer extends Model
{
    protected $fillable = [
        'sender_store_id',
        'receiver_store_id',
        'status',
        'notes',
        'rejection_reason',
        'created_by_type',
        'created_by_id',
        'action_by_type',
        'action_by_id',
        'acted_at',
        'completed_at',
        'rejected_at',
        'cancelled_at',
        'receiver_seen_at',
        'request_business_date',
        'action_business_date',
    ];

    protected $casts = [
        'acted_at' => 'datetime',
        'completed_at' => 'datetime',
        'rejected_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'receiver_seen_at' => 'datetime',
        'request_business_date' => 'date',
        'action_business_date' => 'date',
    ];

    public function senderStore()
    {
        return $this->belongsTo(Store::class, 'sender_store_id');
    }

    public function receiverStore()
    {
        return $this->belongsTo(Store::class, 'receiver_store_id');
    }

    public function items()
    {
        return $this->hasMany(StoreTransferItem::class);
    }

    public function createdBy()
    {
        return $this->morphTo(__FUNCTION__, 'created_by_type', 'created_by_id');
    }

    public function actionBy()
    {
        return $this->morphTo(__FUNCTION__, 'action_by_type', 'action_by_id');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopePendingForReview($query)
    {
        return $query->pending()->where('created_at', '<=', now()->subHours(24));
    }
}
