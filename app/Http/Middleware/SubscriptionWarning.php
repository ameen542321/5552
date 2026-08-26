<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SubscriptionWarning
{
    public function handle(Request $request, Closure $next)
    {
        // إذا كان المستخدم العادي (web)
        if (Auth::guard('web')->check()) {

            $user = Auth::guard('web')->user();

            // تجاهل الأدمن
            if ($user->role !== 'admin' && $user->subscription_end_at) {

                $daysLeft = now()->startOfDay()->diffInDays(
                    $user->subscription_end_at->startOfDay(),
                    false
                );

                if ($daysLeft <= 7 && $daysLeft >= 0) {
                    session()->flash('subscription_warning', $this->arabicDays($daysLeft));
                }
            }
        }

        // إذا كان المحاسب → تجاهل هذا الـ middleware بالكامل
        if (Auth::guard('accountant')->check()) {
            return $next($request);
        }

        return $next($request);
    }

    private function arabicDays($days)
    {
        if ($days == 1) {
            return "يوم واحد";
        } elseif ($days == 2) {
            return "يومان";
        } elseif ($days >= 3 && $days <= 10) {
            return "$days أيام";
        } else {
            return "$days يوم";
        }
    }
}
