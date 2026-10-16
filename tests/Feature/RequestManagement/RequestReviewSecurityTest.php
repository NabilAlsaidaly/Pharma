<?php

namespace Tests\Feature\RequestManagement;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\OwnerDashboardTestCase;

class RequestReviewSecurityTest extends OwnerDashboardTestCase
{
    private function destinations(string $id): array
    {
        return [['GET', 'owner.requests.index', []], ['GET', 'owner.requests.show', ['id' => $id]],
            ['POST', 'owner.requests.review', ['id' => $id]], ['POST', 'owner.requests.preview', []], ['POST', 'owner.requests.confirm', []]];
    }

    public function test_guest_and_customer_cannot_read_or_mutate_any_request_review_destination(): void
    {
        $id = $this->requestFixture();
        foreach ($this->destinations($id) as [$method, $route, $args]) {
            $this->call($method, route($route, $args))->assertRedirect(route('owner.login'));
        }
        $this->actingAs($this->customer(), 'customer');
        foreach ($this->destinations($id) as [$method, $route, $args]) {
            $this->call($method, route($route, $args))->assertRedirect(route('owner.login'));
        }
        $this->assertDatabaseCount('preliminary_request_review_events', 0);
    }

    public static function revokedStates(): array
    {
        return [['disabled'], ['restricted'], ['idle'], ['revoked']];
    }

    #[DataProvider('revokedStates')]
    public function test_owner_security_still_revokes_every_review_destination(string $state): void
    {
        $id = $this->requestFixture();
        foreach ($this->destinations($id) as [$method, $route, $args]) {
            DB::table('management_users')->where('id', $this->owner->id)->update(['role' => 'owner', 'status' => 'active']);
            $this->owner = $this->owner->fresh();
            $this->ownerLogin();
            match ($state) {
                'disabled' => $this->owner->forceFill(['status' => 'disabled'])->save(),
                'restricted' => DB::table('management_users')->where('id', $this->owner->id)->update(['role' => 'restricted']),
                'idle' => $this->withSession(['owner_last_activity' => now()->subHours(4)->timestamp]),
                'revoked' => $this->withSession(['owner_credential_version' => '999']),
            };
            $this->call($method, route($route, $args))->assertForbidden();
        }
        $this->assertDatabaseCount('preliminary_request_review_events', 0);
    }

    public function test_actual_csrf_checks_all_writes_and_identity_forgery_unknown_fields_and_body_are_rejected(): void
    {
        $id = $this->requestFixture();
        $this->ownerLogin();
        $this->app->instance(PreventRequestForgery::class, new class($this->app, $this->app['encrypter']) extends PreventRequestForgery
        {
            protected function runningUnitTests(): bool
            {
                return false;
            }
        });
        foreach (['owner.requests.review' => ['id' => $id], 'owner.requests.preview' => [], 'owner.requests.confirm' => []] as $route => $args) {
            $this->post(route($route, $args))->assertStatus(419);
        }
        $token = Str::random(40);
        $this->withSession(['_token' => $token]);
        $payload = ['_token' => $token, 'operation_id' => (string) Str::uuid7(), 'expected_version' => '0', 'kind' => 'note', 'note' => 'آمنة'];
        $url = route('owner.requests.review', ['id' => $id]);
        $this->post($url, $payload + ['owner_id' => $this->owner->id])->assertUnprocessable();
        $this->post($url, $payload + ['previous_status' => 'reviewed'])->assertUnprocessable();
        $this->postJson($url, $payload + ['padding' => str_repeat('x', 17000)])->assertStatus(413);
        $this->post($url, $payload)->assertRedirect();
        $this->assertDatabaseHas('preliminary_request_review_events', ['owner_id' => $this->owner->id, 'note' => 'آمنة']);
    }

    public function test_private_no_store_errors_and_logs_do_not_expose_note_contact_or_sql(): void
    {
        $id = $this->requestFixture();
        $this->ownerLogin();
        $response = $this->get(route('owner.requests.show', ['id' => $id]))->assertOk()->assertHeader('Referrer-Policy', 'no-referrer');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->get(route('owner.search', ['q' => $id]))->assertOk()->assertDontSee('Private-address-sentinel')->assertDontSee('private-contact-sentinel@example.test');
        Log::spy();
        DB::listen(function ($query): void {
            if (str_starts_with($query->sql, 'insert into `preliminary_request_reviews`')) {
                // Real failing SQL after aggregate insert, without DDL implicit commits in the test transaction.
                DB::table('preliminary_request_review_events')->insert(['note' => 'private-note-sentinel']);
            }
        });
        $this->post(route('owner.requests.review', ['id' => $id]), ['operation_id' => (string) Str::uuid7(), 'expected_version' => '0', 'kind' => 'note', 'note' => 'private-note-sentinel'])->assertStatus(500)->assertDontSee('SQLSTATE')->assertDontSee('private-note-sentinel');
        Log::shouldHaveReceived('error')->withArgs(fn ($name, $context) => $name === 'owner_portal_failed' && array_keys($context) === ['exception_type', 'request_id'])->once();
        $this->assertDatabaseCount('preliminary_request_reviews', 0);
    }

    public function test_write_attempt_quota_has_retry_after_and_no_public_mutation_alias(): void
    {
        $id = $this->requestFixture();
        $this->ownerLogin();
        $key = 'owner-review:'.hash('sha256', $this->owner->id);
        RateLimiter::clear($key);
        for ($i = 0; $i < 60; $i++) {
            RateLimiter::hit($key, 60);
        }
        $this->post(route('owner.requests.review', ['id' => $id]))->assertStatus(429)->assertHeader('Retry-After');
        $this->get('/admin/login')->assertNotFound();
        $this->post('/api/v1/checkout/intents/'.$id.'/review', ['target' => 'reviewed'])->assertNotFound();
        $this->delete(route('owner.requests.show', ['id' => $id]))->assertStatus(405);
        $this->assertDatabaseCount('preliminary_request_review_events', 0);
    }
}
