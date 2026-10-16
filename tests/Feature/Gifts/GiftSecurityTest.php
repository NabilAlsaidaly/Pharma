<?php

namespace Tests\Feature\Gifts;

use App\Domains\Gifts\Infrastructure\GiftStore;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\GiftTestCase;

class GiftSecurityTest extends GiftTestCase
{
    private function destinations(): array
    {
        $id = (string) Str::uuid7();

        return [['GET', 'index', []], ['GET', 'create', []], ['GET', 'edit', ['id' => $id]], ['POST', 'mutate', []], ['GET', 'media', ['id' => $id]], ['GET', 'occasions', []], ['GET', 'occasion.create', []], ['GET', 'occasion.edit', ['id' => $id]], ['POST', 'occasion.mutate', []], ['GET', 'requests', []], ['GET', 'requests.show', ['id' => $id]], ['POST', 'review', ['id' => $id]], ['GET', 'history', []]];
    }

    public function test_all_gift_owner_routes_deny_guest_and_customer(): void
    {
        foreach ([false, true] as $customer) {
            if ($customer) {
                $this->actingAs($this->customer(), 'customer');
            }
            foreach ($this->destinations() as [$method, $name, $parameters]) {
                $this->call($method, route('owner.gifts.'.$name, $parameters))->assertRedirect(route('owner.login'));
            }
        }
        $this->assertCount(13, collect(Route::getRoutes()->getRoutes())->filter(fn ($r) => str_starts_with((string) $r->getName(), 'owner.gifts.')));
        $this->assertDatabaseCount('gift_change_events', 0);
    }

    public static function revoked(): array
    {
        return [['disabled'], ['restricted'], ['credential'], ['idle']];
    }

    #[DataProvider('revoked')]
    public function test_all_new_routes_revalidate_owner_session(string $state): void
    {
        foreach ($this->destinations() as [$method, $name, $parameters]) {
            DB::table('management_users')->where('id', $this->owner->id)->update(['status' => 'active', 'role' => 'owner']);
            $this->owner = $this->owner->fresh();
            $this->ownerLogin();
            match ($state) {
                'disabled' => DB::table('management_users')->where('id', $this->owner->id)->update(['status' => 'disabled']),
                'restricted' => DB::table('management_users')->where('id', $this->owner->id)->update(['role' => 'restricted']),
                'credential' => $this->withSession(['owner_credential_version' => '99']),
                'idle' => $this->withSession(['owner_last_activity' => now()->subHours(4)->timestamp]),
            };
            $this->call($method, route('owner.gifts.'.$name, $parameters))->assertForbidden();
        }
        $this->assertDatabaseCount('gift_change_events', 0);
    }

    public function test_native_csrf_owner_limits_unknown_fields_and_no_sensitive_flash(): void
    {
        $this->ownerLogin();
        $this->app->instance(PreventRequestForgery::class, new class($this->app, $this->app['encrypter']) extends PreventRequestForgery
        {
            protected function runningUnitTests(): bool
            {
                return false;
            }
        });
        foreach (['mutate', 'occasion.mutate', 'review'] as $name) {
            $this->post(route('owner.gifts.'.$name, $name === 'review' ? ['id' => (string) Str::uuid7()] : []))->assertStatus(419);
        }
        $token = Str::random(40);
        $this->withSession(['_token' => $token]);
        $url = route('owner.gifts.mutate');
        $this->post($url, $this->giftCommand(extra: ['values' => ['name' => 'اسم'], 'slug' => 'csrf-gift', '_token' => $token, 'owner_id' => 'forged']))->assertStatus(422)->assertHeader('Referrer-Policy', 'no-referrer');
        $this->assertFalse(session()->has('_old_input'));
        $this->postJson($url, ['_token' => $token, 'padding' => str_repeat('x', 262145)])->assertStatus(413);
        $key = 'gift:owner:'.$this->owner->id;
        for ($i = 0; $i < 60; $i++) {
            RateLimiter::hit($key, 60);
        }
        $this->post($url, ['_token' => $token])->assertStatus(429)->assertHeader('Retry-After');
    }

    public function test_customer_body_quota_queries_and_unsafe_errors_are_bounded(): void
    {
        $customer = $this->customer();
        $this->actingAs($customer, 'customer');
        $this->postJson('/api/v1/gifts/requests', ['padding' => str_repeat('x', 16385)])->assertStatus(413)->assertDontSee('SQLSTATE');
        $this->postJson('/api/v1/gifts/requests?customer_id=forged', [])->assertStatus(422);
        $this->getJson('/api/v1/gifts/packages?unknown=x')->assertStatus(422);
        $this->getJson('/api/v1/gifts/packages?per_page=49')->assertStatus(422);
        $this->getJson('/api/v1/gifts/requests?page=1001')->assertStatus(422);
        $this->json('GET', '/api/v1/gifts/packages', ['owner_id' => 'forged'])->assertStatus(422);
        for ($i = 0; $i < 10; $i++) {
            RateLimiter::hit('gift:customer:'.$customer->id, 3600);
        }
        $this->postJson('/api/v1/gifts/requests', [])->assertStatus(429)->assertHeader('Retry-After');
    }

    public function test_customer_stateful_submission_requires_real_csrf_token(): void
    {
        $id = $this->gift();
        $this->actingAs($this->customer(), 'customer')->withHeader('Origin', 'https://frontend.test');
        $this->app->instance(PreventRequestForgery::class, new class($this->app, $this->app['encrypter']) extends PreventRequestForgery
        {
            protected function runningUnitTests(): bool
            {
                return false;
            }
        });
        $payload = $this->giftInput($id);
        $this->postJson('/api/v1/gifts/requests', $payload)->assertStatus(419);
        $token = Str::random(40);
        $this->withSession(['_token' => $token])->withHeader('X-CSRF-TOKEN', $token);
        $this->postJson('/api/v1/gifts/requests', $payload)->assertCreated();
        $this->assertDatabaseCount('gift_requests', 1);
    }

