<?php

namespace App\Domains\ManagementIdentity\Interfaces\Http\Middleware;

use App\Domains\ManagementIdentity\Infrastructure\OwnerLoginLimiter;
use Closure;
use Illuminate\Http\Request;

class OwnerLoginAttempts
{
    public function handle(Request $request, Closure $next)
    {
        app(OwnerLoginLimiter::class)->begin($request);

        return $next($request);
    }
}
