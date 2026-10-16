<?php

use App\Domains\Cart\Domain\Exceptions\CartConflict;
use App\Domains\Catalog\Domain\Exceptions\ConflictingPriceOwnership;
use App\Domains\Identity\Domain\Exceptions\AccountSuspended;
use App\Domains\Identity\Interfaces\Http\IdentityErrorResponse;
use App\Domains\ManagementIdentity\Interfaces\Console\ProvisionOwnerCommand;
use App\Http\Middleware\AssignRequestId;
use App\Support\ApiErrorResponse;
use Illuminate\Database\UniqueConstraintViolationException;
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
            if (preg_match('/^owner\.(settings|content|gifts)\./', (string) $request->route()?->getName())
                && ($exception instanceof ValidationException || $exception instanceof UniqueConstraintViolationException || ($exception instanceof HttpExceptionInterface && in_array($exception->getStatusCode(), [409, 422], true)))) {
                $status = $exception instanceof ValidationException ? 422 : ($exception instanceof UniqueConstraintViolationException ? 409 : $exception->getStatusCode());
                $errors = $exception instanceof ValidationException ? $exception->errors() : ['store' => [$exception instanceof UniqueConstraintViolationException ? 'تعارضت البيانات مع سجل محفوظ. راجعي البيانات وحدّثي الصفحة.' : ($exception->getMessage() ?: 'تعذر حفظ الإجراء. حدّثي الصفحة وراجعي البيانات.')]];

                return response()->view($request->routeIs('owner.gifts.*') ? 'owner.gifts.validation' : 'owner.store.validation', ['validationErrors' => $errors], $status)
                    ->header('Cache-Control', 'no-store, private, max-age=0')->header('Referrer-Policy', 'no-referrer')
                    ->header('X-Frame-Options', 'DENY')->header('X-Content-Type-Options', 'nosniff');
            }
            if ($exception instanceof ValidationException) {
                if (str_starts_with((string) $request->route()?->getName(), 'owner.catalog.')) {
                    return response()->view('owner.catalog.validation', ['validationErrors' => $exception->errors()], 422)
                        ->header('Cache-Control', 'no-store, private, max-age=0')->header('Referrer-Policy', 'no-referrer');
                }
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
            if (str_starts_with((string) $request->route()?->getName(), 'owner.requests.') && $exception instanceof HttpExceptionInterface && in_array($exception->getStatusCode(), [409, 422], true)) {
                return response()->view('owner.dashboard.validation', ['validationErrors' => ['review' => [$exception->getMessage() ?: 'تعذر حفظ المراجعة. حدّثي الصفحة وراجعي الحالة.']]], $exception->getStatusCode())
                    ->header('Cache-Control', 'no-store, private, max-age=0')->header('Referrer-Policy', 'no-referrer');
            }
            if (str_starts_with((string) $request->route()?->getName(), 'owner.requests.') && $exception instanceof UniqueConstraintViolationException) {
                return response()->view('owner.dashboard.validation', ['validationErrors' => ['review' => ['تعارضت العملية مع تعديل آخر. حدّثي الصفحة وأعيدي المحاولة.']]], 409)
                    ->header('Cache-Control', 'no-store, private, max-age=0')->header('Referrer-Policy', 'no-referrer');
            }
            if ($exception instanceof ConflictingPriceOwnership || $exception instanceof UniqueConstraintViolationException) {
                return response()->view('owner.catalog.validation', ['validationErrors' => ['catalog' => ['تعارض في ملكية السعر أو رابط محجوز. راجعي القيم وحدّثي الصفحة.']]], 409)
                    ->header('Cache-Control', 'no-store, private, max-age=0')->header('Referrer-Policy', 'no-referrer');
            }
            if ($exception instanceof HttpExceptionInterface && in_array($exception->getStatusCode(), [409, 422], true) && str_starts_with((string) $request->route()?->getName(), 'owner.catalog.')) {
                return response()->view('owner.catalog.validation', ['validationErrors' => ['catalog' => [$exception->getMessage() ?: 'تعذر تنفيذ الإجراء. حدّثي الصفحة وأعيدي المعاينة.']]], $exception->getStatusCode())
                    ->header('Cache-Control', 'no-store, private, max-age=0')->header('Referrer-Policy', 'no-referrer');
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
            if (request()->is('api/v1/checkout/intents', 'api/v1/checkout/intents/*', 'api/v1/gifts/*')) {
                // Exception SQL bindings/stack arguments can contain contact snapshots.
                Log::error(request()->is('api/v1/gifts/*') ? 'gift_http_failed' : 'preliminary_checkout_failed', [
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
