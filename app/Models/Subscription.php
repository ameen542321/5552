<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class Subscription
 *
 * يمثل اشتراك مستخدم في نظام CARLED.
 */
class Subscription extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',     // صاحب الاشتراك
        'type',        // نوع الاشتراك (شهري/سنوي/تجريبي)
        'price',       // السعر
        'start_at',    // تاريخ بداية الاشتراك
        'end_at',      // تاريخ نهاية الاشتراك
        'status',      // حالة الاشتراك
    ];

    protected $casts = [
        'start_at' => 'date',
        'end_at' => 'date',
    ];

    /*
    |--------------------------------------------------------------------------
    | العلاقات
    |--------------------------------------------------------------------------
    */

    public function user()
    {
        return $this->belongsTo(User::class);
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
