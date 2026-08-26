<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Store;
use App\Services\SupportSessionService;

class UnifiedStoreGuard
{
    public function handle(Request $request, Closure $next)
    {
        // جلب المتجر من الرابط (Route Binding)
        $store = $request->route('store');

        // 1. التأكد أن الكائن موجود فعلاً (تجنب خطأ Null Object)
        if (!$store instanceof Store) {
            return $next($request); // إذا لم يكن هناك متجر في الرابط، أكمل بشكل طبيعي
        }

        // جلسة الدعم المصرح بها تحتاج الوصول إلى المتجر الموقوف لمراجعته وإصلاحه.
        if (app(SupportSessionService::class)->active($request)) {
            return $next($request);
        }

        // 2. استثناء الأدمن
        if (auth()->guard('web')->check() && auth()->user()->role === 'admin') {
            return $next($request);
        }

        // 3. فحص ملكية المتجر (للمالك) أو تبعيته (للمحاسب)
        $isOwner = auth()->guard('web')->check() && $store->user_id === auth()->id();
        $isAccountant = auth()->guard('accountant')->check() && $store->id === auth()->user()->store_id;

        if (!$isOwner && !$isAccountant) {
            abort(403, 'غير مصرح لك بالوصول لهذا المتجر.');
        }

        // 4. فحص حالة المتجر
        if ($store->status !== 'active') {
            return redirect()->route('user.stores.index')->with('error', 'عذراً، هذا المتجر موقوف حالياً ولا يمكن الوصول إليه.');
        }

        return $next($request);
    }
}
