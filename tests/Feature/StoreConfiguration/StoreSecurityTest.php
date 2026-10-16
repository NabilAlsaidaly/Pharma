<?php

namespace Tests\Feature\StoreConfiguration;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\StoreConfigurationTestCase;

class StoreSecurityTest extends StoreConfigurationTestCase
{
    private function destinations(): array
    {
        $id = (string) Str::uuid7();

        return [['GET', 'owner.settings.media', ['id' => $id]], ['GET', 'owner.settings.index', []], ['GET', 'owner.settings.history', []], ['GET', 'owner.settings.group', ['group' => 'profile']], ['GET', 'owner.settings.create', ['group' => 'zones']], ['GET', 'owner.settings.edit', ['group' => 'zones', 'id' => $id]], ['POST', 'owner.settings.mutate', ['group' => 'profile']], ['GET', 'owner.content.index', ['type' => 'pages']], ['GET', 'owner.content.create', ['type' => 'banners']], ['GET', 'owner.content.edit', ['type' => 'policies', 'id' => $id]], ['POST', 'owner.content.mutate', ['type' => 'faqs']]];
    }

    public function test_all_eleven_new_routes_deny_guests_and_customers_and_no_public_write_aliases(): void
    {
        foreach ([false, true] as $customer) {
            if ($customer) {
                $this->actingAs($this->customer(), 'customer');
            }
            foreach ($this->destinations() as [$method,$route,$parameters]) {
                $this->call($method, route($route, $parameters))->assertRedirect(route('owner.login'));
            }
        }
        $this->assertDatabaseCount('store_change_events', 0);
        $this->postJson('/api/v1/storefront/configuration', $this->command(['name' => 'forged']))->assertStatus(405);
        $this->postJson('/api/v1/storefront/pages/test', $this->command())->assertStatus(405);
        $this->get('/admin/login')->assertNotFound();
    }

    public static function revokedStates(): array
    {
        return [['disabled'], ['restricted'], ['idle'], ['revoked']];
    }

    #[DataProvider('revokedStates')]
    public function test_every_new_destination_retains_owner_revocation(string $state): void
    {
        foreach ($this->destinations() as [$method,$route,$parameters]) {
            DB::table('management_users')->where('id', $this->owner->id)->update(['role' => 'owner', 'status' => 'active']);
            $this->owner = $this->owner->fresh();
            $this->ownerLogin();
            match ($state) {
                'disabled' => $this->owner->forceFill(['status' => 'disabled'])->save(),
                'restricted' => DB::table('management_users')->where('id', $this->owner->id)->update(['role' => 'restricted']),
                'idle' => $this->withSession(['owner_last_activity' => now()->subHours(4)->timestamp]),
                'revoked' => $this->withSession(['owner_credential_version' => '999']),
            };
            $this->call($method, route($route, $parameters))->assertForbidden();
        }
        $this->assertDatabaseCount('store_change_events', 0);
    }

    public function test_native_csrf_payload_allowlists_body_limits_private_headers_and_quotas(): void
    {
        $this->ownerLogin();
        $this->app->instance(PreventRequestForgery::class, new class($this->app, $this->app['encrypter']) extends PreventRequestForgery
        {
            protected function runningUnitTests(): bool
            {
                return false;
            }
        });
        foreach (['owner.settings.mutate' => ['group' => 'profile'], 'owner.content.mutate' => ['type' => 'pages']] as $route => $parameters) {
            $this->post(route($route, $parameters))->assertStatus(419);
        }
        $token = Str::random(40);
        $this->withSession(['_token' => $token]);
        $url = route('owner.settings.mutate', ['group' => 'profile']);
        $payload = $this->command(['name' => 'بيانات آمنة']) + ['_token' => $token];
        $this->post($url, $payload + ['owner_id' => (string) Str::uuid7()])->assertStatus(422)->assertHeader('Referrer-Policy', 'no-referrer');
        $this->postJson($url, $payload + ['padding' => str_repeat('x', 66000)])->assertStatus(413);
        $this->post($url, $payload)->assertRedirect();
        $response = $this->get(route('owner.settings.group', ['group' => 'profile']))->assertOk();
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertFalse(session()->has('_old_input'));
        $key = 'store-write:'.hash('sha256', $this->owner->id);
        for ($i = 0; $i < 60; $i++) {
            RateLimiter::hit($key, 60);
        }
        $this->post($url, $payload)->assertStatus(429)->assertHeader('Retry-After');
    }

    public function test_private_sql_errors_log_only_exception_class_and_request_id(): void
    {
        $this->ownerLogin();
        Log::spy();
        $armed = true;
        DB::listen(function ($query) use (&$armed): void {
            if ($armed && str_starts_with($query->sql, 'insert into `store_profiles`')) {
                $armed = false;
                DB::table('store_change_events')->insert(['request_id' => 'private-body-sentinel']);
            }
        });
        $this->post(route('owner.settings.mutate', ['group' => 'profile']), $this->command(['name' => 'private-body-sentinel']))->assertStatus(500)->assertDontSee('SQLSTATE')->assertDontSee('private-body-sentinel');
        Log::shouldHaveReceived('error')->withArgs(fn ($message, $context) => $message === 'owner_portal_failed' && array_keys($context) === ['exception_type', 'request_id'])->once();
        $this->assertDatabaseCount('store_profiles', 0);
        $this->assertDatabaseCount('store_change_events', 0);
    }

    public function test_schema_inventory_restrictive_foreign_keys_and_published_pointer_integrity(): void
    {
        $id = $this->content();
        $other = $this->content(values: ['title' => 'آخر', 'slug' => 'another-fixture', 'body' => 'نص']);
        foreach ([fn () => DB::table('content_pages')->where('id', $id)->update(['published_version' => 999]), fn () => DB::table('content_pages')->where('id', $id)->delete()] as $invalid) {
            try {
                $invalid();
                $this->fail('Actual MySQL foreign key must prevent dangling publication or destructive deletion.');
            } catch (QueryException $e) {
                $this->assertContains($e->errorInfo[1], [1451, 1452]);
            }
        }
        $routes = collect(Route::getRoutes()->getRoutes())->filter(fn ($r) => str_starts_with((string) $r->getName(), 'owner.'));
        $this->assertCount(40, $routes);
        $this->assertDatabaseCount('content_pages', 2);
        $this->assertDatabaseHas('content_pages_revisions', ['record_id' => $other, 'version' => 1]);
    }
}
