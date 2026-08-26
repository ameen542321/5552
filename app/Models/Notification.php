<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Notification extends Model
{
    protected $fillable = [
        'sender_id',
        'sender_type',
        'target_type',
        'target_ids',
        'title',
        'message',
        'data',
        'template_key',
        'channel',
        'read_by',
    ];

    protected $casts = [
        'target_ids' => 'array',
        'read_by'    => 'array',
        'data' => 'array',
    ];

    /*
    |--------------------------------------------------------------------------
    | دوال الفلترة (Scopes) - لضمان دقة البيانات
    |--------------------------------------------------------------------------
    */
// داخل ملف Notification.php

// جلب الإشعارات التي تخص المستخدم أو العامة
public static function scopeForUser($query, $userId)
{
    return $query->where(function ($q) use ($userId) {
        $q->where('target_type', 'all')
          ->orWhereJsonContains('target_ids', $userId);
    })->orderBy('created_at', 'desc');
}

// حساب عدد الإشعارات غير المقروءة
public static function scopeUnreadCountFor($query, $userId)
{
    // ملاحظة: هنا نفترض وجود جدول وسيط أو حقل يحدد من قرأ ماذا
    // إذا كنت تستخدم نظام لارافل الافتراضي للإشعارات:
    return $query->forUser($userId)->whereNull('read_at')->count();
}

// دالة التحقق من القراءة (التي استخدمتها أنت في الكود)
public function isReadBy($userId)
{
    $readBy = collect($this->read_by ?? [])->map(fn ($value) => (string) $value);

    return $readBy->contains((string) $userId)
        || $readBy->contains('hidden_by_' . $userId);
}
    /**
     * جلب الإشعارات الموجهة لمستخدم محدد (عامة أو خاصة)
     */
    // public function scopeForUser(Builder $query, $userId)
    // {
    //     return $query->where(function ($q) use ($userId) {
    //         $q->where('target_type', 'all')
    //           ->orWhereJsonContains('target_ids', (string)$userId);
    //     });
    // }

    /**
     * جلب الإشعارات غير المقروءة والموجهة للمستخدم حصراً
     */
    public function scopeUnreadFor(Builder $query, $userId)
    {
        return $query->forUser($userId)
                     ->where(function ($q) use ($userId) {
                         $q->whereNull('read_by')
                           ->orWhereJsonDoesntContain('read_by', (string)$userId);
                     });
    }

    /*
    |--------------------------------------------------------------------------
    | دوال القراءة (Read System)
    |--------------------------------------------------------------------------
    */

    /**
     * تعليم الإشعار كمقروء
     */
    public function markAsRead($userId)
    {
        $readBy = $this->read_by ?? [];

        if (in_array((string)$userId, $readBy)) {
            return $this;
        }

        $readBy[] = (string)$userId;
        $this->update(['read_by' => $readBy]);

        return $this;
    }


public static function cleanupOldNotifications($userId = null)
{
    // إذا لم يتم تمرير المعرف، نحاول جلب معرف المستخدم الحالي من الجلسة
    $userId = $userId ?? auth()->id();

    // إذا لم يكن هناك مستخدم مسجل دخول أصلاً، نخرج من الدالة لتجنب الأخطاء
    if (!$userId) {
        return false;
    }

    // جلب إعدادات المستخدم أو استخدام القيم الافتراضية
    $settings = \App\Models\UserSetting::where('user_id', $userId)->first();

    // عدد أيام الصلاحية (من الإعدادات أو 15 يوم افتراضياً)
    $days = $settings ? $settings->notifications_expiry : 15;

    return static::whereJsonContains('target_ids', (string)$userId)
                 ->where('created_at', '<', now()->subDays($days))
                 ->delete();
}

protected static function booted()
{
    static::retrieved(function ($notification) {
        // رفعنا الاحتمالية قليلاً (إلى 10%) لأن المدة قصيرة جداً
        // لضمان استجابة أسرع للحذف
        if (rand(1, 100) <= 10) {
            static::cleanupOldNotifications();
        }
    });
}
    /*
    |--------------------------------------------------------------------------
    | دوال الإحصاء (Statistics)
    |--------------------------------------------------------------------------
    */

    /**
     * العداد الدقيق للإشعارات غير المقروءة
     */
    public static function unreadCountFor($userId)
    {
        if (!$userId) return 0;
        return self::unreadFor($userId)->count();
    }

    /**
     * مسح كافة الإشعارات (جعل الكل مقروء) للمستخدم
     */
    public static function markAllAsReadFor($userId)
    {
        $unreadNotifications = self::unreadFor($userId)->get();

        foreach ($unreadNotifications as $notification) {
            $notification->markAsRead($userId);
        }

        return true;
    }
}
