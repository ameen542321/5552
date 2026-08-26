<?php

namespace App\Http\Middleware;

use Closure;
use Carbon\Carbon;
use App\Models\Log;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\SupportSessionService;

class UnifiedOwnerGuard
{
    public function handle(Request $request, Closure $next)
{
    // 1. التأكد من الهوية
    if (!Auth::check()) {
        return redirect()->route('login');
    }

    // جلسة الدعم تمنح الأدمن سياق المالك كاملًا دون تغيير بيانات دخوله.
    $supportSession = app(SupportSessionService::class)->active($request);
    $user = Auth::user();
    if ($supportSession) {
        view()->share('global_auth', $user);
        view()->share('global_plan', $user->plan);
        return $next($request);
    }

    if ($user?->role === 'admin' && ! $request->routeIs('admin.*')) {
        return redirect()->route('admin.dashboard.index')
            ->with('success', 'انتهت جلسة الدعم وتمت العودة إلى لوحة الدعم التقني.');
    }

    // 2. استثناء الأدمن
    if ($user->role === 'admin') {
        return $next($request);
    }

    // 3. فحص الحظر
    if ($user->status === 'suspended') {
        Auth::logout();
        return redirect()->route('user.suspended');
    }

    // 4. فحص الاشتراك (بافتراض وجود Casting في الموديل)
    if (!$user->subscription_end_at) {
        $user->update(['subscription_end_at' => now()->addDays(3), 'status' => 'active']);
    }

    if (now()->greaterThan($user->subscription_end_at->endOfDay())) {
        if (!$request->routeIs('subscription.*')) {
            return redirect()->route('subscription.expired');
        }
    }

    // 5. الترحيب
    if (!$user->welcome_shown && !$request->routeIs('welcome.*')) {
        return redirect()->route('welcome.screen');
    }

    // 6. تنبيهات الاشتراك
    $this->checkSubscriptionWarning($user);

    // --- إضافة قوية: مشاركة البيانات مع الـ Views ---
    // بدلاً من إعادة حساب المتاجر والمحاسبين في الـ Blade، نحسبها هنا مرة واحدة
    view()->share('global_auth', $user);
    view()->share('global_plan', $user->plan); // تأكد من وجود علاقة plan في موديل User

    return $next($request);
}

   private function checkSubscriptionWarning($user)
{
    // 1. التأكد أولاً أن الحقل ليس فارغاً
    if (!$user || empty($user->subscription_end_at)) {
        return;
    }

    try {
        // 2. التحويل الآمن للكربون (حتى لو كان الحقل نصاً في الداتابيز)
        $endDate = $user->subscription_end_at instanceof \Carbon\Carbon
            ? $user->subscription_end_at
            : \Carbon\Carbon::parse($user->subscription_end_at);

        $daysLeft = now()->startOfDay()->diffInDays($endDate->startOfDay(), false);

        // 3. التأكد من أن الجلسة (Session) متاحة قبل الكتابة فيها
        if ($daysLeft <= 7 && $daysLeft >= 0 && request()->hasSession()) {
            session()->flash('subscription_warning', $this->arabicDays((int)$daysLeft));
        }
    } catch (\Exception $e) {
        // في حال وجود تاريخ غير صالح، لا تعطل الموقع، فقط تجاهل التنبيه
        \Illuminate\Support\Facades\Log::error("خطأ في معالجة تاريخ الاشتراك: " . $e->getMessage());
    }
}

    private function arabicDays($days)
    {
        if ($days == 0) return "اليوم الأخير";
        if ($days == 1) return "يوم واحد";
        if ($days == 2) return "يومان";
        return ($days >= 3 && $days <= 10) ? "$days أيام" : "$days يوم";
    }
}
