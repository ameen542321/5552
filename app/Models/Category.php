<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\BelongsToStore;

/**
 * Class Category
 *
 * يمثل قسم المنتجات داخل متجر معيّن.
 * كل قسم ينتمي لمتجر واحد، ويمكن أن يحتوي على عدة منتجات.
 */
class Category extends Model
{
    use BelongsToStore, SoftDeletes;

    /**
     * الحقول القابلة للتعبئة
     */
    protected $fillable = [
        'store_id',        // المتجر التابع له القسم
        'user_id',         // المستخدم الذي أنشأ القسم
        'name',
        'slug',
        'description',
        'status',          // active / inactive
        'is_main_category', // ← تمت إضافته هنا
    ];

    /**
     * تحويل القيم تلقائيًا
     */
    protected $casts = [
        'is_main_category' => 'boolean', // ← تمت إضافته هنا
    ];

    /**
     * Boot: توليد slug تلقائيًا عند الإنشاء والتحديث
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($category) {
            $category->slug = static::uniqueSlugForStore($category->name, $category->store_id);
        });
    }

    /**
     * توليد slug فريد داخل نفس المتجر
     */
    public static function uniqueSlugForStore($name, $storeId, $ignoreId = null)
    {
        $slug = str()->slug($name);
        $original = $slug;
        $counter = 1;

        while (
            static::where('slug', $slug)
                ->where('store_id', $storeId)
                ->when($ignoreId, fn($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = $original . '-' . $counter++;
        }

        return $slug;
    }

    /*
    |--------------------------------------------------------------------------
    | العلاقات
    |--------------------------------------------------------------------------
    */

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function archivedItem()
    {
        return $this->morphOne(ArchivedItem::class, 'archivable');
    }

    /*
    |--------------------------------------------------------------------------
    | سكوبات
    |--------------------------------------------------------------------------
    */

    public function scopeForCurrentStore($query)
    {
        $storeId = auth()->check()
            ? auth()->user()->current_store_id
            : (auth('accountant')->check() ? auth('accountant')->user()->store_id : null);

        if (!$storeId) {
            return $query->whereNull('id'); // يمنع إرجاع أي نتائج
        }

        return $query->where('store_id', $storeId);
    }
}
