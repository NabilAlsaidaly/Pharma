<?php

namespace App\Domains\Identity\Application\Ports;

use App\Domains\Identity\Application\DTOs\GoogleIdentityData;

interface GoogleOAuthClient
{
    public function authorizationUrl(): string;

    public function authenticatedIdentity(): GoogleIdentityData;
}
