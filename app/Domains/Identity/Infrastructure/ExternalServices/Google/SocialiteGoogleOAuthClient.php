<?php

namespace App\Domains\Identity\Infrastructure\ExternalServices\Google;

use App\Domains\Identity\Application\DTOs\GoogleIdentityData;
use App\Domains\Identity\Application\Ports\GoogleOAuthClient;
use App\Domains\Identity\Domain\Exceptions\AuthenticationFailed;
use App\Domains\Identity\Domain\Exceptions\AuthProviderUnavailable;
use App\Domains\Identity\Domain\Exceptions\InvalidExternalIdentity;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\Request;
use Laravel\Socialite\Contracts\Factory;
use Laravel\Socialite\SocialiteManager;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User;

class SocialiteGoogleOAuthClient implements GoogleOAuthClient
{
    public function __construct(
        private readonly Factory $socialite,
        private readonly Repository $config,
        private readonly Request $request,
    ) {}

    public function authorizationUrl(): string
    {
        return $this->provider()->redirect()->getTargetUrl();
    }

    public function authenticatedIdentity(): GoogleIdentityData
    {
        if ($this->request->has('error')) {
            throw new AuthenticationFailed;
        }

        try {
            $user = $this->provider()->user();
        } catch (InvalidStateException) {
            throw new AuthenticationFailed;
        } catch (GuzzleException) {
            throw new AuthProviderUnavailable;
        }

        if (! $user instanceof User) {
            throw new InvalidExternalIdentity;
        }
        $raw = $user->getRaw();
        $sub = $raw['sub'] ?? null;
        $email = $raw['email'] ?? null;
        $verified = $raw['email_verified'] ?? null;
        if (! is_string($sub) || trim($sub) === '' || strlen($sub) > 255
            || ! is_string($email) || ! filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255
            || in_array($verified, [false, 'false', 0, '0'], true)) {
            throw new InvalidExternalIdentity;
        }

        $name = $user->getName();
        $name = is_string($name) && trim($name) !== '' ? trim($name) : $email;

        return new GoogleIdentityData($sub, mb_substr($name, 0, 255), $email, $user->getAvatar());
    }

    private function provider(): GoogleProvider
    {
        foreach (['client_id', 'client_secret', 'redirect'] as $key) {
            if (! $this->config->get('services.google.'.$key)) {
                throw new AuthProviderUnavailable;
            }
        }

        if ($this->socialite instanceof SocialiteManager) {
            $this->socialite->forgetDrivers();
        }
        $provider = $this->socialite->driver('google');
        if (! $provider instanceof GoogleProvider) {
            throw new AuthProviderUnavailable;
        }

        // Use the current session-backed request, retaining OAuth state validation.
        $provider->setRequest($this->request);
        $provider->setScopes(['openid', 'profile', 'email']);

        return $provider;
    }
}
