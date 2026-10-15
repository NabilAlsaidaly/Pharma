<?php

namespace App\Domains\Catalog\Interfaces\Http;

use App\Http\Middleware\LimitPersonalMutationBody;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LimitOwnerCatalogBody
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! str_starts_with((string) $request->route()?->getName(), 'owner.catalog.')) {
            return app(LimitPersonalMutationBody::class)->handle($request, $next);
        }
        if (! $request->isMethod('GET') && ! $request->isMethod('HEAD')) {
            abort_if((int) $request->header('Content-Length', '0') > 8 * 1024 * 1024 || strlen($request->getContent()) > 8 * 1024 * 1024, 413);
        }

        return $next($request);
    }
}
