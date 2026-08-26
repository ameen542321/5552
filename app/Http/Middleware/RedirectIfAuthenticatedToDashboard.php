<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class RedirectIfAuthenticatedToDashboard
{
    /**
     * يعيد توجيه المستخدم المسجّل دخوله إلى لوحة التحكم المناسبة حسب الـ guard والدور.
     *
     * ملاحظة تشغيلية:
     * - هذا الميدلوير يُستخدم عادةً على مسارات الضيف (مثل صفحة تسجيل الدخول).
     * - الهدف منه منع المستخدم المصادق عليه من رؤية صفحات الضيوف وإرساله مباشرةً لصفحته الصحيحة.
     */
    public function handle($request, Closure $next)
    {
        // [تعديل توضيحي آمن] فحص Guard المحاسب أولاً لأنه يملك لوحة مستقلة ومسارات مستقلة.
        // أثر هذا الجزء: لا يغيّر المنطق، فقط يوضّح ترتيب الأولوية في الفحص.
        if (Auth::guard('accountant')->check()) {
            return redirect()->route('accountant.dashboard');
        }

        // [تعديل توضيحي آمن] بعد ذلك نفحص Guard الويب (admin/user) لتوجيهه بحسب role.
        // أثر هذا الجزء: الحفاظ على نفس الربط الحالي مع مسارات الداشبورد المعتمدة بالمشروع.
        if (Auth::guard('web')->check()) {

            $user = Auth::guard('web')->user();

            return match ($user->role) {
                // الأدمن يذهب إلى لوحة الإدارة.
                'admin' => redirect()->route('admin.dashboard.index'),
                // المستخدم الافتراضي (owner/user) يذهب إلى user.dashboard
                // وهذا متسق مع تعريف route الحالي في routes/user.php.
                default => redirect()->route('user.dashboard'),
            };
        }

        // [تعديل توضيحي آمن] لا يوجد مستخدم مصادق عليه: نكمل الطلب كما هو (سلوك الضيف الطبيعي).
        return $next($request);
    }
}
