<?php

namespace App\Http\Controllers;
use APP\Models\Store;
use App\Models\User;

use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * عرض لوحة التحكم الرئيسية
     */
    public function index()
    {
        $user = auth()->user();

        // لو المستخدم موقوف بسبب انتهاء الاشتراك
        if ($user->status === 'موقوف') {
            return redirect()->route('subscription.expired');
        }

        // لو المستخدم ليس مديرًا ولا تاجرًا
        if (!in_array($user->role, ['admin', 'merchant'])) {
            abort(403, 'غير مصرح لك بالدخول');
        }

        // البيانات التي ستظهر في لوحة التحكم
        $stats = [
        //     'stores_count' => $user->stores()->count(),
        //     'products_count' => $user->products()->count(),
        //     'orders_count' => $user->orders()->count(),
        // 'users' => User::count(), 'stores' => Store::count(),
        ];

        return view('dashboard.index', compact('stats'));
    }
}
