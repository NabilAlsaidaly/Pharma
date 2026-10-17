<?php

namespace Tests\Feature\Education;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\EducationTestCase;

class ArticleSecurityTest extends EducationTestCase
{
    private function destinations(): array
    {
        $id = (string) Str::uuid7();

        return [['GET', 'index', []], ['GET', 'create', []], ['GET', 'edit', ['id' => $id]], ['POST', 'mutate', []], ['GET', 'media', ['id' => $id]], ['GET', 'categories', []], ['GET', 'category.create', []], ['GET', 'category.edit', ['id' => $id]], ['POST', 'category.mutate', []], ['GET', 'history', []]];
    }

    public function test_all_new_owner_routes_deny_guests_and_customers_without_writes(): void
    {
        foreach ([false, true] as $customer) {
            if ($customer) {
                $this->actingAs($this->customer(), 'customer');
            }
            foreach ($this->destinations() as [$method, $name, $parameters]) {
                $this->call($method, route('owner.education.'.$name, $parameters))->assertRedirect(route('owner.login'));
            }
        }
        $this->assertCount(10, collect(Route::getRoutes()->getRoutes())->filter(fn ($r) => str_starts_with((string) $r->getName(), 'owner.education.')));
        $this->assertDatabaseCount('educational_content_events', 0);
    }

    public static function revoked(): array
    {
        return [['disabled'], ['restricted'], ['credential'], ['idle']];
    }

