<?php

namespace Tests\Fakes;

use App\Domains\Identity\Application\DTOs\GoogleIdentityData;
use App\Domains\Identity\Application\Ports\GoogleOAuthClient;
use Throwable;

class FakeGoogleOAuthClient implements GoogleOAuthClient
{
    public ?Throwable $failure = null;

    public function __construct(public GoogleIdentityData $identity) {}

    public function authorizationUrl(): string
    {
        if ($this->failure) {
            throw $this->failure;
        }

        return 'https://accounts.google.com/oauth?test-provider=1';
    }

    public function authenticatedIdentity(): GoogleIdentityData
    {
        if ($this->failure) {
            throw $this->failure;
        }

        return $this->identity;
    }
}
