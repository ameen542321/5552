<?php

namespace App\Models;

use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    /**
     * قيمة صفرية تستخدم داخل QR الزكوي فقط عند عدم تسجيل الرقم الضريبي للمتجر.
     * لا نعرض هذه القيمة في واجهة الفاتورة؛ العرض يخفي سطر الرقم الضريبي عند غيابه.
     */
    private const ZATCA_EMPTY_TAX_NUMBER = '000000000000000';

    protected $fillable = [
        'invoice_number',
        'sale_id',
        'customer_name',
        'customer_phone',
        'vehicle_type',
        'plate_number',
        'tax_number',
        'subtotal',      // تم الإضافة
        'tax_amount',    // تم الإضافة
        'total_amount',  // تم الإضافة
        'status',
        'description',
        'notes',
    ];

    // عملية البيع المرتبطة بالفاتورة
    public function sale()
    {
        // نُظهر الفاتورة حتى لو كانت عملية البيع المرتبطة محذوفة soft delete
        return $this->belongsTo(Sale::class)
            ->withoutGlobalScope(\Illuminate\Database\Eloquent\SoftDeletingScope::class);
    }

public static function cleanupOldInvoices($userId)
{
    // 1. جلب إعدادات المستخدم من جدول user_settings
    $settings = \App\Models\UserSetting::where('user_id', $userId)->first();
    $days = $settings ? $settings->invoices_expiry : 30;

    // 2. حذف الفواتير التي تنتمي لمبيعات هذا المستخدم (المالك)
    // نستخدم whereIn بناءً على sale_id المرتبط بـ user_id في جدول المبيعات
    return static::whereIn('sale_id', function($query) use ($userId) {
        $query->select('id')
              ->from('sales')
              ->where('user_id', $userId);
    })
    ->where('created_at', '<', now()->subDays($days))
    ->delete();
}
public function getZatcaQrCodeAttribute()
{
    $store = optional($this->sale)->store;

    // التحقق: إذا كان الرقم موجوداً نستخدمه، وإلا نضع قيمة صفرية حتى يبقى QR قابلاً للتوليد.
    $taxNumber = optional($store)->tax_number ?: self::ZATCA_EMPTY_TAX_NUMBER;

    $data = [
        [1, optional($store)->name ?: 'غير مسجل'],
        [2, $taxNumber],
        [3, $this->created_at->toIso8601String()],
        [4, $this->total_amount],
        [5, $this->tax_amount]
    ];

    $tlv = '';
    foreach ($data as $field) {
        $tag = pack("C", $field[0]);
        $length = pack("C", strlen($field[1]));
        $value = $field[1];
        $tlv .= $tag . $length . $value;
    }

    return base64_encode($tlv);
}
}
