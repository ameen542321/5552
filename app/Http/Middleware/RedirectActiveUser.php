<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class RedirectActiveUser
{
    public function handle($request, Closure $next)
    {
        // 1) إذا كان المحاسب مسجلاً الدخول → تجاهل هذا الـ middleware
        if (Auth::guard('accountant')->check()) {
            return $next($request);
        }

        // 2) إذا كان الأدمن مسجلاً الدخول → تجاهل هذا الـ middleware
        if (Auth::guard('web')->check() && Auth::guard('web')->user()->role === 'admin') {
            return $next($request);
        }

        // 3) المستخدم العادي فقط
        $user = Auth::guard('web')->user();

        // إذا لم يكن مسجّل دخول → كمل
        if (!$user) {
            return $next($request);
        }

        // إذا شاهد الترحيب سابقاً → كمل
        if ($user->welcome_shown) {
            return $next($request);
        }

        // السماح بالدخول لصفحة الترحيب وزر المتابعة
        if ($request->routeIs('welcome.screen') || $request->routeIs('welcome.continue')) {
            return $next($request);
        }

        // توجيه لصفحة الترحيب
        return redirect()->route('welcome.screen');
    }
}