    #[DataProvider('revoked')]
    public function test_every_route_revalidates_session_and_current_owner(string $state): void
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
            $this->call($method, route('owner.education.'.$name, $parameters))->assertForbidden();
        }
        $this->assertDatabaseCount('educational_content_events', 0);
    }

    public function test_native_csrf_body_limits_attempts_and_unknown_authority_fields(): void
    {
        $this->ownerLogin();
        $this->app->instance(PreventRequestForgery::class, new class($this->app, $this->app['encrypter']) extends PreventRequestForgery
        {
            protected function runningUnitTests(): bool
            {
                return false;
            }
        });
        foreach (['mutate', 'category.mutate'] as $name) {
            $this->post(route('owner.education.'.$name))->assertStatus(419);
        }
        $token = Str::random(40);
        $this->withSession(['_token' => $token]);
        $url = route('owner.education.mutate');
        $this->post($url, $this->command(extra: ['_token' => $token, 'slug' => 'unsafe-authority', 'values' => ['title' => 'عنوان'], 'owner_id' => $this->owner->id]))->assertUnprocessable()->assertHeader('Referrer-Policy', 'no-referrer');
        $this->assertFalse(session()->has('_old_input'));
        $this->postJson($url, ['_token' => $token, 'padding' => str_repeat('x', 262145)])->assertStatus(413);
        for ($i = 0; $i < 60; $i++) {
            RateLimiter::hit('education:owner:'.$this->owner->id, 60);
        }
        $this->post($url, ['_token' => $token])->assertStatus(429)->assertHeader('Retry-After');
        $this->assertDatabaseCount('educational_articles', 0);
    }

    public function test_owner_form_adapter_normalizes_only_known_fields_and_renders_safe_blocks(): void
    {
        $this->ownerLogin();
        $cmd = $this->command(extra: ['slug' => 'web-article', 'values' => ['title' => 'عنوان من النموذج', 'editorial_scope' => 'general', 'sort_order' => '0'], 'body_document' => json_encode($this->document('نص محفوظ')), '_relations_present' => '1']);
        $this->post(route('owner.education.mutate'), $cmd)->assertRedirect();
        $id = DB::table('educational_articles')->where('slug', 'web-article')->value('id');
        $this->get(route('owner.education.edit', ['id' => $id]))->assertOk()->assertSee('نص محفوظ')->assertSee('إضافة قائمة نقطية')->assertSee('معاينة المسودة المحفوظة');
        $cmd = $this->command('1', extra: ['body_document' => '{"schema_version":1,"blocks":[{"type":"script"}]}']);
        $this->post(route('owner.education.mutate', ['id' => $id]), $cmd)->assertUnprocessable()->assertDontSee('<script>alert(1)</script>', false);
        $this->assertSame('1', $this->version($id));
    }

    public function test_constraints_reject_cross_article_media_dangling_publication_and_invalid_json(): void
    {
        $id = $this->article(publish: false);
        $media = $this->articleImage($id, 'cover');
        $r = $this->current($id);
        $this->assertConstraint(fn () => DB::table('educational_articles')->where('id', $id)->update(['published_version' => 999]), 1452);
        $this->assertConstraint(fn () => DB::table('educational_articles')->where('id', $id)->delete(), 1451);
        $this->assertConstraint(fn () => DB::table('educational_article_revisions')->where('id', $r['id'])->update(['version' => 0]), 3819);
        foreach (['{}', '{"schema_version":null,"blocks":[]}', '{"schema_version":2,"blocks":[]}', '{"schema_version":1,"blocks":{}}'] as $bad) {
            $this->assertConstraint(fn () => DB::table('educational_article_revisions')->where('id', $r['id'])->update(['body_document' => $bad]), 3819);
        }
        $this->assertConstraint(fn () => DB::table('educational_revision_media')->where('revision_id', $r['id'])->update(['role' => 'inline', 'slot' => 6]), 3819);
        $other = $this->article(publish: false);
        $otherRevision = $this->current($other);
        $this->assertConstraint(fn () => DB::table('educational_revision_media')->insert(['id' => (string) Str::uuid7(), 'revision_id' => $otherRevision['id'], 'article_id' => $other, 'media_id' => $media, 'role' => 'cover', 'slot' => 0, 'alt_text' => 'انتحال']), 1452);
        $duplicate = (array) DB::table('educational_revision_media')->where('revision_id', $r['id'])->first();
        $duplicate['id'] = (string) Str::uuid7();
        $this->assertConstraint(fn () => DB::table('educational_revision_media')->insert($duplicate), 1062);
    }

    public function test_unexpected_exceptions_redact_content_and_schema_failure_is_honest_503(): void
    {
        $id = $this->article();
        $slug = DB::table('educational_articles')->where('id', $id)->value('slug');
        $armed = true;
        DB::listen(function ($query) use (&$armed): void {
            if ($armed && str_starts_with($query->sql, 'select') && str_contains($query->sql, 'educational_articles')) {
                $armed = false;
                DB::table('educational_media')->insert(['digest' => 'private-editorial-sentinel']);
            }
        });
        Log::spy();
        $this->getJson('/api/v1/education/articles/'.$slug)->assertStatus(500)->assertDontSee('private-editorial-sentinel')->assertDontSee('SQLSTATE')->assertHeader('Cache-Control', 'no-store, private');
        Log::shouldHaveReceived('error')->withArgs(fn ($message, $context) => $message === 'education_http_failed' && array_keys($context) === ['exception_type', 'request_id'])->once();
        Schema::shouldReceive('getTableListing')->andReturn([]);
        $this->getJson('/api/v1/education/articles')->assertStatus(503)->assertJsonPath('success', false);
    }

    public function test_read_routes_do_not_create_audit_or_publish_and_unknown_media_is_private(): void
    {
        $this->getJson('/api/v1/education/articles')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/education/categories')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/education/articles/missing')->assertNotFound();
        $this->get('/api/v1/education/media/'.Str::uuid7().'.png')->assertNotFound();
        $this->postJson('/api/v1/education/articles', ['title' => 'forged'])->assertStatus(405);
        $this->json('GET', '/api/v1/education/articles', ['owner_id' => 'forged'])->assertUnprocessable();
        $this->ownerLogin();
        foreach (['index', 'create', 'categories', 'category.create', 'history'] as $name) {
            $this->get(route('owner.education.'.$name))->assertOk();
        }
        $this->assertDatabaseCount('educational_content_events', 0);
    }
}
