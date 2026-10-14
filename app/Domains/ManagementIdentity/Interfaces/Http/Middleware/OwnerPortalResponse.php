<?php

namespace App\Domains\ManagementIdentity\Interfaces\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class OwnerPortalResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->environment('production') && (! $request->isSecure() || ! config('session.secure') || ! config('session.http_only'))) {
            abort(503, 'خدمة الدخول غير متاحة بأمان حالياً.');
        }
        $response = $next($request);
        $response->headers->set('Cache-Control', 'no-store, private, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'no-referrer');

        return $response;
    }
}
