<?php

namespace Tests\Feature\Owner;

use App\Domains\Identity\Application\Ports\GoogleOAuthClient;
use App\Domains\ManagementIdentity\Application\ProvisionOwner;
use App\Domains\ManagementIdentity\Infrastructure\Persistence\Models\ManagementUser;
use App\Domains\ManagementIdentity\Interfaces\Http\OwnerPortalRoutes;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\CatalogTestCase;
use Tests\Fakes\FakeGoogleOAuthClient;

class OwnerAuthenticationTest extends CatalogTestCase
{
    private const PASSWORD = 'Fixture-safe-passphrase-2026';

    private ManagementUser $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['owner_portal.prefix' => 'fixture-owner-portal']);
        Route::middleware('web')->group(fn () => OwnerPortalRoutes::register());
        Route::getRoutes()->refreshNameLookups();
        $this->owner = app(ProvisionOwner::class)->execute('سمية المالكة', 'owner@example.test', self::PASSWORD, self::PASSWORD);
    }

    private function login(?string $password = null, string $email = 'owner@example.test')
    {
        return $this->post(route('owner.login.submit'), ['email' => $email, 'password' => $password ?? self::PASSWORD]);
    }

    public function test_real_customer_oauth_session_stays_separate_and_customer_logout_clears_shared_owner_session(): void
    {
        $this->app->instance(GoogleOAuthClient::class, new FakeGoogleOAuthClient($this->identity('fixture-google-owner-email', $this->owner->email)));
        $google = $this->get('/api/v1/auth/google/callback')->assertRedirect('https://frontend.test/');
        $this->continueBrowserSession($google);
        $this->get(route('owner.home'))->assertRedirect(route('owner.login'));
        $login = $this->login()->assertRedirect(route('owner.home'));
        $this->continueBrowserSession($login);
        $this->get('/api/v1/auth/me')->assertOk();
        $this->get(route('owner.home'))->assertOk();
        $logout = $this->post('/api/v1/auth/logout')->assertOk();
        $this->continueBrowserSession($logout);
        $this->get(route('owner.home'))->assertRedirect(route('owner.login'));
        $this->assertGuest('owner');
        $this->assertDatabaseCount('customers', 1);
        $this->assertDatabaseCount('management_users', 1);
    }

    public function test_customer_and_owner_guards_are_isolated_even_with_matching_email(): void
    {
        $customer = $this->customer();
        $customer->update(['email' => $this->owner->email]);
        $this->actingAs($customer, 'customer');
        $this->get(route('owner.home'))->assertRedirect(route('owner.login'));
        $this->login()->assertRedirect(route('owner.home'));
        $this->assertAuthenticatedAs($this->owner, 'owner');
        $this->assertAuthenticatedAs($customer, 'customer');
        $this->post(route('owner.logout'))->assertRedirect(route('owner.login'));
        $this->assertGuest('owner');
        $this->assertAuthenticatedAs($customer, 'customer');
        $this->get(route('owner.home'))->assertRedirect(route('owner.login'));
    }

    public function test_logout_old_cookie_cannot_restore_owner_on_a_fresh_request(): void
    {
        $login = $this->login()->assertRedirect(route('owner.home'));
        $oldCookie = $login->getCookie(config('session.cookie'), false)->getValue();
        $this->continueBrowserSession($login);
        $logout = $this->post(route('owner.logout'))->assertRedirect(route('owner.login'));
        $this->continueBrowserSession($logout);
        $this->get(route('owner.home'))->assertRedirect(route('owner.login'));
        Auth::forgetGuards();
        $this->withUnencryptedCookie(config('session.cookie'), $oldCookie)->get(route('owner.home'))->assertRedirect(route('owner.login'));
        $this->assertGuest('owner');
    }

    public function test_owner_login_never_authenticates_a_customer_and_public_catalog_is_unchanged(): void
    {
        $this->login()->assertRedirect(route('owner.home'));
        $this->assertGuest('customer');
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
        $this->getJson('/api/v1/products')->assertOk();
        $this->assertSame('customer', config('auth.defaults.guard'));
        $this->assertDatabaseCount('customers', 0);
    }

    public function test_generic_failures_disabled_nonowner_and_session_fixation(): void
    {
        $oldId = $this->app['session']->getId();
        $oldToken = $this->app['session']->token();
        $bad = $this->login('wrong-password')->assertSessionHasErrors('email');
        $first = session('errors')->get('email');
        $this->login('wrong-password', 'missing@example.test')->assertSessionHasErrors('email');
        $this->assertSame($first, session('errors')->get('email'));
        $this->owner->forceFill(['status' => 'disabled'])->save();
        $this->login()->assertSessionHasErrors('email');
        $this->assertGuest('owner');
        $this->owner->forceFill(['status' => 'active'])->save();
        $restricted = new ManagementUser;
        $restricted->forceFill(['display_name' => 'Restricted', 'email' => 'restricted@example.test', 'password' => self::PASSWORD, 'role' => 'restricted', 'status' => 'active'])->save();
        $this->login(null, 'restricted@example.test')->assertSessionHasErrors('email');
        $this->login()->assertRedirect(route('owner.home'));
        $this->assertNotSame($oldId, $this->app['session']->getId());
        $this->assertNotSame($oldToken, $this->app['session']->token());
        $this->assertSame('1', session('owner_credential_version'));
        $this->get(route('owner.login'))->assertRedirect(route('owner.home'));
        $this->get(route('owner.home'))->assertOk()->assertSee('سمية المالكة');
    }

    public function test_all_protected_routes_require_owner_and_do_not_expose_public_aliases(): void
    {
        foreach (['owner.home', 'owner.profile'] as $name) {
            $this->get(route($name))->assertRedirect(route('owner.login'));
        }
        $this->patch(route('owner.profile.update'), ['display_name' => 'Forged'])->assertRedirect(route('owner.login'));
        $this->put(route('owner.password.update'), ['password' => 'Forged'])->assertRedirect(route('owner.login'));
        $this->post(route('owner.logout'))->assertRedirect(route('owner.login'));
        foreach (['/admin/login', '/login', '/register', '/forgot-password', '/reset-password', '/api/v1/owner'] as $url) {
            $this->get($url)->assertNotFound();
        }
        $routes = collect(Route::getRoutes())->filter(fn ($route) => str_starts_with((string) $route->getName(), 'owner.'));
        $this->assertCount(63, $routes);
        foreach ($routes as $route) {
            $this->assertStringStartsWith('fixture-owner-portal', $route->uri());
            $this->assertContains('web', $route->gatherMiddleware());
        }
    }

    public function test_profile_name_only_email_readonly_escaping_and_source_customers_unchanged(): void
    {
        $this->customer();
        $before = DB::table('customers')->get()->toArray();
        $this->login();
        $this->get(route('owner.profile'))->assertOk()->assertSee('owner@example.test')->assertDontSee($this->owner->password);
        $this->patch(route('owner.profile.update'), ['display_name' => '  سمية أحمد  '])->assertRedirect(route('owner.profile'));
        $this->assertSame('سمية أحمد', $this->owner->fresh()->display_name);
        foreach (['email', 'password', 'role', 'status', 'credential_version', 'id'] as $field) {
            $this->patch(route('owner.profile.update'), ['display_name' => 'Name', $field => 'forged'])->assertSessionHasErrors($field);
        }
        $this->patch(route('owner.profile.update'), ['display_name' => '<script>alert(1)</script>'])->assertSessionHasErrors('display_name');
        $this->assertEquals($before, DB::table('customers')->get()->toArray());
        $this->assertSame('owner@example.test', $this->owner->fresh()->email);
        foreach (['password', 'credential_version', 'owner_slot'] as $field) {
            $this->assertArrayNotHasKey($field, $this->owner->toArray());
        }
    }

    public static function badPasswords(): array
    {
        return ['missing-current' => [null, 'A-new-safe-password-2026', 'A-new-safe-password-2026'], 'wrong-current' => ['wrong', 'A-new-safe-password-2026', 'A-new-safe-password-2026'],
            'weak' => [self::PASSWORD, 'short', 'short'], 'mismatch' => [self::PASSWORD, 'A-new-safe-password-2026', 'mismatch'],
            'same-password' => [self::PASSWORD, self::PASSWORD, self::PASSWORD], 'oversize' => [self::PASSWORD, str_repeat('أ', 40), str_repeat('أ', 40)]];
    }

    #[DataProvider('badPasswords')]
    public function test_password_reauthentication_and_policy_fail_without_change(?string $current, string $new, string $confirmation): void
    {
        $this->login();
        $before = $this->owner->fresh()->password;
        $this->put(route('owner.password.update'), ['current_password' => $current, 'password' => $new, 'password_confirmation' => $confirmation])->assertSessionHasErrors();
        $this->assertSame($before, $this->owner->fresh()->password);
        $this->assertSame('1', $this->owner->fresh()->credential_version);
        $this->assertArrayNotHasKey('password', session('_old_input', []));
        $this->assertArrayNotHasKey('current_password', session('_old_input', []));
        $this->assertArrayNotHasKey('password_confirmation', session('_old_input', []));
    }

    public function test_password_change_revokes_old_version_preserves_current_and_rejects_old_password(): void
    {
        $this->login();
        $oldId = session()->getId();
        $new = 'New-safe-passphrase-2026';
        $this->put(route('owner.password.update'), ['current_password' => self::PASSWORD, 'password' => $new, 'password_confirmation' => $new])->assertRedirect(route('owner.profile'));
        $this->assertTrue(Hash::check($new, $this->owner->fresh()->password));
        $this->assertSame('2', $this->owner->fresh()->credential_version);
        $this->assertSame('2', session('owner_credential_version'));
        $this->assertNotSame($oldId, session()->getId());
        $this->get(route('owner.home'))->assertOk();
        $this->withSession(['owner_credential_version' => '1'])->get(route('owner.home'))->assertForbidden();
        $this->assertGuest('owner');
        $this->login()->assertSessionHasErrors('email');
        $this->login($new)->assertRedirect(route('owner.home'));
    }

    public function test_disabled_account_idle_expiration_and_nonowner_default_deny(): void
    {
        $this->login();
        $this->owner->forceFill(['status' => 'disabled'])->save();
        $this->get(route('owner.home'))->assertForbidden();
        $this->assertGuest('owner');
        $this->owner->forceFill(['status' => 'active'])->save();
        $this->login();
        $this->withSession(['owner_last_activity' => now()->subMinutes(30)->timestamp])->get(route('owner.home'))->assertForbidden();
        $this->actingAs($this->owner, 'owner')->withSession(['owner_credential_version' => null])->get(route('owner.profile'))->assertForbidden();
        DB::table('management_users')->where('id', $this->owner->id)->update(['role' => 'restricted']);
        $this->actingAs($this->owner, 'owner')->withSession(['owner_credential_version' => '1', 'owner_last_activity' => now()->timestamp])->get(route('owner.home'))->assertForbidden();
    }

    public function test_real_csrf_login_profile_password_logout_and_no_store_headers(): void
    {
        $this->app->bind(PreventRequestForgery::class, fn ($app) => new class($app, $app['encrypter']) extends PreventRequestForgery
        {
            protected function runningUnitTests(): bool
            {
                return false;
            }
        });
        $this->login()->assertStatus(419);
        $this->post(route('owner.login.submit'), ['email' => $this->owner->email, 'password' => self::PASSWORD, '_token' => session()->token()])->assertRedirect(route('owner.home'));
        $this->get(route('owner.profile'))->assertOk()->assertHeader('Cache-Control', 'max-age=0, no-store, private')->assertHeader('X-Frame-Options', 'DENY');
        $this->patch(route('owner.profile.update'), ['display_name' => 'X'])->assertStatus(419);
        $this->put(route('owner.password.update'), [])->assertStatus(419);
        $this->post(route('owner.logout'))->assertStatus(419);
        $this->post(route('owner.logout'), ['_token' => session()->token()])->assertRedirect(route('owner.login'));
        $this->assertGuest('owner');
    }

    public function test_identity_ip_and_ip_spraying_limits_without_email_keys(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->login('wrong-password')->assertSessionHasErrors('email');
        }
        $this->login('wrong-password')->assertStatus(429);
        for ($i = 0; $i < 15; $i++) {
            $this->login('wrong-password', 'missing-'.$i.'@example.test')->assertSessionHasErrors('email');
        }
        $this->login('wrong-password', 'fresh@example.test')->assertStatus(429);
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.15']);
        $this->login()->assertRedirect(route('owner.home'));
    }

    public function test_meaningful_queries_and_safe_structured_audit(): void
    {
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->login();
        $login = count(DB::getQueryLog());
        Auth::forgetGuards();
        DB::flushQueryLog();
        $this->get(route('owner.home'))->assertOk();
        $home = count(DB::getQueryLog());
        Auth::forgetGuards();
        DB::flushQueryLog();
        $this->get(route('owner.profile'))->assertOk();
        $profile = count(DB::getQueryLog());
        Auth::forgetGuards();
        DB::flushQueryLog();
        $new = 'Another-safe-passphrase-2026';
        $this->put(route('owner.password.update'), ['current_password' => self::PASSWORD, 'password' => $new, 'password_confirmation' => $new])->assertRedirect();
        $change = count(DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertLessThanOrEqual(5, $login);
        $this->assertLessThanOrEqual(3, $home);
        $this->assertLessThanOrEqual(3, $profile);
        $this->assertLessThanOrEqual(5, $change);
        fwrite(STDOUT, 'OWNER_QUERY_COUNTS '.json_encode(compact('login', 'home', 'profile', 'change')).PHP_EOL);
    }

    public function test_audit_context_never_contains_credentials_email_or_request_body(): void
    {
        Log::spy();
        $this->login('wrong-password');
        $this->login();
        $this->patch(route('owner.profile.update'), ['display_name' => 'Changed Owner']);
        $this->post(route('owner.logout'));
        foreach (['login_failed', 'login_succeeded', 'profile_name_changed', 'logout'] as $event) {
            Log::shouldHaveReceived('info')->with('owner_security', \Mockery::on(function ($context) use ($event): bool {
                return $context['event'] === $event && array_keys($context) === ['event', 'owner_id', 'request_id']
                    && ! str_contains(json_encode($context), 'example.test') && ! str_contains(json_encode($context), self::PASSWORD);
            }))->once();
        }
    }

    public function test_invalid_login_bodies_consume_limits_and_forwarded_ip_cannot_bypass(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('owner.login.submit'), ['email' => $this->owner->email, 'password' => []])->assertSessionHasErrors('password');
        }
        $this->withHeader('X-Forwarded-For', '192.0.2.99')->login()->assertStatus(429)->assertHeader('Retry-After');
        $this->assertGuest('owner');
    }

    public function test_success_clears_only_identity_failures_and_keeps_ip_spray_counter(): void
    {
        for ($i = 0; $i < 4; $i++) {
            $this->login('wrong-password')->assertSessionHasErrors('email');
        }
        $this->login()->assertRedirect(route('owner.home'));
        for ($i = 0; $i < 15; $i++) {
            $this->login('wrong-password', 'other-'.$i.'@example.test')->assertSessionHasErrors('email');
        }
        $this->login()->assertStatus(429);
    }

    public function test_password_nul_and_multibyte_current_password_never_reach_hasher(): void
    {
        $this->login();
        foreach (["bad\0input", str_repeat('أ', 40)] as $current) {
            $this->put(route('owner.password.update'), ['current_password' => $current, 'password' => 'New-valid-passphrase', 'password_confirmation' => 'New-valid-passphrase'])->assertSessionHasErrors('current_password');
        }
        $this->assertSame('1', $this->owner->fresh()->credential_version);
    }

    public function test_production_insecure_http_and_spoofed_https_fail_closed(): void
    {
        $this->app->instance('env', 'production');
        config(['session.secure' => true, 'session.http_only' => true]);
        $this->withHeader('X-Forwarded-Proto', 'https')->get('http://localhost/fixture-owner-portal/login')->assertStatus(503)->assertHeader('Cache-Control', 'max-age=0, no-store, private');
        config(['session.secure' => false]);
        $this->get('https://localhost/fixture-owner-portal/login')->assertStatus(503);
        config(['session.secure' => true]);
        $secure = $this->get('https://localhost/fixture-owner-portal/login')->assertOk();
        $cookie = $secure->getCookie(config('session.cookie'));
        $this->assertTrue($cookie->isSecure());
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('lax', $cookie->getSameSite());
        config(['session.http_only' => false]);
        $this->get('https://localhost/fixture-owner-portal/login')->assertStatus(503);
    }

    public function test_safe_html_error_does_not_leak_sql_or_customer_json_envelope(): void
    {
        Route::middleware('web')->get('fixture-owner-portal/failure-fixture', function () {
            throw new \RuntimeException('SECRET-SQL-SENTINEL');
        })->name('owner.failure.fixture');
        $this->get('/fixture-owner-portal/failure-fixture')->assertStatus(500)->assertDontSee('SECRET-SQL-SENTINEL')->assertHeader('Content-Type', 'text/html; charset=utf-8')->assertHeader('Cache-Control', 'max-age=0, no-store, private');
    }

    public function test_validation_always_redirects_to_fixed_owner_routes_even_with_external_referer_and_json_accept(): void
    {
        $this->withHeader('Referer', 'https://external.example.test/forged')->withHeader('Accept', 'application/json');
        $this->login('wrong-password')->assertRedirect(route('owner.login'))->assertSessionHasErrors('email')->assertHeader('Cache-Control', 'max-age=0, no-store, private');
        $this->post(route('owner.login.submit'), ['email' => 'bad', 'password' => 'bad', 'untrusted_secret' => 'secret-fixture'])->assertRedirect(route('owner.login'));
        $this->assertArrayNotHasKey('untrusted_secret', session('_old_input', []));
        $this->login()->assertRedirect(route('owner.home'));
        $this->patch(route('owner.profile.update'), ['display_name' => '!!!'])->assertRedirect(route('owner.profile'));
        $this->put(route('owner.password.update'), ['current_password' => 'bad', 'password' => 'New-valid-passphrase', 'password_confirmation' => 'New-valid-passphrase'])->assertRedirect(route('owner.profile'));
        $this->assertSame([], session('_old_input', []));
    }
}
