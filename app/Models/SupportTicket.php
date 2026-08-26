<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class SupportTicket extends Model
{
    use SoftDeletes;

    public const UNANSWERED_EXPIRY_DAYS = 7;
    public const PERMANENT_DELETE_AFTER_DAYS = 90;
    public const ACTIVE_STATUSES = ['open', 'waiting_support', 'replied', 'waiting_owner', 'in_progress'];

    protected $fillable = [
        'reference', 'owner_id', 'accountant_id', 'requested_role', 'subject',
        'category', 'priority', 'description', 'status', 'support_response', 'responded_by',
        'responded_at', 'closed_at', 'last_activity_at', 'expires_at', 'cancelled_at',
        'cancel_reason', 'owner_unread_count',
        'support_unread_count', 'created_by_support',
    ];

    protected $casts = [
        'responded_at' => 'datetime',
        'closed_at' => 'datetime',
        'last_activity_at' => 'datetime',
        'expires_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'created_by_support' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (SupportTicket $ticket): void {
            $ticket->reference ??= static::generateReference();
            $ticket->expires_at ??= now()->addDays(static::UNANSWERED_EXPIRY_DAYS);
        });
    }

    public static function generateReference(): string
    {
        do {
            $reference = 'SUP-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6));
        } while (static::withTrashed()->where('reference', $reference)->exists());

        return $reference;
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id')->withTrashed();
    }

    public function accountant()
    {
        return $this->belongsTo(Accountant::class);
    }

    public function sessions()
    {
        return $this->hasMany(SupportSession::class);
    }

    public function messages()
    {
        return $this->hasMany(SupportTicketMessage::class)->oldest();
    }

    public function events()
    {
        return $this->hasMany(SupportTicketEvent::class)->oldest();
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'open', 'waiting_support' => 'بانتظار الدعم التقني',
            'replied', 'waiting_owner' => 'بانتظار المالك',
            'in_progress' => 'قيد التنفيذ',
            'closed' => 'مغلق',
            'cancelled' => 'ملغي لانتهاء المهلة',
            default => 'غير محدد',
        };
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', static::ACTIVE_STATUSES);
    }

    public function getPriorityLabelAttribute(): string
    {
        return match ($this->priority) {
            'urgent' => 'عاجلة',
            'high' => 'مرتفعة',
            'low' => 'منخفضة',
            default => 'عادية',
        };
    }

    public function getCategoryLabelAttribute(): string
    {
        return match ($this->category) {
            'restore' => 'استعادة محذوفات',
            'accounting' => 'مراجعة حسابية',
            'inventory' => 'مراجعة مخزون',
            'account' => 'مشكلة حساب',
            default => 'طلب عام',
        };
    }
}
