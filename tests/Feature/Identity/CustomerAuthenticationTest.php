<?php

namespace Tests\Feature\Identity;

use App\Domains\Identity\Application\DTOs\GoogleIdentityData;
use App\Domains\Identity\Application\Ports\GoogleOAuthClient;
use App\Domains\Identity\Domain\Enums\CustomerAccountStatus;
use App\Domains\Identity\Domain\Exceptions\AuthProviderUnavailable;
use App\Domains\Identity\Infrastructure\Persistence\Models\Customer;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Log;
use Monolog\Handler\TestHandler;
use RuntimeException;
use Tests\Fakes\FakeGoogleOAuthClient;
use Tests\IdentityTestCase;

class CustomerAuthenticationTest extends IdentityTestCase
{
    private FakeGoogleOAuthClient $google;

    protected function setUp(): void
    {
        parent::setUp();
        $this->google = new FakeGoogleOAuthClient($this->identity());
        $this->app->instance(GoogleOAuthClient::class, $this->google);
    }

    public function test_google_redirect_retains_safe_return_path_and_request_id(): void
    {
        $this->get('/api/v1/auth/google/redirect?return_to=%2Fcart')
            ->assertRedirect('https://accounts.google.com/oauth?test-provider=1')
            ->assertSessionHas('identity.return_to', '/cart')->assertHeader('X-Request-ID');
    }

    public function test_callback_creates_customer_and_establishes_session_with_regenerated_id(): void
    {
        $this->get('/api/v1/auth/google/redirect?return_to=%2Fcart');
        $oldId = $this->app['session']->getId();
        $response = $this->get('/api/v1/auth/google/callback');
        $response->assertRedirect('https://frontend.test/cart')->assertSessionMissing('identity.return_to');
        $this->assertAuthenticated('customer');
        $this->assertNotSame($oldId, $this->app['session']->getId());
        $this->assertDatabaseCount('customers', 1);
        $this->continueBrowserSession($response);
        $this->get('/api/v1/auth/me')->assertOk()->assertJsonPath('data.id', Customer::query()->sole()->id);
    }

    public function test_repeated_callback_reuses_subject_and_updates_profile(): void
    {
        $first = $this->get('/api/v1/auth/google/callback');
        $customerId = Customer::query()->sole()->id;
        $this->continueBrowserSession($first);
        $this->google->identity = new GoogleIdentityData('google-123', 'Changed', 'changed@example.test', null);
        $this->get('/api/v1/auth/google/callback')->assertRedirect('https://frontend.test/');
        $this->assertDatabaseCount('customers', 1);
        $this->assertDatabaseHas('customers', ['id' => $customerId, 'name' => 'Changed', 'email' => 'changed@example.test']);
    }

    public function test_untrusted_return_paths_cannot_create_external_redirect(): void
    {
        foreach (['https://evil.test', '//evil.test', '/\\evil.test', '/%2fevil.test', '/%255cevil.test', 'javascript:alert(1)', 'data:text/html,bad', '/%0d%0aLocation:evil', str_repeat('a', 2049)] as $path) {
            $this->get('/api/v1/auth/google/redirect?'.http_build_query(['return_to' => $path]))
                ->assertSessionHas('identity.return_to', '/');
            $this->get('/api/v1/auth/google/callback')->assertRedirect('https://frontend.test/');
        }
    }

    public function test_suspended_customer_cannot_receive_authenticated_session(): void
    {
        $this->customer(CustomerAccountStatus::Suspended);
        $this->get('/api/v1/auth/google/callback')->assertRedirect('https://frontend.test/auth/error?code=ACCOUNT_SUSPENDED');
        $this->assertGuest('customer');
        $this->assertDatabaseCount('customers', 1);
    }

    public function test_unauthenticated_and_bearer_only_requests_use_401_error_contract(): void
    {
        foreach ([[], ['Authorization' => 'Bearer 1|unsupported-token']] as $headers) {
            $response = $this->get('/api/v1/auth/me', $headers);
            $response->assertUnauthorized()->assertJsonPath('error.code', 'UNAUTHENTICATED');
            $this->assertSame($response->headers->get('X-Request-ID'), $response->json('request_id'));
        }
    }

    public function test_current_customer_exposes_only_safe_fields(): void
    {
        $response = $this->get('/api/v1/auth/google/callback');
        $this->continueBrowserSession($response);
        $me = $this->get('/api/v1/auth/me');
        $me->assertOk()->assertJsonPath('success', true)->assertJsonPath('data.account_status', 'active');
        $this->assertSame(['id', 'name', 'email', 'avatar_url', 'account_status', 'last_login_at'], array_keys($me->json('data')));
        foreach (['google_sub', 'password', 'access_token', 'refresh_token', 'session_id'] as $field) {
            $this->assertArrayNotHasKey($field, $me->json('data'));
        }
    }

