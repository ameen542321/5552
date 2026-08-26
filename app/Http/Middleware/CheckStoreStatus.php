<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckStoreStatus
{
    public function handle(Request $request, Closure $next)
    {
        /*
        |--------------------------------------------------------------------------
        | 1) إذا الراوت يمرر متجر (Model Binding)
        |--------------------------------------------------------------------------
        */
        $store = $request->route('store');

        if ($store && $store->status === 'suspended') {
            abort(403, 'هذا المتجر موقوف ولا يمكن الوصول إليه');
        }

        /*
        |--------------------------------------------------------------------------
        | 2) إذا كان المحاسب مسجلاً الدخول
        |--------------------------------------------------------------------------
        */
        if (Auth::guard('accountant')->check()) {

            $accountant = Auth::guard('accountant')->user();

            if ($accountant->store && $accountant->store->status === 'suspended') {
                abort(403, 'تم إيقاف المتجر المرتبط بحسابك');
            }
        }

        /*
        |--------------------------------------------------------------------------
        | 3) إذا كان المستخدم العادي مسجلاً الدخول
        |--------------------------------------------------------------------------
        */
        if (Auth::guard('web')->check()) {

            $user = Auth::guard('web')->user();

            // تجاهل الأدمن
            if ($user->role === 'admin') {
                return $next($request);
            }

            if ($user->store && $user->store->status === 'suspended') {
                abort(403, 'تم إيقاف متجرك ولا يمكن الوصول إليه');
            }
        }

        return $next($request);
    }
}
