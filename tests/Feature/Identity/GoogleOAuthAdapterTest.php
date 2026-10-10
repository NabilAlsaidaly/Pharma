<?php

namespace Tests\Feature\Identity;

use App\Domains\Identity\Domain\Exceptions\AuthenticationFailed;
use App\Domains\Identity\Domain\Exceptions\AuthProviderUnavailable;
use App\Domains\Identity\Domain\Exceptions\InvalidExternalIdentity;
use App\Domains\Identity\Infrastructure\ExternalServices\Google\SocialiteGoogleOAuthClient;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request as ProviderRequest;
use GuzzleHttp\Psr7\Response;
use Illuminate\Http\Request;
use Laravel\Socialite\Contracts\Factory;
use Tests\TestCase;

class GoogleOAuthAdapterTest extends TestCase
{
    private function adapter(Request $request, MockHandler $transport): SocialiteGoogleOAuthClient
    {
        config(['services.google' => [
            'client_id' => 'test-client', 'client_secret' => 'test-secret',
            'redirect' => 'https://backend.test/api/v1/auth/google/callback',
            'guzzle' => ['handler' => HandlerStack::create($transport)],
        ]]);
        $session = app('session.store');
        $session->start();
        $request->setLaravelSession($session);

        return new SocialiteGoogleOAuthClient(app(Factory::class), app('config'), $request);
    }

    private function transport(array $profile): MockHandler
    {
        return new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], json_encode(['access_token' => 'transient-test-token', 'token_type' => 'Bearer'])),
            new Response(200, ['Content-Type' => 'application/json'], json_encode($profile)),
        ]);
    }

    public function test_authorization_url_has_only_identity_scopes_and_session_backed_state(): void
    {
        $request = Request::create('/api/v1/auth/google/redirect');
        $adapter = $this->adapter($request, new MockHandler);
        $url = $adapter->authorizationUrl();
        parse_str(parse_url($url, PHP_URL_QUERY), $query);
        $this->assertSame('openid profile email', $query['scope']);
        $this->assertNotEmpty($query['state']);
        $this->assertSame($request->session()->get('state'), $query['state']);
        $this->assertStringNotContainsString('test-secret', $url);
    }

    public function test_mismatched_or_missing_state_fails_before_any_provider_request(): void
    {
        foreach ([[], ['state' => 'wrong']] as $query) {
            $transport = $this->transport(['sub' => '123', 'email' => 'customer@example.test']);
            $request = Request::create('/api/v1/auth/google/callback', 'GET', $query);
            $adapter = $this->adapter($request, $transport);
            $request->session()->put('state', 'expected');
            try {
                $adapter->authenticatedIdentity();
                $this->fail('Invalid OAuth state was accepted.');
            } catch (AuthenticationFailed) {
                $this->assertCount(2, $transport);
                $this->assertFalse($request->session()->has('state'));
            }
        }
    }

    public function test_valid_identity_is_mapped_without_tokens_and_callback_state_cannot_be_replayed(): void
    {
        $request = Request::create('/api/v1/auth/google/callback', 'GET', ['state' => 'expected', 'code' => 'transient-code']);
        $adapter = $this->adapter($request, $this->transport(['sub' => '123', 'email' => 'customer@example.test', 'email_verified' => true]));
        $request->session()->put('state', 'expected');
        $identity = $adapter->authenticatedIdentity();
        $this->assertSame('123', $identity->googleSub);
        $this->assertSame('customer@example.test', $identity->name);
        $this->assertSame(['googleSub', 'name', 'email', 'avatarUrl'], array_keys(get_object_vars($identity)));
        $this->assertFalse($request->session()->has('state'));
        $this->expectException(AuthenticationFailed::class);
        $adapter->authenticatedIdentity();
    }

    public function test_missing_subject_email_or_explicitly_unverified_email_is_rejected(): void
    {
        foreach ([
            ['email' => 'customer@example.test'],
            ['sub' => '123'],
            ['sub' => '123', 'email' => 'customer@example.test', 'email_verified' => false],
            ['sub' => '123', 'email' => 'customer@example.test', 'email_verified' => 'false'],
        ] as $profile) {
            $request = Request::create('/callback', 'GET', ['state' => 'expected', 'code' => 'test-code']);
            $adapter = $this->adapter($request, $this->transport($profile));
            $request->session()->put('state', 'expected');
            try {
                $adapter->authenticatedIdentity();
                $this->fail('Invalid external identity was accepted.');
            } catch (InvalidExternalIdentity) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_missing_credentials_is_provider_unavailable(): void
    {
        $request = Request::create('/redirect');
        $adapter = $this->adapter($request, new MockHandler);
        config(['services.google.client_secret' => '']);
        $this->expectException(AuthProviderUnavailable::class);
        $adapter->authorizationUrl();
    }

    public function test_provider_network_failure_is_sanitized(): void
    {
        $request = Request::create('/callback', 'GET', ['state' => 'expected', 'code' => 'test-code']);
        $transport = new MockHandler([new ConnectException('secret-code-and-token', new ProviderRequest('POST', 'https://google.example.test'))]);
        $adapter = $this->adapter($request, $transport);
        $request->session()->put('state', 'expected');
        try {
            $adapter->authenticatedIdentity();
            $this->fail('Provider failure was accepted.');
        } catch (AuthProviderUnavailable $exception) {
            $this->assertSame('', $exception->getMessage());
            $this->assertNull($exception->getPrevious());
        }
    }

    public function test_provider_denial_is_authentication_failure_without_network_call(): void
    {
        $transport = new MockHandler;
        $adapter = $this->adapter(Request::create('/callback', 'GET', ['error' => 'access_denied']), $transport);
        $this->expectException(AuthenticationFailed::class);
        $adapter->authenticatedIdentity();
    }
}