    public function test_logout_invalidates_session_and_old_cookie_cannot_authenticate(): void
    {
        $login = $this->get('/api/v1/auth/google/callback');
        $this->continueBrowserSession($login);
        $oldCookie = $login->getCookie(config('session.cookie'), false)->getValue();
        $oldToken = $this->app['session']->token();
        $logout = $this->post('/api/v1/auth/logout');
        $logout->assertOk()->assertJsonPath('success', true);
        $this->assertGuest('customer');
        $this->assertNotSame($oldToken, $this->app['session']->token());
        $this->continueBrowserSession($logout);
        $this->get('/api/v1/auth/me')->assertUnauthorized();
        $this->app['auth']->forgetGuards();
        $this->withUnencryptedCookie(config('session.cookie'), $oldCookie)->get('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_suspension_revokes_existing_authenticated_session(): void
    {
        $login = $this->get('/api/v1/auth/google/callback');
        Customer::query()->sole()->update(['account_status' => CustomerAccountStatus::Suspended]);
        $this->continueBrowserSession($login);
        $blocked = $this->get('/api/v1/auth/me');
        $blocked->assertForbidden()->assertJsonPath('error.code', 'ACCOUNT_SUSPENDED');
        $this->assertSame($blocked->headers->get('X-Request-ID'), $blocked->json('request_id'));
        $this->assertGuest('customer');
        $this->continueBrowserSession($blocked);
        $this->get('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_provider_failure_redirect_and_logs_exclude_sensitive_details(): void
    {
        $handler = new TestHandler;
        Log::channel()->getLogger()->pushHandler($handler);
        $this->google->failure = new RuntimeException('client-secret auth-code google-sub email@example.test access-token');
        $response = $this->get('/api/v1/auth/google/callback?code=private-code');
        $response->assertRedirect('https://frontend.test/auth/error?code=AUTHENTICATION_FAILED');
        $this->assertGuest('customer');
        $this->assertStringNotContainsString('client-secret', $response->headers->get('Location'));
        $records = $handler->getRecords();
        $this->assertCount(1, $records);
        $this->assertSame($response->headers->get('X-Request-ID'), $records[0]->context['request_id']);
        foreach (['client-secret', 'auth-code', 'google-sub', 'email@example.test', 'access-token', 'private-code'] as $secret) {
            $this->assertStringNotContainsString($secret, json_encode($records[0]->context));
        }
    }

    public function test_unavailable_provider_returns_only_safe_error_code(): void
    {
        $this->google->failure = new AuthProviderUnavailable;
        $this->get('/api/v1/auth/google/redirect')->assertRedirect('https://frontend.test/auth/error?code=AUTH_PROVIDER_UNAVAILABLE');
    }

    public function test_google_auth_rate_limit_is_shared_by_redirect_and_callback_for_client_ip(): void
    {
        config(['identity.google_auth_rate_limit_per_minute' => 1]);
        $this->get('/api/v1/auth/google/redirect')->assertRedirect();
        $this->get('/api/v1/auth/google/callback')->assertStatus(429)->assertJsonPath('error.code', 'RATE_LIMITED')->assertHeader('Retry-After');
    }

    public function test_stateful_logout_requires_csrf_token_and_accepts_valid_token(): void
    {
        // Strengthen the test: Laravel normally skips request-forgery checks in PHPUnit.
        $this->app->bind(PreventRequestForgery::class, function ($app) {
            return new class($app, $app['encrypter']) extends PreventRequestForgery
            {
                protected function runningUnitTests(): bool
                {
                    return false;
                }
            };
        });
        $login = $this->get('/api/v1/auth/google/callback');
        $this->continueBrowserSession($login);
        $this->post('/api/v1/auth/logout')->assertStatus(419)->assertJsonPath('error.code', 'SESSION_EXPIRED');
        $token = $this->app['session']->token();
        $this->post('/api/v1/auth/logout', [], ['X-CSRF-TOKEN' => $token])->assertOk();
    }

    public function test_csrf_bootstrap_and_credentialed_cors_allow_only_configured_origin(): void
    {
        $csrf = $this->get('/sanctum/csrf-cookie', ['Origin' => 'https://frontend.test']);
        $csrf->assertNoContent()->assertCookie('XSRF-TOKEN')->assertCookie(config('session.cookie'));
        $csrf->assertHeader('Access-Control-Allow-Origin', 'https://frontend.test')
            ->assertHeader('Access-Control-Allow-Credentials', 'true')->assertHeader('Access-Control-Expose-Headers', 'X-Request-ID');
        $this->assertTrue($csrf->getCookie(config('session.cookie'), false)->isHttpOnly());
        $this->assertTrue($csrf->getCookie(config('session.cookie'), false)->isSecure());
        $evil = $this->get('/sanctum/csrf-cookie', ['Origin' => 'https://evil.test']);
        // With one configured origin, the CORS library emits that origin even for
        // another caller; browsers reject it because it does not match the caller.
        $evil->assertHeader('Access-Control-Allow-Origin', 'https://frontend.test');
        $this->assertNotSame('https://evil.test', $evil->headers->get('Access-Control-Allow-Origin'));
    }
}
