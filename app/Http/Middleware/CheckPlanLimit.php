<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckPlanLimit
{
    public function handle(Request $request, Closure $next, $type)
    {
       $user = auth()->user();


        if (!$user) {
            return back()->with('error', 'يجب تسجيل الدخول أولاً.');
        }

        if (!$user->plan) {
            return back()->with('error', 'لا توجد خطة اشتراك مفعّلة.');
        }

        /*
        |--------------------------------------------------------------------------
        | التحقق حسب نوع العملية
        |--------------------------------------------------------------------------
        */

        switch ($type) {

            /*
            |-----------------------------
            | إنشاء متجر جديد
            |-----------------------------
            */
            case 'store':
                if (!$user->canCreateStore()) {
                    return back()->with('error', 'لقد وصلت للحد الأقصى من المتاجر المسموح بها في خطتك.');
                }
                break;

            /*
            |-----------------------------
            | استرجاع متجر محذوف
            |-----------------------------
            */
            case 'store-restore':
                if (!$user->canCreateStore()) {
                    return back()->with('error', 'لا يمكنك استرجاع المتجر لأنك وصلت للحد الأقصى من المتاجر في خطتك.');
                }
                break;

            /*
            |-----------------------------
            | إنشاء محاسب جديد
            |-----------------------------
            */
            case 'accountant':
                if (!$user->canCreateAccountant()) {
                    return back()->with('error', 'لقد وصلت للحد الأقصى من المحاسبين المسموح بهم في خطتك.');
                }
                break;

            /*
            |-----------------------------
            | استرجاع محاسب محذوف
            |-----------------------------
            */
            case 'accountant-restore':
                if (!$user->canCreateAccountant()) {
                    return back()->with('error', 'لا يمكنك استرجاع المحاسب لأنك وصلت للحد الأقصى في خطتك.');
                }
                break;
        }

        return $next($request);
    }
}
