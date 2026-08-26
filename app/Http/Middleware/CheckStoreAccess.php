<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Store;

class CheckStoreAccess
{
    public function handle(Request $request, Closure $next)
{
    $store = $request->route('store');

    // تأكد من أن الموديل موجود ونشط
    if (!$store instanceof Store) {
        abort(404);
    }

    // 1) الأدمن (فحص الحالة أيضاً للأدمن لضمان عدم دخول أدمن موقوف)
    if (Auth::guard('web')->check() && Auth::guard('web')->user()->role === 'admin') {
        return $next($request);
    }

    // 2) فحص حالة المتجر (أي حالة غير active فهي ممنوعة)
    if ($store->status !== 'active') {
        return redirect()->route('home')->with('error', 'هذا المتجر غير متاح حالياً.');
    }

    // 3) المالك
    if (Auth::guard('web')->check() && $store->user_id === Auth::id()) {
        return $next($request);
    }

    // 4) المحاسب (مع فحص حالة الموظف المرتبط به لزيادة الأمان)
    if (Auth::guard('accountant')->check()) {
        $accountant = Auth::guard('accountant')->user();
        if ($accountant->store_id === $store->id && $accountant->status === 'active') {
            return $next($request);
        }
    }

    abort(403, 'غير مصرح لك بالدخول إلى هذا المتجر');
}
}
