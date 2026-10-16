<?php

namespace App\Domains\Gifts\Interfaces\Http;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;

final class GiftHttpBoundary
{
    public function handle(Request $request, Closure $next)
    {
        if (($request->isMethod('GET') || $request->isMethod('HEAD')) && $request->is('api/v1/gifts/*')) {
            abort_if(strlen($request->getContent()) > 16 * 1024, 413);
            abort_unless(in_array(trim($request->getContent()), ['', '[]', '{}'], true), 422);
        }
        if (! $request->isMethod('GET') && ! $request->isMethod('HEAD')) {
            $owner = str_starts_with((string) $request->route()?->getName(), 'owner.');
            $limit = $owner ? ($request->hasFile('image') ? 10 * 1024 * 1024 : 256 * 1024) : 16 * 1024;
            abort_if(strlen($request->getContent()) > $limit || (int) $request->header('Content-Length', '0') > $limit, 413);
            $actorId = Auth::guard($owner ? 'owner' : 'customer')->id();
            $keys = $owner ? ['gift:owner:'.$actorId => [60, 60]] : ['gift:customer:'.$actorId => [10, 3600], 'gift:ip:'.hash('sha256', (string) $request->ip()) => [100, 3600]];
            foreach ($keys as $key => [$max, $seconds]) {
                if (RateLimiter::tooManyAttempts($key, $max)) {
                    abort(429, '', ['Retry-After' => (string) RateLimiter::availableIn($key)]);
                }
            }
            foreach ($keys as $key => [$max, $seconds]) {
                RateLimiter::hit($key, $seconds);
            }
        }
        $response = $next($request);
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
