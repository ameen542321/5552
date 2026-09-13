<?php

namespace App\Modules\PurchaseOrders\Models;

use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrderLimitSetting extends Model
{
    // يبقى السلوك الافتراضي مطابقًا للحد السابق حتى قبل تخصيص أي متجر.
    public const DEFAULT_WEEKLY_LIMIT = 4;
    public const DEFAULT_COUNTED_STATUSES = ['draft', 'sent', 'received', 'approved'];

    protected $fillable = [
        'store_id', 'weekly_limit', 'counted_statuses', 'exception_weekly_limit',
        'exception_expires_at', 'exception_reason', 'exception_admin_id',
    ];

    protected $casts = [
        'weekly_limit' => 'integer',
        'counted_statuses' => 'array',
        'exception_weekly_limit' => 'integer',
        'exception_expires_at' => 'datetime',
    ];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function exceptionAdmin()
    {
        return $this->belongsTo(User::class, 'exception_admin_id');
    }

    public function effectiveWeeklyLimit(): int
    {
        // الاستثناء ينتهي ذاتيًا؛ لا يحتاج مهمة مجدولة لإعادة الحد الأساسي.
        if ($this->exception_weekly_limit && $this->exception_expires_at?->isFuture()) {
            return $this->exception_weekly_limit;
        }

        return $this->weekly_limit ?: self::DEFAULT_WEEKLY_LIMIT;
    }

    public function effectiveCountedStatuses(): array
    {
        // التقاطع يمنع أي قيمة قديمة أو غير معروفة من التأثير في حارس الإنشاء.
        return array_values(array_intersect(
            $this->counted_statuses ?: self::DEFAULT_COUNTED_STATUSES,
            ['draft', 'sent', 'received', 'approved', 'cancelled']
        ));
    }
}
