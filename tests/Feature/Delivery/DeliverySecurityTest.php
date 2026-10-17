<?php

namespace Tests\Feature\Delivery;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\DeliveryTestCase;

class DeliverySecurityTest extends DeliveryTestCase
{
    private function destinations(): array
    {
        return [['GET', 'index', []], ['GET', 'create', []], ['GET', 'edit', ['id' => (string) Str::uuid7()]], ['GET', 'history', []], ['POST', 'mutate', []]];
    }

    public function test_all_private_routes_deny_guests_and_customers(): void
    {
        foreach ([false, true] as $customer) {
            if ($customer) {
                $this->actingAs($this->customer(), 'customer');
            }
            foreach ($this->destinations() as [$method, $name, $parameters]) {
                $this->call($method, route('owner.delivery.'.$name, $parameters))->assertRedirect(route('owner.login'));
            }
        }
        $this->assertDatabaseCount('delivery_workers', 0);
    }

    public static function revoked(): array
    {
        return [['disabled'], ['restricted'], ['credential'], ['idle']];
    }

    #[DataProvider('revoked')]
    public function test_all_private_routes_enforce_owner_revocation(string $state): void
    {
        foreach ($this->destinations() as [$method, $name, $parameters]) {
            DB::table('management_users')->where('id', $this->owner->id)->update(['status' => 'active', 'role' => 'owner']);
            $this->owner = $this->owner->fresh();
            $this->ownerLogin();
            match ($state) {
                'disabled' => DB::table('management_users')->where('id', $this->owner->id)->update(['status' => 'disabled']),
                'restricted' => DB::table('management_users')->where('id', $this->owner->id)->update(['role' => 'restricted']),
                'credential' => $this->withSession(['owner_credential_version' => '999']),
                'idle' => $this->withSession(['owner_last_activity' => now()->subHours(4)->timestamp]),
            };
            $this->call($method, route('owner.delivery.'.$name, $parameters))->assertForbidden();
        }
        $this->assertDatabaseCount('delivery_worker_changes', 0);
    }

    public function test_native_csrf_size_attempts_unknown_fields_and_safe_no_flash_errors(): void
    {
        $this->ownerLogin();
        $this->app->instance(PreventRequestForgery::class, new class($this->app, $this->app['encrypter']) extends PreventRequestForgery
        {
            protected function runningUnitTests(): bool
            {
                return false;
            }
        });
        $url = route('owner.delivery.mutate');
        $this->post($url)->assertStatus(419);
        $token = Str::random(40);
        $this->withSession(['_token' => $token]);
        $this->post($url, $this->command(['name' => 'private-worker-sentinel', 'phone' => '123', 'is_active' => '1']) + ['_token' => $token, 'owner_id' => $this->owner->id])->assertStatus(422)->assertDontSee('private-worker-sentinel')->assertSee('العودة إلى التوصيل')->assertHeader('Cache-Control', 'max-age=0, no-store, private');
        $this->assertFalse(session()->has('_old_input'));
        $this->postJson($url, ['_token' => $token, 'padding' => str_repeat('x', 16385)])->assertStatus(413);
        for ($i = 0; $i < 60; $i++) {
            RateLimiter::hit('delivery:owner:'.$this->owner->id, 60);
        }
        $this->post($url, ['_token' => $token])->assertStatus(429)->assertHeader('Retry-After');
        $this->assertDatabaseCount('delivery_workers', 0);
    }

    public function test_public_allowlist_bounds_methods_and_no_worker_api_or_read_side_writes(): void
    {
        foreach (['?governorate=حماة', '?page=0', '?page=1001', '?per_page=49', '?per_page[]=12', '?zone_id=forged'] as $suffix) {
            $this->getJson('/api/v1/delivery/zones'.$suffix)->assertUnprocessable()->assertHeader('Cache-Control', 'no-store, private');
        }
        $this->json('GET', '/api/v1/delivery/zones', ['owner_id' => 'forged'])->assertUnprocessable();
        $this->call('GET', '/api/v1/delivery/zones', [], [], [], ['HTTP_ACCEPT' => 'application/json'], str_repeat('x', 16385))->assertStatus(413);
        $this->postJson('/api/v1/delivery/zones')->assertStatus(405);
        $this->getJson('/api/v1/delivery/workers')->assertNotFound();
        $this->getJson('/api/v1/delivery/zones')->assertOk()->assertJsonCount(0, 'data');
        $this->ownerLogin();
        foreach (['index', 'create', 'history'] as $name) {
            $this->get(route('owner.delivery.'.$name))->assertOk()->assertHeader('Referrer-Policy', 'no-referrer');
        }
        $this->get(route('owner.delivery.index', ['sort' => 'owner_id']))->assertUnprocessable();
        $this->get(route('owner.delivery.history', ['worker' => (string) Str::uuid7()]))->assertNotFound();
        $this->assertDatabaseCount('store_delivery_zones', 0);
        $this->assertDatabaseCount('store_change_events', 0);
        $this->assertDatabaseCount('delivery_worker_changes', 0);
    }

    public function test_private_errors_and_public_sql_failure_redact_bindings_and_schema_unavailable_is_honest(): void
    {
        $this->zone();
        $armed = true;
        DB::listen(function ($query) use (&$armed): void {
            if ($armed && str_starts_with($query->sql, 'select') && str_contains($query->sql, 'store_delivery_zones')) {
                $armed = false;
                DB::table('delivery_workers')->insert(['name' => 'private-delivery-sentinel']);
            }
        });
        Log::spy();
        $this->getJson('/api/v1/delivery/zones')->assertStatus(500)->assertDontSee('private-delivery-sentinel')->assertDontSee('SQLSTATE')->assertHeader('Cache-Control', 'no-store, private');
        Log::shouldHaveReceived('error')->withArgs(fn ($message, $context) => $message === 'delivery_http_failed' && array_keys($context) === ['exception_type', 'request_id'])->once();
        Schema::shouldReceive('hasTable')->with('store_delivery_zones')->andReturn(false);
        $this->getJson('/api/v1/delivery/zones')->assertStatus(503)->assertJsonPath('success', false);
    }
}
