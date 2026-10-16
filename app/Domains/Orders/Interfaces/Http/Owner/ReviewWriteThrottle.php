<?php

namespace App\Domains\Orders\Interfaces\Http\Owner;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class ReviewWriteThrottle
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = 'owner-review:'.hash('sha256', (string) Auth::guard('owner')->id());
        if (RateLimiter::tooManyAttempts($key, 60)) {
            abort(429, '', ['Retry-After' => (string) RateLimiter::availableIn($key)]);
        }
        RateLimiter::hit($key, 60);

        return $next($request);
    }
}
