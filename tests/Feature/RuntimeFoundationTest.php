<?php

namespace Tests\Feature;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Monolog\Handler\TestHandler;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class RuntimeFoundationTest extends TestCase
{
    public function test_api_not_found_uses_error_contract_without_accept_header(): void
    {
        $response = $this->get('/api/v1/missing');

        $response->assertNotFound()->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND')
            ->assertJsonStructure(['error' => ['message', 'fields'], 'request_id'])
            ->assertHeader('X-Request-ID');
        $this->assertSame($response->headers->get('X-Request-ID'), $response->json('request_id'));
        $this->assertSame('{}', json_encode($response->getData()->error->fields));
    }

    public function test_request_ids_are_distinct_uuidv7_and_ignore_caller_input(): void
    {
        $first = $this->withHeader('X-Request-ID', 'untrusted')->get('/up');
        $second = $this->get('/up');

        $first->assertOk();
        $second->assertOk();
        foreach ([$first, $second] as $response) {
            $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $response->headers->get('X-Request-ID'));
        }
        $this->assertNotSame($first->headers->get('X-Request-ID'), $second->headers->get('X-Request-ID'));
    }

    public function test_web_errors_keep_framework_html_behavior(): void
    {
        config(['app.debug' => false]);
        $response = $this->get('/missing-web-page');

        $response->assertNotFound()->assertHeader('X-Request-ID');
        $this->assertStringContainsString('text/html', $response->headers->get('Content-Type'));
        $this->assertStringNotContainsString('"success":false', $response->getContent());
    }

    public function test_internal_errors_are_safe_even_with_debug_enabled(): void
    {
        Route::get('/api/v1/test-internal', function () {
            throw new RuntimeException('secret-password SELECT * FROM users C:\\private\\config.php stack trace');
        });

        foreach ([true, false] as $debug) {
            config(['app.debug' => $debug]);
            $response = $this->get('/api/v1/test-internal');
            $response->assertStatus(500)->assertJsonPath('error.code', 'INTERNAL_ERROR')
                ->assertJsonPath('error.message', 'حدث خطأ داخلي. يرجى المحاولة لاحقاً.')
                ->assertHeader('X-Request-ID');
            foreach (['secret-password', 'SELECT', 'private', 'trace', 'exception'] as $sensitive) {
                $this->assertStringNotContainsString($sensitive, $response->getContent());
            }
        }
    }

    public function test_validation_errors_include_field_messages(): void
    {
        Route::post('/api/v1/test-validation', function () {
            throw ValidationException::withMessages(['name' => ['Name is required.']]);
        });

        $this->post('/api/v1/test-validation')->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonPath('error.fields.name.0', 'Name is required.');
    }

    public function test_framework_errors_preserve_status_codes_and_protocol_headers(): void
    {
        $cases = [
            [new AuthenticationException, 401, 'UNAUTHENTICATED'],
            [new AuthorizationException, 403, 'FORBIDDEN'],
            [new ModelNotFoundException, 404, 'RESOURCE_NOT_FOUND'],
            [new HttpException(409), 409, 'RESOURCE_CONFLICT'],
            [new ThrottleRequestsException('private detail', null, ['Retry-After' => '30']), 429, 'RATE_LIMITED'],
            [new HttpException(503, 'private detail', null, ['Retry-After' => '60']), 503, 'SERVICE_UNAVAILABLE'],
        ];

        foreach ($cases as $index => [$exception, $status, $code]) {
            Route::get('/api/v1/test-error-'.$index, function () use ($exception) {
                throw $exception;
            });
            $response = $this->get('/api/v1/test-error-'.$index);
            $response->assertStatus($status)->assertJsonPath('error.code', $code)->assertHeader('X-Request-ID');
            $this->assertSame($response->headers->get('X-Request-ID'), $response->json('request_id'));
            if ($exception instanceof HttpException && isset($exception->getHeaders()['Retry-After'])) {
                $response->assertHeader('Retry-After', $exception->getHeaders()['Retry-After']);
            }
        }
    }

    public function test_api_method_not_allowed_preserves_allow_header(): void
    {
        Route::post('/api/v1/test-method', fn () => response()->noContent());
        $this->get('/api/v1/test-method')->assertStatus(405)
            ->assertJsonPath('error.code', 'METHOD_NOT_ALLOWED')->assertHeader('Allow', 'POST');
    }

    public function test_request_context_is_logged_and_cleared_after_response(): void
    {
        $handler = new TestHandler;
        Log::channel()->getLogger()->pushHandler($handler);
        Route::get('/api/v1/test-context', function () {
            Log::info('Foundation context test');

            return response()->json(['request_id' => request()->attributes->get('request_id')]);
        });

        $response = $this->get('/api/v1/test-context?token=do-not-log');
        $record = $handler->getRecords()[0];
        $this->assertSame($response->json('request_id'), $record->context['request_id']);
        $this->assertSame('GET', $record->context['method']);
        $this->assertSame('api/v1/test-context', $record->context['path']);
        $this->assertSame('testing', $record->context['environment']);
        $this->assertStringNotContainsString('do-not-log', json_encode($record->context));
        Log::info('After request');
        $this->assertArrayNotHasKey('request_id', $handler->getRecords()[1]->context);
    }
}