    public function test_missing_gift_schema_returns_safe_unavailable(): void
    {
        Schema::shouldReceive('getTableListing')->with(schemaQualified: false)->andReturn([]);
        $this->getJson('/api/v1/gifts/packages')->assertStatus(503)->assertJsonPath('error.code', 'SERVICE_UNAVAILABLE')->assertDontSee('gift_packages');
    }

    public static function unsafeImages(): array
    {
        return [['svg'], ['extension'], ['fake'], ['large'], ['dimension'], ['pixels'], ['polyglot']];
    }

    #[DataProvider('unsafeImages')]
    public function test_unsafe_images_are_rejected_without_revision_or_file(string $kind): void
    {
        $id = $this->gift(publish: false);
        $sourceImage = UploadedFile::fake()->image('source.png');
        $file = match ($kind) {
            'svg' => UploadedFile::fake()->createWithContent('bad.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>'),
            'extension' => UploadedFile::fake()->image('bad.php.png'),
            'fake' => UploadedFile::fake()->createWithContent('fake.jpg', 'not an image'),
            'large' => UploadedFile::fake()->image('large.png')->size(5121),
            'dimension' => UploadedFile::fake()->image('wide.png', 4097, 1),
            'pixels' => UploadedFile::fake()->image('pixels.png', 2200, 2200),
            'polyglot' => UploadedFile::fake()->createWithContent('poly.png', file_get_contents($sourceImage->getPathname()).'<?php echo 1;'),
        };
        $this->ownerLogin();
        $this->post(route('owner.gifts.mutate', ['id' => $id]), $this->giftCommand('1', 'media', ['alt_text' => 'صورة']) + ['image' => $file])->assertStatus(422);
        $this->assertDatabaseCount('gift_package_revisions', 1);
        $this->assertSame([], Storage::disk('local')->allFiles('gifts'));
    }

    public function test_actual_sql_failure_compensates_new_media_and_redacts_sensitive_logging(): void
    {
        $id = $this->gift();
        $before = Storage::disk('local')->allFiles('gifts');
        $armed = true;
        DB::listen(function ($q) use (&$armed): void {
            if ($armed && str_starts_with($q->sql, 'insert into `gift_change_events`')) {
                $armed = false;
                DB::table('gift_review_events')->insert(['note' => 'secret-sentinel']);
            }
        });
        $this->ownerLogin();
        Log::spy();
        $this->post(route('owner.gifts.mutate', ['id' => $id]), $this->giftCommand('3', 'media', ['alt_text' => 'secret-sentinel']) + ['image' => UploadedFile::fake()->image('new.png')])->assertStatus(500)->assertDontSee('secret-sentinel')->assertDontSee('SQLSTATE');
        $this->assertSame($before, Storage::disk('local')->allFiles('gifts'));
        $this->assertDatabaseCount('gift_package_revisions', 3);
        Log::shouldHaveReceived('error')->withArgs(fn ($message, $context) => $message === 'owner_portal_failed' && array_keys($context) === ['exception_type', 'request_id'])->once();
    }

    public function test_request_child_insert_failure_is_atomic_and_does_not_log_recipient_body(): void
    {
        $id = $this->gift();
        $this->actingAs($this->customer(), 'customer');
        $armed = true;
        DB::listen(function ($q) use (&$armed): void {
            if ($armed && str_starts_with($q->sql, 'insert into `gift_request_components`')) {
                $armed = false;
                DB::table('gift_review_events')->insert(['note' => 'private-message']);
            }
        });
        Log::spy();
        $this->postJson('/api/v1/gifts/requests', $this->giftInput($id, ['message' => 'private-message']))->assertStatus(500)->assertDontSee('private-message')->assertDontSee('SQLSTATE');
        $this->assertDatabaseCount('gift_requests', 0);
        $this->assertDatabaseCount('gift_request_components', 0);
        Log::shouldHaveReceived('error')->withArgs(fn ($message, $context) => $message === 'gift_http_failed' && array_keys($context) === ['exception_type', 'request_id'])->once();
    }

    public function test_database_constraints_reject_dangling_publication_destructive_deletion_and_invalid_quantity(): void
    {
        $id = $this->gift();
        $r = GiftStore::revision($id, '3');
        $this->assertConstraint(fn () => DB::table('gift_packages')->where('id', $id)->update(['published_version' => 999]), 1452);
        $this->assertConstraint(fn () => DB::table('gift_packages')->where('id', $id)->delete(), 1451);
        $this->assertConstraint(fn () => DB::table('gift_revision_components')->where('revision_id', $r['id'])->update(['quantity' => 101]), 3819);
        $this->assertConstraint(fn () => DB::table('gift_revision_components')->where('revision_id', $r['id'])->update(['type' => 'catalog']), 3819);
        $this->assertConstraint(fn () => DB::table('gift_revision_images')->where('revision_id', $r['id'])->update(['is_primary' => true, 'primary_slot' => null]), 3819);
        DB::table('gift_revision_images')->where('revision_id', $r['id'])->update(['is_primary' => true, 'primary_slot' => 1]);
        $duplicate = (array) DB::table('gift_revision_images')->where('revision_id', $r['id'])->first();
        $duplicate['id'] = (string) Str::uuid7();
        $duplicate['media_id'] = (string) Str::uuid7();
        $this->assertConstraint(fn () => DB::table('gift_revision_images')->insert($duplicate), 1062);
    }
}
