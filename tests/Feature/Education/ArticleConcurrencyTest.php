<?php

namespace Tests\Feature\Education;

use App\Domains\Catalog\Infrastructure\Persistence\Models\Brand;
use App\Domains\Catalog\Infrastructure\Persistence\Models\Concern;
use App\Domains\Catalog\Infrastructure\Persistence\Models\Product;
use App\Domains\Content\Application\Education\ManageEducationalArticle;
use App\Domains\Content\Application\Education\ManageEducationalCategory;
use App\Domains\Content\Infrastructure\Education\EducationalStore;
use App\Domains\Content\Infrastructure\Education\PublishedEducation;
use App\Domains\ManagementIdentity\Application\ProvisionOwner;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ArticleConcurrencyTest extends TestCase
{
    use DatabaseMigrations { runDatabaseMigrations as private isolatedMigrations; }

    private $owner;

    private string $article;

    private string $category;

    private string $product;

    private string $concern;

    private string $mediaRoot;

    public function runDatabaseMigrations(): void
    {
        if (! app()->environment('testing') || config('database.default') !== 'mysql' || config('database.connections.mysql.database') !== 'sumaya_pharma_test' || DB::selectOne('SELECT DATABASE() AS name')->name !== 'sumaya_pharma_test') {
            throw new \RuntimeException('Educational races require guarded physical MySQL.');
        }
        $this->isolatedMigrations();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = app(ProvisionOwner::class)->execute('Editorial Race Owner', 'education-race@example.test', 'Education-fixture-passphrase-2026', 'Education-fixture-passphrase-2026');
        $this->mediaRoot = storage_path('framework/testing/education-race-media-'.Str::uuid7());
        mkdir($this->mediaRoot, 0777, true);
        config(['filesystems.disks.local.root' => $this->mediaRoot]);
        $brand = Brand::query()->create(['name' => 'علامة اختبار', 'slug' => 'education-race-brand', 'status' => 'active']);
        $this->product = Product::query()->create(['brand_id' => $brand->id, 'name' => 'منتج اختبار', 'slug' => 'education-race-product', 'status' => 'active', 'availability_status' => 'unavailable'])->id;
        $this->concern = Concern::query()->create(['name' => 'اهتمام اختبار', 'slug' => 'education-race-concern', 'status' => 'active'])->id;
        $this->category = app(ManageEducationalCategory::class)->execute(null, $this->owner, $this->command('0', 'save', ['slug' => 'education-race-category', 'values' => ['name' => 'فئة اختبار', 'is_active' => true]]));
        $this->article = app(ManageEducationalArticle::class)->execute(null, $this->owner, $this->command('0', 'save', ['slug' => 'education-race-article', 'values' => ['title' => 'عنوان كامل قديم', 'editorial_scope' => 'general', 'category_id' => $this->category], 'body' => $this->body('نص كامل قديم'), 'products' => [['id' => $this->product, 'sort_order' => 0]], 'concerns' => [['id' => $this->concern, 'sort_order' => 0]]]));
        app(ManageEducationalArticle::class)->execute($this->article, $this->owner, $this->command('1', 'publish'));
    }

    protected function tearDown(): void
    {
        if (isset($this->mediaRoot) && is_dir($this->mediaRoot)) {
            $allowed = realpath(storage_path('framework/testing'));
            $target = realpath($this->mediaRoot);
            if (! $allowed || ! $target || realpath(Storage::disk('local')->path('')) !== $target || ! str_starts_with($target, $allowed.DIRECTORY_SEPARATOR.'education-race-media-')) {
                throw new \RuntimeException('Refusing unverified educational fixture cleanup.');
            }
            Storage::disk('local')->deleteDirectory('education');
            rmdir($target);
        }
        parent::tearDown();
    }

    private function command(string $version, string $action, array $extra = []): array
    {
        return ['operation_id' => (string) Str::uuid7(), 'expected_version' => $version, 'action' => $action] + (in_array($action, ['publish', 'unpublish', 'archive'], true) ? ['confirm' => '1'] : []) + ($action === 'publish' ? ['review_acknowledged' => '1'] : []) + $extra;
    }

    private function body(string $text): array
    {
        return ['schema_version' => 1, 'blocks' => [['type' => 'paragraph', 'runs' => [['text' => $text, 'bold' => true, 'italic' => false, 'href' => null]]]]];
    }

    private function payload(array $input, string $kind = 'article', ?string $id = null): array
    {
        return ['kind' => $kind, 'id' => $id ?? $this->article, 'input' => $input];
    }

    private function overlap(array $payloads, string $table, string $id): array
    {
        $dir = storage_path('framework/testing/education-workers-'.Str::uuid7());
        mkdir($dir);
        $workers = [];
        $connections = [];
        DB::beginTransaction();
        DB::table($table)->where('id', $id)->lockForUpdate()->first();
        try {
            foreach ($payloads as $i => $payload) {
                $ready = $dir.'/ready-'.$i;
                $path = $dir.'/payload-'.$i.'.json';
                file_put_contents($path, json_encode($payload + ['owner' => $this->owner->id, 'ready' => $ready, 'media_root' => $this->mediaRoot], JSON_THROW_ON_ERROR));
                $worker = new Process([PHP_BINARY, base_path('tests/Support/education-worker.php'), $path], base_path(), null, null, 60);
                $workers[] = $worker;
                $worker->start();
                $deadline = microtime(true) + 25;
                $blocked = false;
                $connection = null;
                do {
                    if (! $worker->isRunning()) {
                        $this->fail($worker->getErrorOutput().$worker->getOutput());
                    }
                    if (file_exists($ready)) {
                        $connection = (int) file_get_contents($ready);
                        $active = collect(DB::select('SHOW FULL PROCESSLIST'))->first(fn ($row) => (int) $row->Id === $connection);
                        $sql = strtolower((string) ($active->Info ?? ''));
                        $blocked = $active !== null && str_contains($sql, '`'.$table.'`') && (str_contains($sql, 'for update') || str_contains($sql, 'lock in share mode') || str_contains($sql, 'for share'));
                    }
                    if (! $blocked) {
                        usleep(10000);
                    }
                } while (! $blocked && microtime(true) < $deadline);
                $this->assertTrue($blocked, 'Worker must overlap in real locking SQL.');
                $connections[] = $connection;
            }
            $this->assertCount(count($workers), array_unique($connections));
            DB::commit();
            $results = [];
            foreach ($workers as $worker) {
                $worker->wait();
                $this->assertTrue($worker->isSuccessful(), $worker->getErrorOutput());
                $results[] = json_decode($worker->getOutput(), true, 64, JSON_THROW_ON_ERROR);
            }
            fwrite(STDOUT, 'EDUCATION_REAL_OVERLAP '.json_encode(['barrier' => $table, 'connections' => $connections, 'kinds' => array_column($payloads, 'kind'), 'results' => $results], JSON_THROW_ON_ERROR).PHP_EOL);

            return $results;
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            foreach ($workers as $worker) {
                if ($worker->isRunning()) {
                    $worker->stop();
                }
            }
            foreach (glob($dir.'/*') ?: [] as $file) {
                unlink($file);
            }
            rmdir($dir);
        }
    }

    public static function scenarios(): array
    {
        return array_map(fn ($kind) => [$kind], ['edit', 'draft-publish', 'publish-unpublish', 'archive-publish', 'category-visibility', 'product-visibility', 'concern-visibility', 'media-publish', 'identical-retry', 'conflicting-operation', 'article-category', 'first-coordination']);
    }

    #[DataProvider('scenarios')]
    public function test_real_independent_transactions_preserve_approved_boundaries(string $kind): void
    {
        if ($kind === 'first-coordination') {
            DB::table('educational_owner_locks')->where('owner_id', $this->owner->id)->delete();
        }
        $first = $this->command('2', 'save', ['values' => ['title' => 'عنوان كامل جديد'], 'body' => $this->body('نص كامل جديد')]);
        $second = $this->command('2', 'save', ['values' => ['title' => 'عنوان بديل'], 'body' => $this->body('نص بديل')]);
        $barrier = ['management_users', $this->owner->id];
        if (in_array($kind, ['draft-publish', 'media-publish'], true)) {
            $second = $this->command('2', 'publish');
        }
        if ($kind === 'publish-unpublish') {
            $first = $this->command('2', 'publish');
            $second = $this->command('2', 'unpublish');
        }
        if ($kind === 'archive-publish') {
            $first = $this->command('2', 'archive');
            $second = $this->command('2', 'publish');
        }
        if (in_array($kind, ['identical-retry', 'conflicting-operation'], true)) {
            $second = $first;
            if ($kind === 'conflicting-operation') {
                $second['values']['title'] = 'نفس العملية بقيمة مختلفة';
            }
        }
        $payloads = [$this->payload($first), $this->payload($second)];
        if ($kind === 'media-publish') {
            $file = UploadedFile::fake()->image('race.png', 20, 20);
            $payloads[0] = $this->payload($this->command('2', 'media', ['role' => 'inline', 'alt_text' => 'صورة سباق'])) + ['file' => $file->getPathname()];
        }
        if (in_array($kind, ['category-visibility', 'article-category'], true)) {
            $payloads = [$this->payload($this->command('1', 'save', ['values' => ['is_active' => false]]), 'category', $this->category), $this->payload($kind === 'category-visibility' ? $this->command('2', 'publish') : $first)];
        }
        if (in_array($kind, ['product-visibility', 'concern-visibility'], true)) {
            $entity = $kind === 'product-visibility' ? 'product' : 'concern';
            $target = $entity === 'product' ? $this->product : $this->concern;
            $barrier = [$entity.'s', $target];
            $payloads = [$this->payload(['lock_version' => '1', 'status' => 'hidden'], $entity, $target), $this->payload($this->command('2', 'publish'))];
        }
        $result = $this->overlap($payloads, ...$barrier);
        $both = in_array($kind, ['identical-retry', 'category-visibility', 'article-category', 'product-visibility', 'concern-visibility'], true);
        $this->assertCount($both ? 2 : 1, array_filter($result, fn ($r) => $r['success'] ?? false));
        $this->assertSame('3', (string) DB::table('educational_articles')->where('id', $this->article)->value('lock_version'));
        $this->assertSame(3, DB::table('educational_article_revisions')->where('article_id', $this->article)->count());
        $a = DB::table('educational_articles')->where('id', $this->article)->first();
        if ($a->published_version !== null) {
            $public = app(PublishedEducation::class)->item('education-race-article');
            $this->assertContains($public['body']['blocks'][0]['runs'][0]['text'], ['نص كامل قديم', 'نص كامل جديد', 'نص بديل']);
            $snapshot = EducationalStore::revision($this->article, (string) $a->published_version);
            $this->assertSame($snapshot['title'], $public['title']);
            $this->assertSame($snapshot['body']['blocks'][0]['runs'][0]['text'], $public['body']['blocks'][0]['runs'][0]['text']);
            if ($kind === 'product-visibility') {
                $this->assertCount(0, $public['related_products']);
            }
            if ($kind === 'concern-visibility') {
                $this->assertCount(0, $public['related_concerns']);
            }
            if (in_array($kind, ['category-visibility', 'article-category'], true)) {
                $this->assertNull($public['category']);
            }
        } else {
            $this->assertContains($kind, ['publish-unpublish', 'archive-publish']);
        }
        if ($kind === 'identical-retry') {
            $this->assertSame($result[0]['id'], $result[1]['id']);
        }
        $this->assertDatabaseCount('gift_requests', 0);
        $this->assertDatabaseCount('checkout_intents', 0);
        $this->assertDatabaseCount('educational_owner_locks', 1);
    }
}
