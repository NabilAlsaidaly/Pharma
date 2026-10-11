<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LimitPersonalMutationBody
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('GET') && ! $request->isMethod('HEAD')) {
            // Check actual bytes as well as the declared length; never trust Content-Length alone.
            abort_if((int) $request->header('Content-Length', '0') > config('customer_collections.mutation_body_bytes')
                || strlen($request->getContent()) > config('customer_collections.mutation_body_bytes'), 413);
        }

        return $next($request);
    }
}
