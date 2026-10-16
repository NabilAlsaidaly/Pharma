<?php

namespace Tests\Feature\StoreConfiguration;

use App\Support\BusinessRaster;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\StoreConfigurationTestCase;

class StoreContentTest extends StoreConfigurationTestCase
{
    public function test_page_http_create_publish_edit_unpublish_archive_restore_history(): void
    {
        $this->ownerLogin();
        $url = route('owner.content.mutate', ['type' => 'pages']);
        $payload = $this->command(['title' => 'صفحة عربية', 'slug' => 'about-fixture', 'body' => 'نص واضح مع رموز & " محفوظة']);
        $this->post($url, $payload)->assertRedirect();
        $page = DB::table('content_pages')->sole();
        $edit = route('owner.content.mutate', ['type' => 'pages', 'id' => $page->id]);
        $public = '/api/v1/storefront/pages/about-fixture';
        $this->getJson($public)->assertNotFound();
        $this->post($edit, $this->command([], '1', 'publish'))->assertRedirect();
        $this->getJson($public)->assertOk()->assertJsonPath('data.body_format', 'text/plain')->assertJsonPath('data.revision', '2')->assertJsonPath('data.title', 'صفحة عربية')->assertDontSee('owner_id');
        $this->post($edit, $this->command(['title' => 'مسودة ثانية'], '2'))->assertRedirect();
        $this->getJson($public)->assertJsonPath('data.title', 'صفحة عربية');
        $this->post($edit, $this->command([], '3', 'unpublish'))->assertRedirect();
        $this->getJson($public)->assertNotFound();
        $this->post($edit, $this->command([], '4', 'archive'))->assertRedirect();
        $this->post($edit, $this->command([], '5', 'restore'))->assertRedirect();
        $this->getJson($public)->assertNotFound();
        $this->get(route('owner.content.edit', ['type' => 'pages', 'id' => $page->id, 'revision' => '2']))->assertOk()->assertSee('صفحة عربية')->assertSee('مسودة ثانية');
        $this->assertDatabaseCount('content_pages_revisions', 6);
        $this->post($url, $this->command(['title' => 'آخر', 'slug' => 'about-fixture', 'body' => 'نص']))->assertStatus(409);
    }

    public function test_legal_policy_draft_does_not_rewrite_published_history_or_imply_acceptance(): void
    {
        $id = $this->content('policies', ['title' => 'نص اختبار فقط', 'slug' => 'privacy', 'body' => 'نص مقدم في الاختبار وليس سياسة إنتاج']);
        $this->content('policies', [], '1', $id, 'publish');
        $this->content('policies', ['body' => 'مسودة بديلة'], '2', $id);
        $this->getJson('/api/v1/storefront/policies/privacy')->assertJsonPath('data.body', 'نص مقدم في الاختبار وليس سياسة إنتاج');
        $this->content('policies', [], '3', $id, 'publish');
        $this->getJson('/api/v1/storefront/policies/privacy')->assertJsonPath('data.body', 'مسودة بديلة');
        $this->assertDatabaseHas('content_policies_revisions', ['record_id' => $id, 'version' => 2, 'body' => 'نص مقدم في الاختبار وليس سياسة إنتاج']);
        $this->assertFalse(Schema::hasTable('customer_policy_acceptances'));
    }

    public function test_faq_publication_deterministic_order_pagination_and_private_drafts(): void
    {
        foreach (['سؤال أول' => '2', 'سؤال ثان' => '1', 'مسودة خاصة' => '0'] as $question => $sort) {
            $id = $this->content('faqs', ['question' => $question, 'answer' => 'إجابة عربية', 'sort_order' => $sort]);
            if ($sort !== '0') {
                $this->content('faqs', [], '1', $id, 'publish');
            }
        }
        $this->getJson('/api/v1/storefront/faqs?per_page=10')->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.question', 'سؤال ثان')->assertJsonPath('data.0.answer_format', 'text/plain')->assertDontSee('مسودة خاصة');
        $this->getJson('/api/v1/storefront/faqs?per_page=1000')->assertStatus(422);
        $this->getJson('/api/v1/storefront/faqs?page=1001')->assertStatus(422);
        $this->getJson('/api/v1/storefront/faqs?status=draft')->assertStatus(422);
    }

