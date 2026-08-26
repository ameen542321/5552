<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes;

    /*
    |--------------------------------------------------------------------------
    | Constants
    |--------------------------------------------------------------------------
    */
    const ROLE_ADMIN = 'admin';
    const ROLE_USER  = 'user';

    const STATUS_ACTIVE    = 'active';
    const STATUS_SUSPENDED = 'suspended';

    /*
    |--------------------------------------------------------------------------
    | Fillable / Hidden / Casts
    |--------------------------------------------------------------------------
    */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'must_reset_password',
        'status',
        'role',
        'plan_id',
        'slug',
        'last_login_at',
        'welcome_shown',
        'subscription_end_at',
        'expires_at',
        'allowed_stores',
        'allowed_accountants',
        'suspension_reason',
    ];

    // ⚠️ role غير موجودة في fillable
    // لا يمكن تغييرها إلا يدويًا من قاعدة البيانات

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'last_login_at'       => 'datetime',
        'subscription_end_at' => 'date',
        'expires_at'          => 'date',
        'welcome_shown'       => 'boolean',
        'must_reset_password' => 'boolean',
        'allowed_stores'      => 'integer',
        'allowed_accountants' => 'integer',
    ];

    protected $attributes = [
        'role'                => self::ROLE_USER,
        'status'              => self::STATUS_ACTIVE,
        'welcome_shown'       => false,
        'allowed_stores'      => 1,
        'allowed_accountants' => 0,
    ];

    /*
    |--------------------------------------------------------------------------
    | Boot
    |--------------------------------------------------------------------------
    */
    protected static function booted()
    {
        static::creating(function (User $user) {

            // 🔒 منع إنشاء أكثر من أدمن
            if ($user->role === self::ROLE_ADMIN) {
                if (self::where('role', self::ROLE_ADMIN)->exists()) {
                    throw new \Exception('لا يمكن إنشاء أكثر من حساب دعم تقني رئيسي واحد');
                }
            }

            // slug
            if (!$user->slug) {
                $user->slug = Str::slug($user->name) . '-' . Str::random(6);
            }

            // المستخدم العادي فقط
            if ($user->role === self::ROLE_USER && empty($user->subscription_end_at)) {
                $user->subscription_end_at = now()->addDays(3);
            }

            // الأدمن بدون أي صلاحيات تجارية
            if ($user->role === self::ROLE_ADMIN) {
                $user->allowed_stores = 0;
                $user->allowed_accountants = 0;
                $user->subscription_end_at = null;
            }
        });

        static::created(function (User $user) {

            // متجر افتراضي للمستخدم فقط
            if ($user->role === self::ROLE_USER) {
                $user->stores()->create([
                    'name'   => 'المتجر الرئيسي',
                    'status' => 'active',
                    'slug'   => 'main-store-' . $user->slug,
                ]);
            }
        });

        static::updating(function (User $user) {

            // 🔒 منع تغيير الدور إلى admin
            if ($user->isDirty('role') && $user->role === self::ROLE_ADMIN) {
                throw new \Exception('لا يمكن ترقية المستخدم إلى حساب الدعم التقني من هذا المسار');
            }
        });

        static::deleting(function (User $user) {

            // 🔒 منع حذف الأدمن الوحيد
            if ($user->isAdmin()) {
                throw new \Exception('لا يمكن حذف حساب الدعم التقني الرئيسي');
            }
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Relations
    |--------------------------------------------------------------------------
    */

    public function stores()
    {
        return $this->hasMany(Store::class);
    }

    public function accountants()
    {
        return $this->hasMany(Accountant::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */


    public function scopeUsers($q)
    {
        return $q->where('role', self::ROLE_USER);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */
    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isUser(): bool
    {
        return $this->role === self::ROLE_USER;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /*
    |--------------------------------------------------------------------------
    | Subscription
    |--------------------------------------------------------------------------
    */
    public function isSubscriptionActive(): bool
    {
        return $this->isAdmin()
            || ($this->subscription_end_at && $this->subscription_end_at->isFuture());
    }

    /*
    |--------------------------------------------------------------------------
    | Status Control
    |--------------------------------------------------------------------------
    */
    public function suspend(?string $reason = null): bool
    {
        return $this->update([
            'status' => self::STATUS_SUSPENDED,
            'suspension_reason' => $reason,
        ]);
    }

    public function activate(): bool
    {
        return $this->update([
            'status' => self::STATUS_ACTIVE,
            'suspension_reason' => null,
        ]);
    }


    public function employees()
    {
        return $this->hasManyThrough(Employee::class, Store::class);
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }


    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeSuspended($query)
    {
        return $query->where('status', 'suspended');
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */


    /*
    |--------------------------------------------------------------------------
    | حدود الاشتراك
    |--------------------------------------------------------------------------
    */

    public function totalStores()
    {
        return $this->stores()->withTrashed()->count();
    }

    public function canCreateStore()
    {
        return $this->plan && $this->totalStores() < $this->plan->allowed_stores;
    }

    public function totalAccountants()
    {
        return $this->accountants()->withTrashed()->count();
    }

    public function canCreateAccountant()
    {
        return $this->plan && $this->totalAccountants() < $this->plan->allowed_accountants;
    }
/**
 * علاقة المستخدم بجدول الإعدادات الخاص به (user_settings)
 */
public function settings(): \Illuminate\Database\Eloquent\Relations\HasOne
{
    // الربط مع مودل UserSetting باستخدام user_id
    return $this->hasOne(UserSetting::class, 'user_id');
}
/**
 * سجل النشاطات التي قام بها المستخدم
 */
public function logs()
{
    // تفترض وجود جدول باسم activity_logs يحتوي على user_id
    return $this->hasMany(Log::class);
}
public function subscriptions()
{
    return $this->hasMany(Subscription::class);
}

public function activeSubscription()
{
    return $this->hasOne(Subscription::class)
                ->where('status', 'نشط')
                ->latest('end_at');
}
}
