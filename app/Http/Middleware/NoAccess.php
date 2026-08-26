<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class NoAccess
{
    public function handle(Request $request, Closure $next)
    {
        return redirect()->route('no.access');
    }
} 
