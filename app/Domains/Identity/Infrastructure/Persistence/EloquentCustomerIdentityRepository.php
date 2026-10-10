<?php

namespace App\Domains\Identity\Infrastructure\Persistence;

use App\Domains\Identity\Application\DTOs\CustomerAuthenticationData;
use App\Domains\Identity\Application\DTOs\GoogleIdentityData;
use App\Domains\Identity\Application\Ports\CustomerIdentityRepository;
use App\Domains\Identity\Domain\Enums\CustomerAccountStatus;
use App\Domains\Identity\Domain\Exceptions\AccountSuspended;
use App\Domains\Identity\Infrastructure\Persistence\Models\Customer;
use Illuminate\Support\Facades\DB;

class EloquentCustomerIdentityRepository implements CustomerIdentityRepository
{
    public function findByGoogleSub(string $googleSub): ?CustomerAuthenticationData
    {
        $customer = Customer::query()->where('google_sub', $googleSub)->first();

        return $customer ? $this->data($customer) : null;
    }

    public function createOrFirst(GoogleIdentityData $identity): CustomerAuthenticationData
    {
        $customer = Customer::query()->createOrFirst(['google_sub' => $identity->googleSub], [
            'name' => $identity->name,
            'email' => $identity->email,
            'avatar_url' => $identity->avatarUrl,
            'account_status' => CustomerAccountStatus::Active,
        ]);

        return $this->data($customer);
    }

    public function synchronizeLogin(string $customerId, GoogleIdentityData $identity): CustomerAuthenticationData
    {
        // Short DB-only transaction: never held while contacting Google.
        return DB::transaction(function () use ($customerId, $identity) {
            $customer = Customer::query()->lockForUpdate()->findOrFail($customerId);
            if ($customer->account_status === CustomerAccountStatus::Suspended) {
                throw new AccountSuspended;
            }
            $customer->fill([
                'name' => $identity->name,
                'email' => $identity->email,
                'avatar_url' => $identity->avatarUrl,
                'last_login_at' => now(),
            ])->save();

            return $this->data($customer);
        });
    }

    private function data(Customer $customer): CustomerAuthenticationData
    {
        return new CustomerAuthenticationData(
            $customer->id,
            $customer->name,
            $customer->email,
            $customer->avatar_url,
            $customer->account_status,
            $customer->last_login_at,
        );
    }
}
