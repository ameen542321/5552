<?php

namespace App\Http\Controllers;

use App\Models\OneSignalSetting;
use App\Services\OneSignalService;
use Illuminate\Http\Request;

class AdminOneSignalSettingsController extends Controller
{
    public function index()
    {
        $settings = OneSignalSetting::first();
        return view('admin.onesignal.index', compact('settings'));
    }

    public function update(Request $request)
    {
        $request->validate([
            'app_id' => 'required|string',
            'api_key' => 'required|string',
        ]);

        OneSignalSetting::updateOrCreate(
            ['id' => 1],
            [
                'app_id' => $request->app_id,
                'api_key' => $request->api_key,
            ]
        );

        return back()->with('success', 'تم حفظ إعدادات OneSignal بنجاح');
    }

    public function test()
    {
        $settings = OneSignalSetting::first();

        if (!$settings || !$settings->app_id || !$settings->api_key) {
            return back()->with('error', 'الرجاء إدخال إعدادات OneSignal أولاً');
        }

        // إرسال إشعار تجريبي لجميع الأجهزة
        OneSignalService::sendToAll(
            "اختبار OneSignal",
            "تم إرسال هذا الإشعار بنجاح!"
        );

        return back()->with('success', 'تم إرسال الإشعار التجريبي بنجاح');
    }
}

