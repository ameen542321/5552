<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserSetting extends Model
{
    use HasFactory;

    /**
     * الحقول القابلة للتعبئة
     */
    protected $fillable = [
        'user_id',
        'notifications_expiry',
        'invoices_expiry',
        'logs_expiry',
        'email_notifications'
    ];

    /**
     * تحويل القيم لضمان التعامل معها كأرقام صحيحة أو قيم منطقية
     */
    protected $casts = [
        'notifications_expiry' => 'integer',
        'invoices_expiry'      => 'integer',
        'logs_expiry'          => 'integer',
        'email_notifications'  => 'boolean',
    ];

    /**
     * العلاقة: الإعدادات تنتمي لمستخدم واحد
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
