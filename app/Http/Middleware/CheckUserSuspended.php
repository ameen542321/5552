<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
class CheckUserSuspended
{
    public function handle(Request $request, Closure $next)
    {
        // 1) إذا كان المحاسب مسجلاً الدخول
        if (Auth::guard('accountant')->check()) {

            $accountant = Auth::guard('accountant')->user();

            // إذا كان المحاسب نفسه موقوفًا
            if ($accountant->status === 'suspended') {
                Auth::guard('accountant')->logout();
                return redirect()->route('accountant.login')
                    ->withErrors(['auth' => 'تم إيقاف حسابك من قبل الإدارة']);
            }

            // إذا كان المالك موقوفًا
            if ($accountant->user && $accountant->user->status === 'suspended') {
                Auth::guard('accountant')->logout();
                return redirect()->route('accountant.login')
                    ->withErrors(['auth' => 'تم إيقاف حساب المالك، لا يمكنك تسجيل الدخول']);
            }

            return $next($request);
        }

        // 2) إذا كان المستخدم العادي أو الأدمن عبر guard web
        if (Auth::guard('web')->check()) {

            $user = Auth::guard('web')->user();

            // الأدمن لا يتم إيقافه
            if ($user->role === 'admin') {
                return $next($request);
            }

            // المستخدم العادي فقط
            if ($user->status === 'suspended') {
                Auth::guard('web')->logout();
                return redirect()->route('user.suspended');
            }
        }

        return $next($request);
    }
}
