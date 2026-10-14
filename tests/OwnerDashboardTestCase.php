<?php

namespace Tests;

use App\Domains\Cart\Application\CartOperations;
use App\Domains\Identity\Infrastructure\Persistence\Models\Customer;
use App\Domains\ManagementIdentity\Application\ProvisionOwner;
use App\Domains\ManagementIdentity\Infrastructure\Persistence\Models\ManagementUser;
use App\Domains\ManagementIdentity\Interfaces\Http\OwnerPortalRoutes;
use App\Domains\Orders\Application\SubmitPreliminaryCheckoutRequest;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

abstract class OwnerDashboardTestCase extends CatalogTestCase
{
    protected ManagementUser $owner;

    private ?Customer $requestCustomer = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['owner_portal.prefix' => 'fixture-dashboard-portal']);
        Route::middleware('web')->group(fn () => OwnerPortalRoutes::register());
        Route::getRoutes()->refreshNameLookups();
        $this->owner = app(ProvisionOwner::class)->execute('مالكة تجريبية', 'dashboard-owner@example.test', 'Dashboard-fixture-passphrase-2026', 'Dashboard-fixture-passphrase-2026');
    }

    protected function ownerLogin(): void
    {
        $this->post(route('owner.login.submit'), ['email' => $this->owner->email, 'password' => 'Dashboard-fixture-passphrase-2026'])->assertRedirect(route('owner.home'));
    }

    protected function requestFixture(): string
    {
        if ($this->requestCustomer === null) {
            $this->requestCustomer = $this->customer();
            $this->requestCustomer->update(['email' => 'private-contact-sentinel@example.test', 'google_sub' => 'private-google-sub-sentinel']);
            $product = $this->product(['price_usd' => '12.00']);
            app(CartOperations::class)->add($this->requestCustomer->id, $product->id, null, 2);
        }
        $result = app(SubmitPreliminaryCheckoutRequest::class)->execute(
            $this->requestCustomer->id,
            [
                'operation_id' => (string) Str::uuid7(),
                'currency' => 'USD',
                'expected_cart_version' => '1',
                'contact' => [
                    'full_name' => 'Private-contact-name-sentinel',
                    'phone' => '+1234567890',
                    'governorate' => 'دمشق',
                    'city' => 'دمشق',
                    'address' => 'Private-address-sentinel',
                    'notes' => 'Private-notes-sentinel',
                ],
            ]
        );

        return $result['intent']->id;
    }
}
