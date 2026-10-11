<?php

namespace Tests\Feature\Collections;

use App\Domains\Cart\Infrastructure\Persistence\Models\Cart;
use App\Domains\Identity\Application\Ports\GoogleOAuthClient;
use App\Domains\Identity\Interfaces\Http\Middleware\EnsureActiveCustomer;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\DB;
use Tests\Fakes\FakeGoogleOAuthClient;
use Tests\PersonalCollectionsTestCase;

class CollectionsSecurityTest extends PersonalCollectionsTestCase
{
    public function test_all_mutations_require_real_csrf_and_valid_token_succeeds(): void
    {
        $this->app->bind(PreventRequestForgery::class, fn ($app) => new class($app, $app['encrypter']) extends PreventRequestForgery
        {
            protected function runningUnitTests(): bool
            {
                return false;
            }
        });
        $p = $this->product(['price_usd' => '1']);
        $id = $this->addItem($p)['item']['id'];
        foreach ([['POST', '/api/v1/favorites', ['product_id' => $p->id]], ['DELETE', '/api/v1/favorites/'.$p->id, []], ['POST', '/api/v1/cart/items', ['product_id' => $p->id, 'quantity' => 1]], ['PATCH', '/api/v1/cart/items/'.$id, ['quantity' => 2, 'expected_version' => '1']], ['DELETE', '/api/v1/cart/items/'.$id, []]] as [$method,$url,$body]) {
            $this->json($method, $url, $body)->assertStatus(419)->assertJsonPath('error.code', 'SESSION_EXPIRED');
        }
        $this->postJson('/api/v1/favorites', ['product_id' => $p->id], ['X-CSRF-TOKEN' => $this->app['session']->token()])->assertCreated();
        $this->assertSame(1, Cart::query()->sole()->version + 0);
    }

    public function test_actual_session_logout_and_later_login_preserve_both_collections(): void
    {
        $fake = new FakeGoogleOAuthClient($this->identity());
        $this->app->instance(GoogleOAuthClient::class, $fake);
        $p = $this->product(['price_usd' => '1']);
        $this->postJson('/api/v1/favorites', ['product_id' => $p->id]);
        $this->addItem($p);
        $this->app['auth']->forgetGuards();
        $login = $this->get('/api/v1/auth/google/callback');
        $this->continueBrowserSession($login);
        $this->getJson('/api/v1/cart')->assertJsonPath('data.item_count', 1);
        $this->getJson('/api/v1/favorites')->assertJsonCount(1, 'data');
        $logout = $this->postJson('/api/v1/auth/logout');
        $this->continueBrowserSession($logout);
        $this->getJson('/api/v1/cart')->assertUnauthorized();
        $this->getJson('/api/v1/favorites')->assertUnauthorized();
        $login = $this->get('/api/v1/auth/google/callback');
        $this->continueBrowserSession($login);
        $this->getJson('/api/v1/cart')->assertJsonPath('data.item_count', 1);
        $this->getJson('/api/v1/favorites')->assertJsonCount(1, 'data');
        $this->assertSame(1, DB::table('customers')->count());
    }

    public function test_body_byte_limit_safe_envelope_request_id_and_no_mutation(): void
    {
        $p = $this->product(['price_usd' => '1']);
        $response = $this->postJson('/api/v1/cart/items', ['product_id' => $p->id, 'quantity' => 1, 'padding' => str_repeat('x', 16384)])
            ->assertStatus(413)->assertJsonPath('error.code', 'PAYLOAD_TOO_LARGE');
        $this->assertSame($response->headers->get('X-Request-ID'), $response->json('request_id'));
        $this->assertSame(0, Cart::query()->count());
        $this->postJson('/api/v1/favorites', ['product_id' => $p->id], ['Content-Length' => '20000'])->assertStatus(413);
    }

    public function test_per_customer_rate_limits_read_write_split_and_cors_patch_delete(): void
    {
        config(['customer_collections.reads_per_minute' => 1, 'customer_collections.mutations_per_minute' => 1]);
        $p = $this->product();
        $this->getJson('/api/v1/cart')->assertOk();
        $this->getJson('/api/v1/favorites')->assertStatus(429)->assertHeader('Retry-After');
        $this->postJson('/api/v1/favorites', ['product_id' => $p->id])->assertCreated();
        $this->deleteJson('/api/v1/favorites/'.$p->id)->assertStatus(429);
        $this->actingAs($this->otherCustomer(), 'customer');
        $this->getJson('/api/v1/cart')->assertOk();
        foreach (['PATCH', 'DELETE'] as $method) {
            $response = $this->options('/api/v1/cart/items/example', [], ['Origin' => 'https://frontend.test', 'Access-Control-Request-Method' => $method, 'Access-Control-Request-Headers' => 'Content-Type,X-XSRF-TOKEN']);
            $response->assertNoContent();
            $this->assertStringContainsString($method, $response->headers->get('Access-Control-Allow-Methods'));
        }
    }

    public function test_exact_approved_routes_and_auth_middleware_no_checkout_endpoints(): void
    {
        $routes = collect(app('router')->getRoutes())->filter(fn ($r) => str_starts_with($r->uri(), 'api/v1/favorites') || str_starts_with($r->uri(), 'api/v1/cart'));
        $this->assertCount(8, $routes);
        foreach ($routes as $route) {
            $this->assertContains('auth:sanctum', $route->gatherMiddleware());
            $this->assertContains(EnsureActiveCustomer::class, $route->gatherMiddleware());
        }
        $this->getJson('/api/v1/cart/clear')->assertNotFound();
        $this->postJson('/api/v1/checkout')->assertNotFound();
    }
}
