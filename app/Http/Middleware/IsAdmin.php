<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Services\SecurityEventService;

class IsAdmin
{
    /**
     * [تعديل آمن] التحقق من أن المستخدم ضمن حارس web ويحمل دور admin.
     *
     * الهدف: حماية مسارات الإدارة دون تغيير أي ربط قائم في الراوتات.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth('web')->user();

        // [تعديل آمن] إذا لم يكن المستخدم أدمن، يتم تحويله لصفحة no-access إن كانت متاحة.
        if (! $user || $user->role !== 'admin') {
            app(SecurityEventService::class)->record(
                'AUTHZ.ADMIN_DENIED', 'authorization', 'medium',
                'سيدي، رصدنا محاولة وصول غير مصرح بها إلى منطقة الإدارة.',
                ['confidence' => 100, 'actor' => $user]
            );
            return redirect()->route('no.access');
        }

        return $next($request);
    }
}
