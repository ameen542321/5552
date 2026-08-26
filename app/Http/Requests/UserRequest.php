<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Class UserRequest
 *
 * ✅ هذا الريكوست مسؤول عن التحقق من بيانات المستخدمين
 * ✅ يُستخدم في عمليتي (store + update)
 */
class UserRequest extends FormRequest
{
    /**
     * ✅ السماح بتنفيذ الطلب
     * الصلاحيات الحقيقية تتم عبر Middleware وليس هنا
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * ✅ قواعد التحقق من البيانات
     */
    public function rules(): array
    {
        // عند التعديل نحتاج ID المستخدم لتجنب تعارض البريد
        $id = $this->user ? $this->user->id : null;

        return [
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email,' . $id,
            'phone'    => 'nullable|string|max:20',

            // ✅ كلمة المرور مطلوبة فقط عند الإنشاء
            'password' => $this->isMethod('post')
                ? 'required|min:6'
                : 'nullable|min:6',

            'role'     => 'required|in:admin,accountant,user',
            'status'   => 'required|in:active,suspended',
        ];
    }

    /**
     * ✅ رسائل الخطأ (اختياري — نضيفها لاحقًا إذا رغبت)
     */
    public function messages(): array
    {
        return [
            'name.required' => 'الاسم مطلوب',
            'email.required' => 'البريد الإلكتروني مطلوب',
            'email.unique' => 'هذا البريد مستخدم مسبقًا',
            'password.required' => 'كلمة المرور مطلوبة عند إنشاء مستخدم جديد',
        ];
    }
}