    public function test_banner_media_publish_hidden_catalog_destination_and_safe_public_projection(): void
    {
        Storage::fake('local');
        $product = $this->product();
        $id = $this->content('banners', ['title' => 'بنر اختبار', 'alt_text' => 'صورة توضيحية', 'link' => '/products/'.$product->slug, 'sort_order' => '1']);
        $this->ownerLogin();
        $url = route('owner.content.mutate', ['type' => 'banners', 'id' => $id]);
        $this->post($url, $this->command([], '1', 'publish'))->assertStatus(422);
        $this->post($url, $this->command([], '1', 'media') + ['image' => UploadedFile::fake()->image('safe.jpg', 40, 20)])->assertRedirect();
        $path = DB::table('content_banners')->sole()->image_path;
        Storage::disk('local')->assertExists($path);
        $this->assertSame('image/png', getimagesize(Storage::disk('local')->path($path))['mime']);
        $this->getJson('/api/v1/storefront/banners')->assertJsonCount(0, 'data');
        $publish = $this->command([], '2', 'publish');
        $this->post($url, $publish)->assertRedirect();
        $this->post($url, $publish)->assertRedirect();
        $this->getJson('/api/v1/storefront/banners')->assertJsonCount(1, 'data')->assertJsonPath('data.0.link', '/products/'.$product->slug)->assertJsonPath('data.0.image_url', BusinessRaster::url($path))->assertDontSee('image_path')->assertDontSee('owner_id');
        $this->content('banners', ['link' => '/products/'.$product->slug.'/'], '3', $id);
        $this->content('banners', [], '4', $id, 'publish');
        $this->getJson('/api/v1/storefront/banners')->assertJsonPath('data.0.link', '/products/'.$product->slug.'/');
        $product->delete();
        $this->getJson('/api/v1/storefront/banners')->assertJsonPath('data.0.link', null);
        $this->assertDatabaseCount('content_banners_revisions', 5);
    }

    public static function unsafeContent(): array
    {
        return [['<script>alert(1)</script>'], ['<img src=x onerror=alert(1)>'], ['&#60;script>'], [str_repeat('x', 12001)], ["text\u{0000}tail"]];
    }

    #[DataProvider('unsafeContent')]
    public function test_content_injection_and_size_are_rejected_without_revisions(string $body): void
    {
        $this->ownerLogin();
        $this->post(route('owner.content.mutate', ['type' => 'pages']), $this->command(['title' => 'نص', 'slug' => 'xss-fixture', 'body' => $body]))->assertStatus(422)->assertDontSee('<script>', false);
        $this->assertDatabaseCount('content_pages', 0);
    }

    public function test_media_validation_version_conflict_and_old_reference_retention(): void
    {
        Storage::fake('local');
        $id = $this->setting();
        $this->ownerLogin();
        $url = route('owner.settings.mutate', ['group' => 'profile', 'id' => $id]);
        foreach (['attack.php.png', 'image.svg', 'image.jpg'] as $filename) {
            $this->post($url, $this->command([], '1', 'media') + ['image' => UploadedFile::fake()->createWithContent($filename, '<script>bad</script>')])->assertStatus(422);
        }
        $this->post($url, $this->command([], '1', 'media') + ['image' => UploadedFile::fake()->image('logo.png')])->assertRedirect();
        $old = DB::table('store_profiles')->sole()->image_path;
        $this->post($url, $this->command([], '1', 'media') + ['image' => UploadedFile::fake()->image('replacement.png')])->assertStatus(409);
        $this->assertCount(1, Storage::disk('local')->allFiles('storefront'));
        $this->post($url, $this->command([], '2', 'media') + ['image' => UploadedFile::fake()->image('replacement.png')])->assertRedirect();
        Storage::disk('local')->assertExists($old);
        $this->assertCount(2, Storage::disk('local')->allFiles('storefront'));
        $this->assertDatabaseHas('store_profiles_revisions', ['record_id' => $id, 'version' => 2, 'image_path' => $old]);
    }
}
