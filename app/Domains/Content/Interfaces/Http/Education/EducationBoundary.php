<?php

namespace App\Domains\Content\Interfaces\Http\Education;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;

final class EducationBoundary
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->isMethod('GET') || $request->isMethod('HEAD')) {
            abort_if(strlen($request->getContent()) > 16 * 1024, 413);
            abort_unless(in_array(trim($request->getContent()), ['', '{}', '[]'], true), 422);
        } else {
            $limit = $request->hasFile('image') ? 10 * 1024 * 1024 : 256 * 1024;
            abort_if(strlen($request->getContent()) > $limit || (int) $request->header('Content-Length', '0') > $limit, 413);
            $key = 'education:owner:'.Auth::guard('owner')->id();
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
