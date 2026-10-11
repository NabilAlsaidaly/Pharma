<?php

use App\Domains\Cart\Domain\Exceptions\CartConflict;
use App\Domains\Identity\Domain\Exceptions\AccountSuspended;
use App\Domains\Identity\Interfaces\Http\IdentityErrorResponse;
use App\Http\Middleware\AssignRequestId;
use App\Support\ApiErrorResponse;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(AssignRequestId::class);
        $middleware->statefulApi();
        $middleware->redirectGuestsTo(fn (Request $request): ?string => $request->is('api/*') ? null : '/');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontReport([AccountSuspended::class, CartConflict::class]);
        $exceptions->render(IdentityErrorResponse::render(...));
        $exceptions->render(ApiErrorResponse::render(...));
    })->create();
