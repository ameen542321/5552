<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class CheckSubscriptionActive
{
    public function handle(Request $request, Closure $next)
    {
        /*
        |--------------------------------------------------------------------------
        | 1) المحاسب (accountant)
        |--------------------------------------------------------------------------
        */
        if (Auth::guard('accountant')->check()) {

            $accountant = Auth::guard('accountant')->user();

            // إذا كان المحاسب نفسه موقوفًا
            if ($accountant->status === 'suspended') {
                Auth::guard('accountant')->logout();
                return redirect()->route('accountant.login')
                    ->withErrors(['auth' => 'تم إيقاف حسابك من قبل الإدارة']);
            }

            // إذا كان المالك موقوفًا أو اشتراكه منتهي
            if ($accountant->user) {

                $owner = $accountant->user;

                // إذا كان المالك موقوفًا
                if ($owner->status === 'suspended') {
                    Auth::guard('accountant')->logout();
                    return redirect()->route('accountant.login')
                        ->withErrors(['auth' => 'تم إيقاف حساب المالك، لا يمكنك تسجيل الدخول']);
                }

                // إذا كان اشتراك المالك منتهي
                if ($owner->subscription_end_at) {
                    $endDate = Carbon::parse($owner->subscription_end_at)->endOfDay();

                    if (now()->greaterThan($endDate)) {
                        Auth::guard('accountant')->logout();
                        return redirect()->route('accountant.login')
                            ->withErrors(['auth' => 'انتهى اشتراك المالك، لا يمكنك تسجيل الدخول']);
                    }
                }
            }

            return $next($request);
        }

        /*
        |--------------------------------------------------------------------------
        | 2) الأدمن (admin)
        |--------------------------------------------------------------------------
        */
        if (Auth::guard('web')->check() && Auth::guard('web')->user()->role === 'admin') {
            return $next($request);
        }

        /*
        |--------------------------------------------------------------------------
        | 3) المستخدم العادي (user)
        |--------------------------------------------------------------------------
        */
        if (Auth::guard('web')->check()) {

            $user = Auth::guard('web')->user();

            // إذا لا يوجد اشتراك → نعطيه 3 أيام
            if (!$user->subscription_end_at) {
                $user->subscription_end_at = now()->addDays(3);
                $user->status = 'active';
                $user->save();
                return $next($request);
            }

            $endDate = Carbon::parse($user->subscription_end_at)->endOfDay();

            // إذا انتهى الاشتراك
            if (now()->greaterThan($endDate)) {

                if ($user->status !== 'suspended') {
                    $user->status = 'suspended';
                    $user->save();
                }

                if (!$request->routeIs('subscription.*')) {
                    return redirect()->route('subscription.expired');
                }
            }
        }

        return $next($request);
    }
}
