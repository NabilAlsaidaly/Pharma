<?php

namespace Tests\Feature\Education;

use App\Domains\Content\Application\Education\ManageEducationalArticle;
use App\Domains\Content\Application\Education\ManageEducationalCategory;
use App\Domains\Content\Domain\Education\ArticleDocument;
use App\Domains\Content\Infrastructure\Education\PublishedEducation;
use Illuminate\Support\Facades\DB;
use Tests\EducationTestCase;

class ArticleWorkflowTest extends EducationTestCase
{
    public function test_incomplete_draft_stays_private_and_published_revision_survives_edits(): void
    {
        $id = $this->article(['body' => ArticleDocument::empty(), 'values' => ['title' => 'قديم']], false);
        $slug = DB::table('educational_articles')->where('id', $id)->value('slug');
        $this->getJson('/api/v1/education/articles/'.$slug)->assertNotFound()->assertHeader('Cache-Control', 'no-store, private');
        $this->refused(fn () => $this->change($id, 'publish'));
        $this->change($id, 'save', ['values' => ['editorial_scope' => 'general'], 'body' => $this->document()]);
        $this->change($id, 'publish');
        $this->change($id, 'save', ['values' => ['title' => 'جديد'], 'body' => $this->document('مسودة مختلفة')]);
        $this->getJson('/api/v1/education/articles/'.$slug)->assertOk()->assertJsonPath('data.title', 'قديم')->assertJsonPath('data.revision', '3');
        $this->change($id, 'publish');
        $this->getJson('/api/v1/education/articles/'.$slug)->assertOk()->assertJsonPath('data.title', 'جديد')->assertJsonPath('data.body_format', 'structured_blocks')->assertJsonPath('data.body.blocks.0.runs.0.text', 'مسودة مختلفة');
        $this->assertSame(5, DB::table('educational_article_revisions')->where('article_id', $id)->count());
    }

    public function test_health_publication_needs_explicit_review_source_and_extra_acknowledgment(): void
    {
        $id = $this->article(['values' => ['title' => 'محتوى اختباري', 'editorial_scope' => 'health_related']], false);
        $this->refused(fn () => $this->change($id, 'publish', ['health_acknowledged' => '1']));
        $this->change($id, 'save', ['sources' => [['title' => 'مصدر يكتبه الاختبار', 'url' => 'https://example.org/source', 'sort_order' => 0]]]);
        $this->refused(fn () => $this->change($id, 'publish'));
        $cmd = $this->command($this->version($id), 'publish', ['health_acknowledged' => '1']);
        unset($cmd['review_acknowledged']);
        $this->refused(fn () => app(ManageEducationalArticle::class)->execute($id, $this->owner, $cmd));
        $this->change($id, 'publish', ['health_acknowledged' => '1']);
        $this->assertTrue((bool) $this->current($id)['review_acknowledged']);
        $this->assertTrue((bool) $this->current($id)['health_acknowledged']);
        $json = json_encode(app(PublishedEducation::class)->item(DB::table('educational_articles')->where('id', $id)->value('slug')));
        foreach (['medically_approved', 'doctor_verified', 'owner_id', 'payload_fingerprint'] as $secret) {
            $this->assertStringNotContainsString($secret, $json);
        }
    }

    public function test_unpublish_archive_restore_and_permanent_slug_preserve_history(): void
    {
        $id = $this->article();
        $slug = DB::table('educational_articles')->where('id', $id)->value('slug');
        $this->refused(fn () => $this->change($id, 'save', ['slug' => 'changed-slug']), 409);
        $this->change($id, 'unpublish');
        $this->getJson('/api/v1/education/articles/'.$slug)->assertNotFound();
        $this->change($id, 'publish');
        $this->change($id, 'archive');
        $this->getJson('/api/v1/education/articles/'.$slug)->assertNotFound();
        $this->refused(fn () => $this->change($id, 'publish'), 409);
        $this->change($id, 'restore');
        $this->assertNull(DB::table('educational_articles')->where('id', $id)->value('published_version'));
        $this->getJson('/api/v1/education/articles/'.$slug)->assertNotFound();
        $this->assertSame($slug, DB::table('educational_articles')->where('id', $id)->value('slug'));
        $this->assertSame(6, DB::table('educational_content_events')->where('target_id', $id)->count());
    }

    public function test_owner_operation_replay_precedes_stale_version_but_conflicting_payload_is_409(): void
    {
        $command = $this->command(extra: ['slug' => 'permanent-example', 'values' => ['title' => 'عنوان محفوظ']]);
        $service = app(ManageEducationalArticle::class);
        $id = $service->execute(null, $this->owner, $command);
        $this->assertSame($id, $service->execute(null, $this->owner, $command));
        $this->change($id, 'save', ['values' => ['title' => 'عنوان معدل']]);
        $this->assertSame($id, $service->execute(null, $this->owner, $command));
        $command['values']['title'] = 'حمولة أخرى';
        $this->refused(fn () => $service->execute(null, $this->owner, $command), 409);
        $this->refused(fn () => $service->execute($id, $this->owner, $this->command('1', extra: ['values' => ['title' => 'تبويب قديم']])), 409);
        $this->assertSame('2', $this->version($id));
        $this->assertSame(2, DB::table('educational_content_events')->count());
    }

