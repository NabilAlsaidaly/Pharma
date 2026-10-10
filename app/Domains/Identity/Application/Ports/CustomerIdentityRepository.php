<?php

namespace App\Domains\Identity\Application\Ports;

use App\Domains\Identity\Application\DTOs\CustomerAuthenticationData;
use App\Domains\Identity\Application\DTOs\GoogleIdentityData;

interface CustomerIdentityRepository
{
    public function findByGoogleSub(string $googleSub): ?CustomerAuthenticationData;

    public function createOrFirst(GoogleIdentityData $identity): CustomerAuthenticationData;

    public function synchronizeLogin(string $customerId, GoogleIdentityData $identity): CustomerAuthenticationData;
}
