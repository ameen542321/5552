<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Services\SupportSessionService;

class UnifiedAccountantGuard
{
    public function handle(Request $request, Closure $next)
    {
        // 1. التأكد من هوية المحاسب (Accountant Guard)
        if (!Auth::guard('accountant')->check()) {
            return redirect()->route('login');
        }

        // لا تُعطل حالة الحساب أو الاشتراك جلسة الدعم التي بدأها الأدمن صراحةً.
        if (app(SupportSessionService::class)->active($request)) {
            return $next($request);
        }

        if (Auth::guard('web')->user()?->role === 'admin') {
            return redirect()->route('admin.dashboard.index')
                ->with('success', 'انتهت جلسة الدعم وتمت العودة إلى لوحة الدعم التقني.');
        }

        $accountant = Auth::guard('accountant')->user();
        if (! $accountant) {
            return redirect()->route('login');
        }

        // 2. فحص حالة المحاسب (active/suspended) وحالة الموظف المرتبط به
        // ملاحظة: دمجنا هنا فحص CheckUserSuspended و AccountantAuth
        if ($accountant->status !== 'active') {
            Auth::guard('accountant')->logout();
            return redirect()->route('login')->withErrors(['auth' => 'حسابك الشخصي غير نشط.']);
        }

        // 3. فحص حالة المالك (Owner) واشتراكه
        // ملاحظة: دمجنا هنا فحص CheckSubscriptionActive
        $owner = $accountant->user; // العلاقة مع المالك
        if ($owner) {
            if ($owner->status !== 'active') {
                Auth::guard('accountant')->logout();
                return redirect()->route('login')->withErrors(['auth' => 'حساب المالك موقوف حالياً.']);
            }

            if ($owner->subscription_end_at && now()->gt(Carbon::parse($owner->subscription_end_at)->endOfDay())) {
                Auth::guard('accountant')->logout();
                return redirect()->route('login')->withErrors(['auth' => 'انتهى اشتراك المنشأة، يرجى مراجعة المالك.']);
            }
        }

        // 4. فحص حالة المتجر (Store)
        // ملاحظة: دمجنا هنا فحص CheckStoreStatus
        if ($accountant->store && $accountant->store->status !== 'active') {
            return abort(403, 'المتجر المرتبط بك غير نشط حالياً.');
        }

        return $next($request);
    }
}
