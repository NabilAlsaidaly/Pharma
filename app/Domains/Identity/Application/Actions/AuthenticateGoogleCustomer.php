<?php

namespace App\Domains\Identity\Application\Actions;

use App\Domains\Identity\Application\DTOs\CustomerAuthenticationData;
use App\Domains\Identity\Application\DTOs\GoogleIdentityData;
use App\Domains\Identity\Application\Ports\CustomerIdentityRepository;
use App\Domains\Identity\Domain\Enums\CustomerAccountStatus;
use App\Domains\Identity\Domain\Exceptions\AccountSuspended;

class AuthenticateGoogleCustomer
{
    public function __construct(private readonly CustomerIdentityRepository $customers) {}

    public function execute(GoogleIdentityData $identity): CustomerAuthenticationData
    {
        $customer = $this->customers->findByGoogleSub($identity->googleSub)
            ?? $this->customers->createOrFirst($identity);

        if ($customer->accountStatus === CustomerAccountStatus::Suspended) {
            throw new AccountSuspended;
        }

        return $this->customers->synchronizeLogin($customer->id, $identity);
    }
}
