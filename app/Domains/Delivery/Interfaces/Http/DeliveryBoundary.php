<?php

namespace App\Domains\Delivery\Interfaces\Http;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;

final class DeliveryBoundary
{
    public function handle(Request $request, Closure $next)
    {
        abort_if(strlen($request->getContent()) > 16 * 1024 || (int) $request->header('Content-Length', '0') > 16 * 1024, 413);
        if ($request->isMethod('GET') || $request->isMethod('HEAD')) {
            abort_unless(in_array(trim($request->getContent()), ['', '{}', '[]'], true), 422);
        } else {
            abort_if($request->query() !== [], 422);
            $key = 'delivery:owner:'.Auth::guard('owner')->id();
            if (RateLimiter::tooManyAttempts($key, 60)) {
                abort(429, '', ['Retry-After' => (string) RateLimiter::availableIn($key)]);
            }
            RateLimiter::hit($key, 60);
        }
        $response = $next($request);
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
