<?php

namespace Tests;

use App\Domains\Content\Application\Education\ManageEducationalArticle;
use App\Domains\Content\Application\Education\ManageEducationalCategory;
use App\Domains\Content\Infrastructure\Education\EducationalStore;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

abstract class EducationTestCase extends OwnerDashboardTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    protected function runText(string $text = 'نص تحريري اختباري'): array
    {
        return ['text' => $text, 'bold' => false, 'italic' => false, 'href' => null];
    }

    protected function document(string $text = 'نص تحريري اختباري'): array
    {
        return ['schema_version' => 1, 'blocks' => [['type' => 'paragraph', 'runs' => [$this->runText($text)]]]];
    }

    protected function command(string $version = '0', string $action = 'save', array $extra = []): array
    {
        return array_replace(['operation_id' => (string) Str::uuid7(), 'expected_version' => $version, 'action' => $action] + (in_array($action, ['publish', 'unpublish', 'archive', 'restore'], true) ? ['confirm' => '1'] : []) + ($action === 'publish' ? ['review_acknowledged' => '1'] : []), $extra);
    }

    protected function article(array $extra = [], bool $publish = true): string
    {
        $id = app(ManageEducationalArticle::class)->execute(null, $this->owner, $this->command(extra: array_replace(['slug' => 'article-'.strtolower(Str::random(8)), 'values' => ['title' => 'عنوان تحريري اختباري', 'editorial_scope' => 'general'], 'body' => $this->document()], $extra)));
        if ($publish) {
            $this->change($id, 'publish');
        }

        return $id;
    }

    protected function change(string $id, string $action, array $extra = [], ?UploadedFile $file = null): string
    {
        return app(ManageEducationalArticle::class)->execute($id, $this->owner, $this->command($this->version($id), $action, $extra), $file);
    }

    protected function version(string $id): string
    {
        return (string) DB::table('educational_articles')->where('id', $id)->value('lock_version');
    }

    protected function articleImage(string $id, string $role = 'inline'): string
    {
        $this->change($id, 'media', ['role' => $role, 'alt_text' => 'وصف اختباري للصورة'], UploadedFile::fake()->image('article.png', 20, 30));

        return DB::table('educational_media')->where('article_id', $id)->orderByDesc('created_at')->orderByDesc('id')->value('id');
    }

    protected function category(bool $active = true): string
    {
        return app(ManageEducationalCategory::class)->execute(null, $this->owner, $this->command(extra: ['slug' => 'category-'.strtolower(Str::random(8)), 'values' => ['name' => 'فئة اختبارية', 'is_active' => $active]]));
    }

    protected function refused(callable $call, int $status = 422): void
    {
        try {
            $call();
            $this->fail('Expected a refused educational command.');
        } catch (HttpExceptionInterface $e) {
            $this->assertSame($status, $e->getStatusCode());
        }
    }

    protected function current(string $id): array
    {
        return EducationalStore::revision($id, $this->version($id));
    }
}
