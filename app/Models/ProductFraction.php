<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductFraction extends Model
{
    // المسميات الصارمة كما اتفقنا
    protected $fillable = [
        'product_id',
        'option_label',
        'deduction_value',
        'price'
    ];

    // علاقة عكسية: هذا الجزء تابع لمنتج واحد
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
