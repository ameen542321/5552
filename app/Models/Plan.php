<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class Plan
 *
 * يمثل خطة اشتراك في نظام CARLED.
 */
class Plan extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',                 // اسم الخطة
        'allowed_stores',       // عدد المتاجر المسموح بها
        'allowed_accountants',  // عدد المحاسبين المسموح بهم
        'price',                // سعر الاشتراك
    ];

    /*
    |--------------------------------------------------------------------------
    | العلاقات
    |--------------------------------------------------------------------------
    */

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
