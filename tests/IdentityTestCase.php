<?php

namespace Tests;

use App\Domains\Identity\Application\DTOs\GoogleIdentityData;
use App\Domains\Identity\Domain\Enums\CustomerAccountStatus;
use App\Domains\Identity\Infrastructure\Persistence\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use RuntimeException;

abstract class IdentityTestCase extends TestCase
{
    use RefreshDatabase {
        refreshTestDatabase as protected refreshIdentityDatabase;
    }

    protected function refreshTestDatabase(): void
    {
        if (config('database.default') !== 'mysql'
            || config('database.connections.mysql.database') !== 'sumaya_pharma_test'
            || ! app()->environment('testing')) {
            throw new RuntimeException('Identity tests require the isolated sumaya_pharma_test MySQL database.');
        }
        $this->refreshIdentityDatabase();
    }

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'identity.frontend_url' => 'https://frontend.test',
            'sanctum.stateful' => ['frontend.test'],
            'cors.allowed_origins' => ['https://frontend.test'],
            'services.google.client_id' => 'test-client',
            'services.google.client_secret' => 'test-secret',
            'services.google.redirect' => 'https://backend.test/api/v1/auth/google/callback',
        ]);
    }

    protected function identity(string $sub = 'google-123', string $email = 'customer@example.test'): GoogleIdentityData
    {
        return new GoogleIdentityData($sub, 'Customer', $email, 'https://example.test/avatar.png');
    }

    protected function customer(CustomerAccountStatus $status = CustomerAccountStatus::Active): Customer
    {
        return Customer::query()->create([
            'google_sub' => 'google-123', 'name' => 'Customer', 'email' => 'customer@example.test',
            'account_status' => $status, 'last_login_at' => now(),
        ]);
    }

    protected function continueBrowserSession(TestResponse $response): void
    {
        $cookies = [];
        foreach ($response->headers->getCookies() as $cookie) {
            $cookies[$cookie->getName()] = $cookie->getValue();
        }
        $this->withUnencryptedCookies($cookies);
        $this->app['auth']->forgetGuards();
        $this->withHeader('Origin', 'https://frontend.test');
    }
}
