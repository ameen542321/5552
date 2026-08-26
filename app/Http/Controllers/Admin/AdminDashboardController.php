<?php

namespace App\Http\Controllers\Admin;

use App\Models\User;
use App\Models\Store;
use App\Models\Accountant;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdminDashboardController
{
    public function index(): View
    {
        // 1. حساب المستخدمين (أصحاب المتاجر فقط) واستثناء الأدمن
        $stats = [
            'users_count'       => User::where('role', 'user')->count(),

            // 2. المحاسبين (يتم جلبهم من جدول المحاسبين المنفصل حسب هيكلة قاعدة بياناتك)
            'accountants_count' => Accountant::count(),

            // 3. المتاجر (كل المتاجر في النظام)
            'stores_count'      => Store::count(),

            // 4. الإشعارات (التي تخص النظام العام)
            'notifications_count' => DB::table('notifications')->count(),
        ];

        // 5. جلب آخر 5 أنشطة مع استثناء العمليات التي قام بها الأدمن إذا كنت لا تريد رؤيتها في الجدول
        // أو على الأقل استثناء أي نشاط يتعلق بحسابات الأدمن
        $recent_activities = DB::table('logs')
            ->join('users', 'logs.user_id', '=', 'users.id')
            ->where('users.role', '!=', 'admin') // استثناء أنشطة الأدمن من الظهور في جدول النشاط العام
            ->select('logs.*')
            ->latest('logs.created_at')
            ->take(5)
            ->get();

        return view('dashboard.admin.index', compact('stats', 'recent_activities'));
    }
}
