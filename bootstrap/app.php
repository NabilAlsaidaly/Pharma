<?php

use App\Domains\Cart\Domain\Exceptions\CartConflict;
use App\Domains\Identity\Domain\Exceptions\AccountSuspended;
use App\Domains\Identity\Interfaces\Http\IdentityErrorResponse;
use App\Domains\ManagementIdentity\Interfaces\Console\ProvisionOwnerCommand;
use App\Http\Middleware\AssignRequestId;
use App\Support\ApiErrorResponse;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

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
    ->withCommands([ProvisionOwnerCommand::class])
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(['current_password', 'password', 'password_confirmation']);
        $exceptions->render(function (Throwable $exception, Request $request) {
            $login = app('router')->getRoutes()->getByName('owner.login');
            $prefix = $login ? substr($login->uri(), 0, -6) : null;
            $privatePath = $prefix !== null && ($request->path() === $prefix || str_starts_with($request->path(), $prefix.'/'));
            if (! str_starts_with((string) $request->route()?->getName(), 'owner.') && ! $privatePath) {
                return null;
            }
            if ($exception instanceof ValidationException) {
                if (
                    in_array($request->route()->getName(), ['owner.dashboard', 'owner.search'], true)
                    || preg_match('/^owner\.(products|brands|concerns|requests)\./', (string) $request->route()->getName())
                ) {
                    return response()->view('owner.dashboard.validation', ['validationErrors' => $exception->errors()], 422)
                        ->header('Cache-Control', 'no-store, private, max-age=0')->header('Referrer-Policy', 'no-referrer');
                }

                return redirect()->route($request->route()->getName() === 'owner.login.submit' ? 'owner.login' : 'owner.profile')
                    ->withErrors($exception->errors())->withInput($request->only(['email', 'display_name']))
                    ->header('Cache-Control', 'no-store, private, max-age=0')
                    ->header('Referrer-Policy', 'no-referrer');
            }
            $status = $exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : 500;

            return response()->view(
                'owner.error',
                ['statusCode' => $status, 'skipOwnerIdentity' => true],
                $status,
                $exception instanceof HttpExceptionInterface ? $exception->getHeaders() : []
            )
                ->header('Cache-Control', 'no-store, private, max-age=0')
                ->header('X-Frame-Options', 'DENY')->header('X-Content-Type-Options', 'nosniff')
                ->header('Referrer-Policy', 'no-referrer');
        });
        $exceptions->report(function (Throwable $exception): ?bool {
            if (str_starts_with((string) request()->route()?->getName(), 'owner.')) {
                Log::error('owner_portal_failed', ['exception_type' => $exception::class, 'request_id' => request()->attributes->get('request_id')]);

                return false;
            }
            if (request()->is('api/v1/checkout/intents', 'api/v1/checkout/intents/*')) {
                // Exception SQL bindings/stack arguments can contain contact snapshots.
                Log::error('preliminary_checkout_failed', [
                    'exception_type' => $exception::class,
                    'request_id' => request()->attributes->get('request_id'),
                ]);

                return false;
            }

            return null;
        });
        $exceptions->dontReport([AccountSuspended::class, CartConflict::class]);
        $exceptions->render(IdentityErrorResponse::render(...));
        $exceptions->render(ApiErrorResponse::render(...));
    })->create();
