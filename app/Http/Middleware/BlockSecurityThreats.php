<?php

namespace App\Http\Middleware;

use App\Services\SecurityEventService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BlockSecurityThreats
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app(SecurityEventService::class)->isBlocked($request->ip())) {
            abort(429, 'تم تقييد هذا المصدر مؤقتًا بسبب نشاط أمني متكرر.');
        }

        return $next($request);
    }
}
