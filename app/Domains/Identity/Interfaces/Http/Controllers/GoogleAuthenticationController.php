<?php

namespace App\Domains\Identity\Interfaces\Http\Controllers;

use App\Domains\Identity\Application\Actions\AuthenticateGoogleCustomer;
use App\Domains\Identity\Application\Ports\GoogleOAuthClient;
use App\Domains\Identity\Domain\Enums\CustomerAccountStatus;
use App\Domains\Identity\Domain\Exceptions\AccountSuspended;
use App\Domains\Identity\Domain\Exceptions\AuthenticationFailed;
use App\Domains\Identity\Domain\Exceptions\AuthProviderUnavailable;
use App\Domains\Identity\Domain\Exceptions\InvalidExternalIdentity;
use App\Domains\Identity\Infrastructure\Persistence\Models\Customer;
use App\Domains\Identity\Interfaces\Http\FrontendRedirect;
use Illuminate\Auth\SessionGuard;
use Illuminate\Contracts\Auth\Factory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class GoogleAuthenticationController
{
    public function __construct(
        private readonly GoogleOAuthClient $google,
        private readonly AuthenticateGoogleCustomer $authenticate,
        private readonly Factory $auth,
        private readonly FrontendRedirect $frontend,
    ) {}

    public function redirect(Request $request): RedirectResponse
    {
        $request->session()->put('identity.return_to', $this->frontend->safePath($request->query('return_to', '/')));

        try {
            return new RedirectResponse($this->google->authorizationUrl());
        } catch (Throwable $exception) {
            return $this->failure($request, $exception);
        }
    }

    public function callback(Request $request): RedirectResponse
    {
        try {
            $result = $this->authenticate->execute($this->google->authenticatedIdentity());
            /** @var SessionGuard $guard */
            $guard = $this->auth->guard('customer');
            $customer = $guard->loginUsingId($result->id);
            if (! $customer instanceof Customer) {
                throw new AuthenticationFailed;
            }
            if ($customer->account_status === CustomerAccountStatus::Suspended) {
                throw new AccountSuspended;
            }
            $request->session()->regenerate();
            $path = $request->session()->pull('identity.return_to', '/');
            Log::info('customer_google_login_succeeded', ['customer_id' => $result->id, 'outcome' => 'success']);

            return new RedirectResponse($this->frontend->destination($path));
        } catch (Throwable $exception) {
            return $this->failure($request, $exception);
        }
    }

    private function failure(Request $request, Throwable $exception): RedirectResponse
    {
        $code = match (true) {
            $exception instanceof AccountSuspended => 'ACCOUNT_SUSPENDED',
            $exception instanceof AuthProviderUnavailable => 'AUTH_PROVIDER_UNAVAILABLE',
            $exception instanceof InvalidExternalIdentity => 'INVALID_EXTERNAL_IDENTITY',
            default => 'AUTHENTICATION_FAILED',
        };

        $this->auth->guard('customer')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        // Exception messages/provider payloads can contain codes and tokens: never log them.
        Log::warning('customer_google_login_failed', ['outcome' => $code, 'exception_type' => $exception::class]);

        return new RedirectResponse($this->frontend->destination('/auth/error?code='.$code));
    }
}
