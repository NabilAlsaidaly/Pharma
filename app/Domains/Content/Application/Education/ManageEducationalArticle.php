<?php

namespace App\Domains\Content\Application\Education;

use App\Domains\Catalog\Infrastructure\Persistence\Models\Brand;
use App\Domains\Catalog\Infrastructure\Persistence\Models\Concern;
use App\Domains\Catalog\Infrastructure\Persistence\Models\Product;
use App\Domains\Content\Domain\Education\ArticleDocument;
use App\Domains\Content\Infrastructure\Education\EducationalStore;
use App\Domains\ManagementIdentity\Infrastructure\Persistence\Models\ManagementUser;
use App\Support\BusinessRaster;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

final class ManageEducationalArticle
{
    public function execute(?string $id, ManagementUser $owner, array $command, ?UploadedFile $file = null): string
    {
        EducationalStore::ready();
        [$op, $before, $action] = ArticleInput::envelope($command);
        $id = $id === null ? null : ArticleInput::uuid($id);
        abort_if(($action === 'media') !== ($file !== null), 422);
        abort_unless(is_array($command['values'] ?? []), 422);
        $values = ArticleInput::values($command['values'] ?? []);
        $parts = [];
        foreach (['products', 'concerns', 'sources', 'images', 'body'] as $key) {
            if (array_key_exists($key, $command)) {
                $parts[$key] = match ($key) {
                    'body' => ArticleDocument::normalize($command[$key]),
                    'sources' => ArticleInput::sources($command[$key]),
                    'images' => ArticleInput::images($command[$key]),
                    default => ArticleInput::links($command[$key]),
                };
            }
        }
        $slug = $command['slug'] ?? null;
        if ($slug !== null) {
            abort_unless(is_string($slug) && strlen($slug) <= 80 && preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $slug), 422);
        }
        $review = in_array($command['review_acknowledged'] ?? null, [true, '1', 1], true);
        $health = in_array($command['health_acknowledged'] ?? null, [true, '1', 1], true);
        if ($action === 'publish') {
            abort_unless($review, 422, 'راجعي المحتوى وأكدي مراجعته قبل النشر.');
        }
        $media = null;
        $prepared = null;
        if ($file !== null) {
            abort_unless(in_array($command['role'] ?? null, ['cover', 'inline'], true), 422);
            $alt = trim(ArticleDocument::text($command['alt_text'] ?? null, 250));
            abort_if($alt === '', 422);
            $media = ['role' => $command['role'], 'alt_text' => $alt];
            $prepared = app(BusinessRaster::class)->prepare($file);
        }
        $hash = ArticleInput::fingerprint([$id, 'article', $action, $before, $slug, $values, $parts, $review, $health, $media, $prepared['digest'] ?? null]);
        $fresh = null;
        try {
            return DB::transaction(function () use ($id, $owner, $op, $before, $action, $slug, $values, $parts, $health, $media, $prepared, $hash, &$fresh): string {
                EducationalStore::actor($owner);
                if ($prior = EducationalStore::replay($owner->id, $op, $hash)) {
                    return $prior;
                }
                $article = $id ? DB::table('educational_articles')->where('id', $id)->lockForUpdate()->first() : null;
                abort_if($id && ! $article, 404);
                abort_unless((string) ($article->lock_version ?? '0') === $before, 409, 'المقال تغير في جلسة أخرى؛ حدّثي الصفحة.');
                abort_if(! $article && $action !== 'save', 409, 'احفظي هوية المقال أولاً.');
                abort_if($article && ($action === 'restore' ? ! $article->archived_at : $article->archived_at !== null), 409);
                abort_if($article && $slug !== null && $slug !== $article->slug, 409, 'الرابط دائم ولا يتغير.');
                abort_if(! $article && ! $slug, 422, 'الرابط مطلوب.');
                $old = $article ? EducationalStore::revision($id, $before) : null;
                $next = array_replace(['title' => null, 'excerpt' => null, 'seo_title' => null, 'meta_description' => null, 'category_id' => null, 'editorial_scope' => null, 'is_featured' => false, 'sort_order' => 0], $old ? array_intersect_key($old, array_flip([...array_keys(ArticleInput::FIELDS), 'category_id', 'editorial_scope', 'is_featured', 'sort_order'])) : [], $values);
                abort_if($next['title'] === null || $next['title'] === '', 422, 'عنوان المقال مطلوب.');
                $children = array_replace(['body' => ArticleDocument::empty(), 'products' => [], 'concerns' => [], 'sources' => [], 'images' => []], $old ? array_intersect_key($old, array_flip(['body', 'products', 'concerns', 'sources', 'images'])) : [], $parts);
                if (isset($parts['images'])) {
                    $priorIds = array_column($old['images'] ?? [], 'media_id');
                    abort_if(array_diff(array_column($children['images'], 'media_id'), $priorIds) !== [], 422, 'اختاري صور المسودة الحالية فقط.');
                }
                $category = $next['category_id'] ? DB::table('educational_categories')->where('id', $next['category_id'])->lockForUpdate()->first() : null;
                abort_if($next['category_id'] !== null && ! $category, 422);
                abort_if($category && $next['category_id'] !== ($old['category_id'] ?? null) && (! $category->is_active || $category->archived_at), 422, 'الفئة الجديدة غير ظاهرة.');
                $next['category_name'] = $category?->name;
                $next['category_description'] = $category?->description;
                $this->catalog($children, $old);
                if ($action === 'media') {
                    $images = array_map(fn ($r) => array_intersect_key($r, array_flip(['media_id', 'role', 'alt_text'])), $children['images']);
                    // Validate capacity before creating any bytes or media identities.
                    $fresh ??= (string) Str::uuid7();
                    $images[] = $media + ['media_id' => $fresh];
                    $children['images'] = ArticleInput::images($images);
                }
                $inlineIds = array_column(array_filter($children['images'], fn ($r) => $r['role'] === 'inline'), 'media_id');
                $blockIds = array_column(array_filter($children['body']['blocks'], fn ($b) => $b['type'] === 'image'), 'media_id');
                abort_if(array_diff($blockIds, $inlineIds) !== [], 422, 'الصورة الداخلية ليست ضمن صور هذه المسودة.');
                if ($action === 'publish') {
                    abort_unless(ArticleDocument::readable($children['body']) && in_array($next['editorial_scope'], ['general', 'health_related'], true), 422, 'النشر يحتاج نصاً وتصنيف مراجعة صريحاً.');
                    if ($next['editorial_scope'] === 'health_related') {
                        abort_unless($health && count($children['sources']) >= 1, 422, 'المحتوى الصحي يحتاج مصدراً وإقرار مراجعة الادعاءات ومصادرها.');
                    }
                    abort_if(array_diff($inlineIds, $blockIds) !== [], 422, 'ضعي الصور الداخلية في المتن أو أزيليها من المسودة.');
                    foreach ($children['images'] as $image) {
                        abort_unless(Storage::disk('local')->exists('education/'.$image['media_id'].'.png'), 422, 'ملف الصورة غير متاح.');
                    }
                    // Revalidate stored syntax too; raw DBA changes are outside command guarantees.
                    $children['body'] = ArticleDocument::normalize($children['body']);
                }
                $target = $id ?? (string) Str::uuid7();
                $version = ArticleInput::next($before);
                if (! $article) {
                    DB::table('educational_articles')->insert(['id' => $target, 'slug' => $slug, 'lock_version' => $version, 'created_at' => now(), 'updated_at' => now()]);
                }
                if ($action === 'media') {
                    $info = getimagesizefromstring($prepared['bytes']);
                    abort_unless(Storage::disk('local')->put('education/'.$fresh.'.png', $prepared['bytes']), 503, 'تعذر حفظ الصورة.');
                    DB::table('educational_media')->insert(['id' => $fresh, 'article_id' => $target, 'digest' => $prepared['digest'], 'width' => $info[0], 'height' => $info[1], 'created_at' => now()]);
                }
                $revision = (string) Str::uuid7();
                DB::table('educational_article_revisions')->insert($next + ['id' => $revision, 'article_id' => $target, 'version' => $version, 'owner_id' => $owner->id, 'action' => $action, 'body_document' => ArticleDocument::encode($children['body']), 'review_acknowledged' => $action === 'publish', 'health_acknowledged' => $action === 'publish' && $next['editorial_scope'] === 'health_related', 'created_at' => now()]);
                foreach (['products' => 'product_id', 'concerns' => 'concern_id'] as $kind => $column) {
                    $rows = array_map(fn ($row) => ['id' => (string) Str::uuid7(), 'revision_id' => $revision, $column => $row['id'], 'sort_order' => $row['sort_order']], $children[$kind]);
                    if ($rows) {
                        DB::table('educational_revision_'.$kind)->insert($rows);
                    }
                }
                $sources = array_map(fn ($row) => $row + ['id' => (string) Str::uuid7(), 'revision_id' => $revision], $children['sources']);
                if ($sources) {
                    DB::table('educational_revision_sources')->insert($sources);
                }
                $images = array_map(fn ($row) => $row + ['id' => (string) Str::uuid7(), 'revision_id' => $revision, 'article_id' => $target], $children['images']);
                if ($images) {
                    DB::table('educational_revision_media')->insert($images);
                }
                $published = $action === 'publish' ? $version : ($article->published_version ?? null);
                if (in_array($action, ['unpublish', 'archive', 'restore'], true)) {
                    $published = null;
                }
                DB::table('educational_articles')->where('id', $target)->update(['lock_version' => $version, 'published_version' => $published, 'archived_at' => $action === 'archive' ? now() : null, 'updated_at' => now()]);
                $changed = [];
                foreach ($next + $children as $key => $value) {
                    if (($old[$key] ?? null) !== $value) {
                        $changed[] = $key;
                    }
                }
                EducationalStore::audit($owner->id, $op, $hash, 'article', $target, $action, $before, $version, $changed);

                return $target;
            }, 3);
        } catch (Throwable $exception) {
            if ($fresh !== null && ! Storage::disk('local')->delete('education/'.$fresh.'.png')) {
                logger()->warning('education_media_compensation_failed', ['request_id' => request()->attributes->get('request_id')]);
            }
            throw $exception;
        }
    }

    private function catalog(array $children, ?array $old): void
    {
        $products = Product::withTrashed()->whereKey(array_column($children['products'], 'id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        $brands = Brand::withTrashed()->whereKey($products->pluck('brand_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        $concerns = Concern::withTrashed()->whereKey(array_column($children['concerns'], 'id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        foreach ($children['products'] as $link) {
            $p = $products->get($link['id']);
            abort_unless($p !== null, 422, 'المنتج غير موجود.');
            $b = $brands->get($p->brand_id);
            if (! in_array($p->id, array_column($old['products'] ?? [], 'id'), true)) {
                abort_unless(! $p->trashed() && $p->status->value === 'active' && $b && ! $b->trashed() && $b->status->value === 'active', 422, 'المنتج الجديد أو علامته غير ظاهر.');
            }
        }
        foreach ($children['concerns'] as $link) {
            $c = $concerns->get($link['id']);
            abort_unless($c !== null, 422);
            if (! in_array($c->id, array_column($old['concerns'] ?? [], 'id'), true)) {
                abort_unless(! $c->trashed() && $c->getRawOriginal('status') === 'active', 422, 'الاهتمام الجديد غير ظاهر.');
            }
        }
    }
}
