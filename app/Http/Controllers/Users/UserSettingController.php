<?php

namespace App\Http\Controllers\Users;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class UserSettingController extends Controller
{
    /**
     * تحديث إعدادات المستخدم
     */
    public function update(Request $request)
    {
        // 1. التحقق من صحة البيانات المرسلة
        $validated = $request->validate([
            'notifications_expiry' => 'required|integer|in:10,15,30',
            'invoices_expiry'      => 'required|integer|in:30,60,90',
            'logs_expiry'          => 'required|integer|in:30,60,90,180',
        ]);

        // 2. تحديث الإعدادات (أو إنشاؤها إذا لم تكن موجودة)
        $settings = Auth::user()->settings()->updateOrCreate(
            ['user_id' => Auth::id()],
            $validated
        );

        return back()->with('success', 'تم تحديث الإعدادات بنجاح');
    }
}
