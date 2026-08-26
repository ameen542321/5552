<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArchivedItem extends Model
{
    protected $fillable = [
        'owner_id', 'store_id', 'archivable_type', 'archivable_id', 'original_name',
        'original_slug', 'archived_slug', 'reference', 'status', 'archived_by',
        'archived_at', 'owner_restore_deadline', 'restored_at', 'restored_by',
        'admin_message', 'metadata',
    ];

    protected $casts = [
        'archived_at' => 'datetime',
        'owner_restore_deadline' => 'datetime',
        'restored_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function archivable()
    {
        return $this->morphTo();
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id')->withTrashed();
    }

    public function store()
    {
        return $this->belongsTo(Store::class)->withTrashed();
    }

    public function getTypeLabelAttribute(): string
    {
        return match ($this->archivable_type) {
            Product::class => 'منتج',
            Category::class => 'قسم',
            Employee::class => 'موظف',
            Store::class => 'متجر',
            Purchase::class => 'مشتريات مالك',
            default => class_basename((string) $this->archivable_type),
        };
    }

    public function getRestoreWindowExpiredAttribute(): bool
    {
        return $this->status === 'archived'
            && $this->owner_restore_deadline !== null
            && $this->owner_restore_deadline->isPast();
    }
}
