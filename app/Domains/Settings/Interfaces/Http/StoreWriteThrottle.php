<?php

namespace App\Domains\Settings\Interfaces\Http;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class StoreWriteThrottle
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = 'store-write:'.hash('sha256', (string) Auth::guard('owner')->id());
        abort_if(RateLimiter::tooManyAttempts($key, 60), 429, '', ['Retry-After' => (string) RateLimiter::availableIn($key)]);
        RateLimiter::hit($key, 60);

        return $next($request);
    }
}