    public function test_category_visibility_suppresses_only_category_and_renames_need_article_republication(): void
    {
        $category = $this->category();
        $categorySlug = DB::table('educational_categories')->where('id', $category)->value('slug');
        $id = $this->article(['values' => ['title' => 'مقال بفئة', 'editorial_scope' => 'general', 'category_id' => $category]]);
        $slug = DB::table('educational_articles')->where('id', $id)->value('slug');
        $manager = app(ManageEducationalCategory::class);
        $manager->execute($category, $this->owner, $this->command('1', extra: ['values' => ['name' => 'فئة معدلة']]));
        $this->getJson('/api/v1/education/articles/'.$slug)->assertJsonPath('data.category.name', 'فئة اختبارية');
        $this->change($id, 'publish');
        $this->getJson('/api/v1/education/articles/'.$slug)->assertJsonPath('data.category.name', 'فئة معدلة');
        $manager->execute($category, $this->owner, $this->command('2', 'archive'));
        $this->getJson('/api/v1/education/articles/'.$slug)->assertOk()->assertJsonPath('data.category', null);
        $this->getJson('/api/v1/education/categories/'.$categorySlug.'/articles')->assertNotFound();
        $manager->execute($category, $this->owner, $this->command('3', 'restore'));
        $this->assertFalse((bool) DB::table('educational_categories')->where('id', $category)->value('is_active'));
        $this->change($id, 'publish'); // retained hidden category is permitted, without public leakage
        $this->getJson('/api/v1/education/articles/'.$slug)->assertOk()->assertJsonPath('data.category', null);
    }

    public function test_revision_relationship_membership_and_current_catalog_visibility_are_independent(): void
    {
        $p = $this->product(['availability_status' => 'unavailable']);
        $c = $this->concern();
        $id = $this->article(['products' => [['id' => $p->id, 'sort_order' => 3]], 'concerns' => [['id' => $c->id, 'sort_order' => 2]]]);
        $slug = DB::table('educational_articles')->where('id', $id)->value('slug');
        $this->getJson('/api/v1/education/articles/'.$slug)->assertOk()->assertJsonPath('data.related_products.0.availability_status', 'unavailable')->assertJsonPath('data.related_concerns.0.id', $c->id);
        $this->change($id, 'save', ['products' => [], 'concerns' => []]);
        $this->getJson('/api/v1/education/articles/'.$slug)->assertJsonCount(1, 'data.related_products');
        $p->update(['status' => 'hidden']);
        $c->delete();
        $this->getJson('/api/v1/education/articles/'.$slug)->assertOk()->assertJsonCount(0, 'data.related_products')->assertJsonCount(0, 'data.related_concerns')->assertDontSee($p->name);
        $this->getJson('/api/v1/education/articles?concern='.$c->slug)->assertNotFound();
        $p->update(['status' => 'active']);
        $c->restore();
        $this->getJson('/api/v1/education/articles/'.$slug)->assertJsonCount(1, 'data.related_products');
        $this->change($id, 'publish');
        $this->getJson('/api/v1/education/articles/'.$slug)->assertJsonCount(0, 'data.related_products');
        $this->assertDatabaseCount('cart_items', 0);
        $this->assertDatabaseCount('checkout_intents', 0);
        $this->assertDatabaseCount('gift_requests', 0);
    }

    public function test_search_featured_and_pagination_are_published_literal_and_summary_only(): void
    {
        $this->article(['values' => ['title' => 'عنوان %_ حرفي', 'editorial_scope' => 'general', 'is_featured' => true], 'body' => $this->document('عبارة في المتن فقط')]);
        $this->article(['values' => ['title' => 'مسودة %_', 'editorial_scope' => 'general']], false);
        $this->getJson('/api/v1/education/articles?q='.urlencode('%_').'&featured=1&per_page=1')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 1)->assertJsonMissingPath('data.0.body')->assertJsonMissingPath('data.0.owner_id');
        $this->getJson('/api/v1/education/articles?q='.urlencode('عبارة في المتن'))->assertOk()->assertJsonCount(0, 'data');
        foreach (['per_page=49', 'page=1001', 'q=x', 'sort=random', 'featured=7', 'q[]=test'] as $bad) {
            $this->getJson('/api/v1/education/articles?'.$bad)->assertUnprocessable();
        }
    }

    public function test_hidden_new_links_and_duplicate_associations_are_rejected_without_partial_writes(): void
    {
        $p = $this->product(['status' => 'hidden']);
        $id = $this->article(publish: false);
        $this->refused(fn () => $this->change($id, 'save', ['products' => [['id' => $p->id, 'sort_order' => 0]]]));
        $c = $this->concern();
        $links = [['id' => $c->id, 'sort_order' => 0], ['id' => $c->id, 'sort_order' => 1]];
        $this->refused(fn () => $this->change($id, 'save', ['concerns' => $links]));
        $this->assertSame('1', $this->version($id));
        $this->assertDatabaseCount('educational_content_events', 1);
    }

    public function test_version_maximum_is_readable_but_not_advanceable_and_history_is_private(): void
    {
        $id = $this->article(publish: false);
        DB::table('educational_article_revisions')->where('article_id', $id)->update(['version' => '18446744073709551615']);
        DB::table('educational_articles')->where('id', $id)->update(['lock_version' => '18446744073709551615']);
        $this->refused(fn () => $this->change($id, 'save', ['values' => ['title' => 'تجاوز']]));
        $this->ownerLogin();
        $this->get(route('owner.education.edit', ['id' => $id, 'revision' => '18446744073709551615']))->assertOk()->assertSee('18446744073709551615');
    }
}
