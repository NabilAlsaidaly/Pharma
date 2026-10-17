<?php

namespace Tests\Feature\Education;

use App\Domains\Content\Infrastructure\Education\PublishedEducation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\EducationTestCase;

class ArticlePerformanceTest extends EducationTestCase
{
    private function measured(callable $operation): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        try {
            $response = $operation();
            $this->assertLessThan(400, $response->getStatusCode());

            return count(DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
        }
    }

    public function test_http_counts_and_real_plans_with_growing_content_and_history(): void
    {
        $this->withoutExceptionHandling();
        $this->ownerLogin();
        $empty = $this->measured(fn () => $this->getJson('/api/v1/education/articles'));
        $category = $this->category();
        $category2 = $this->category();
        $p = $this->product();
        $hidden = $this->product(['status' => 'active']);
        $c = $this->concern();
        $id = $this->article(['values' => ['title' => 'عنوان أداء', 'editorial_scope' => 'general', 'category_id' => $category, 'is_featured' => true], 'products' => [['id' => $p->id, 'sort_order' => 0], ['id' => $hidden->id, 'sort_order' => 1]], 'concerns' => [['id' => $c->id, 'sort_order' => 0]], 'sources' => [['title' => 'مرجع أداء اصطناعي', 'url' => 'https://example.org/performance', 'sort_order' => 0]]], false);
        $media = $this->articleImage($id, 'cover');
        $this->change($id, 'publish');
        $hidden->update(['status' => 'hidden']);
        $slug = DB::table('educational_articles')->where('id', $id)->value('slug');
        $categorySlug = DB::table('educational_categories')->where('id', $category)->value('slug');
        $template = (array) DB::table('educational_article_revisions')->where('article_id', $id)->where('version', '3')->first();
        $created = 1;
        $counts = [];
        foreach ([10, 50, 200] as $volume) {
            while ($created < $volume) {
                $article = (string) Str::uuid7();
                $revision = (string) Str::uuid7();
                DB::table('educational_articles')->insert(['id' => $article, 'slug' => 'performance-'.$created, 'lock_version' => 1, 'created_at' => now(), 'updated_at' => now()]);
                DB::table('educational_article_revisions')->insert(array_replace($template, ['id' => $revision, 'article_id' => $article, 'version' => 1, 'category_id' => $created % 2 ? $category : $category2]));
                DB::table('educational_articles')->where('id', $article)->update(['published_version' => 1]);
                DB::table('educational_revision_products')->insert(['id' => (string) Str::uuid7(), 'revision_id' => $revision, 'product_id' => $p->id, 'sort_order' => 0]);
                DB::table('educational_revision_concerns')->insert(['id' => (string) Str::uuid7(), 'revision_id' => $revision, 'concern_id' => $c->id, 'sort_order' => 0]);
                $created++;
            }
            for ($n = 0; $n < 12; $n++) {
                $this->change($id, 'save', ['values' => ['excerpt' => 'مسودة أداء '.$volume.'-'.$n]]);
            }
            $m = [];
            $m['list'] = $this->measured(fn () => $this->getJson('/api/v1/education/articles'));
            $m['category'] = $this->measured(fn () => $this->getJson('/api/v1/education/categories/'.$categorySlug.'/articles'));
            $m['detail'] = $this->measured(fn () => $this->getJson('/api/v1/education/articles/'.$slug));
            $m['featured'] = $this->measured(fn () => $this->getJson('/api/v1/education/articles?featured=1'));
            $m['search'] = $this->measured(fn () => $this->getJson('/api/v1/education/articles?q='.urlencode('أداء')));
            $m['concern'] = $this->measured(fn () => $this->getJson('/api/v1/education/articles?concern='.$c->slug));
            $m['categories'] = $this->measured(fn () => $this->getJson('/api/v1/education/categories'));
            $m['owner_list'] = $this->measured(fn () => $this->get(route('owner.education.index')));
            $m['owner_edit'] = $this->measured(fn () => $this->get(route('owner.education.edit', ['id' => $id])));
            $m['history'] = $this->measured(fn () => $this->get(route('owner.education.history')));
            $m['media'] = $this->measured(fn () => $this->get('/api/v1/education/media/'.$media.'.png'));
            $version = $this->version($id);
            $m['publish'] = $this->measured(fn () => $this->post(route('owner.education.mutate', ['id' => $id]), $this->command($version, 'publish')));
            $version = $this->version($id);
            $m['unpublish'] = $this->measured(fn () => $this->post(route('owner.education.mutate', ['id' => $id]), $this->command($version, 'unpublish')));
            $this->change($id, 'publish');
            $counts[$volume] = $m;
        }
        $this->assertSame($counts[10], $counts[50]);
        $this->assertSame($counts[10], $counts[200]);
        $reader = app(PublishedEducation::class);
        $plans = [];
        foreach (['page' => $reader->query()->select(['a.id', 'r.title'])->orderBy('r.sort_order')->orderByDesc('r.created_at')->orderBy('a.id')->limit(12), 'count' => $reader->query()->selectRaw('COUNT(*)'), 'search_page' => $reader->query(['q' => 'أداء'])->select('a.id')->limit(12), 'concern_page' => $reader->query(['concern' => $c->slug])->select('a.id')->limit(12)] as $key => $query) {
            $plans[$key] = DB::select('EXPLAIN '.$query->toSql(), $query->getBindings());
        }
        $plans['history'] = DB::select('EXPLAIN SELECT version FROM educational_article_revisions WHERE article_id = ? ORDER BY version DESC LIMIT 25', [$id]);
        $plans['media'] = DB::select('EXPLAIN SELECT revision_id FROM educational_revision_media WHERE media_id = ?', [$media]);
        $plans['category'] = DB::select('EXPLAIN SELECT id FROM educational_categories WHERE archived_at IS NULL AND is_active = 1 ORDER BY sort_order,id LIMIT 12');
        foreach (['search_count' => ['q' => 'أداء'], 'concern_count' => ['concern' => $c->slug], 'category_count' => ['category' => $categorySlug]] as $key => $filters) {
            $query = $reader->query($filters)->selectRaw('COUNT(*)');
            $plans[$key] = DB::select('EXPLAIN '.$query->toSql(), $query->getBindings());
        }
        foreach ($plans as $plan) {
            $this->assertNotEmpty($plan);
        }
        foreach (['page', 'count', 'search_page', 'concern_page', 'search_count', 'concern_count', 'category_count'] as $key) {
            $this->assertDoesNotMatchRegularExpression('/Table scan on r|Index scan on r /i', json_encode($plans[$key], JSON_THROW_ON_ERROR));
            foreach ($plans[$key] as $row) {
                $this->assertFalse(($row->table ?? null) === 'r' && in_array($row->type ?? '', ['ALL', 'index'], true));
            }
        }
        $this->assertSame(200, DB::table('educational_articles')->count());
        $this->assertSame(0, DB::table('gift_requests')->count());
        fwrite(STDOUT, 'EDUCATION_HTTP_COUNTS '.json_encode(['empty' => $empty, 'volumes' => $counts, 'plans' => $plans], JSON_THROW_ON_ERROR).PHP_EOL);
    }
}
