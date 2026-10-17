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
        if (preg_match('/^owner\.(gifts|education)\./', (string) $request->route()?->getName())) {
            return $next($request);
        }
        if (preg_match('/^owner\.(settings|content)\./', (string) $request->route()?->getName())) {
            $limit = $request->hasFile('image') ? 8 * 1024 * 1024 : 64 * 1024;
            abort_if((int) $request->header('Content-Length', '0') > $limit || strlen($request->getContent()) > $limit, 413);

            return $next($request);
        }
        if (! str_starts_with((string) $request->route()?->getName(), 'owner.catalog.')) {
            return app(LimitPersonalMutationBody::class)->handle($request, $next);
        }
        if (! $request->isMethod('GET') && ! $request->isMethod('HEAD')) {
            abort_if((int) $request->header('Content-Length', '0') > 8 * 1024 * 1024 || strlen($request->getContent()) > 8 * 1024 * 1024, 413);
        }

        return $next($request);
    }
}
