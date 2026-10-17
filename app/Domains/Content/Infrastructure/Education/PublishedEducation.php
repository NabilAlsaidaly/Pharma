<?php

namespace App\Domains\Content\Infrastructure\Education;

use App\Domains\Catalog\Infrastructure\Persistence\Models\Concern;
use App\Domains\Catalog\Infrastructure\Persistence\Models\Product;
use Illuminate\Support\Facades\DB;

final class PublishedEducation
{
    public function query(array $filters = [])
    {
        // Actual search EXPLAIN otherwise chose all revision history as its driving table.
        // Fixed server-owned MySQL join order also survives paginator COUNT clones.
        $q = DB::query()->fromRaw('educational_articles AS a STRAIGHT_JOIN educational_article_revisions AS r ON r.article_id = a.id AND r.version = a.published_version')
            ->whereNull('a.archived_at')->whereNotNull('a.published_version');
        if (isset($filters['category'])) {
            $category = DB::table('educational_categories')->where('slug', $filters['category'])->whereNull('archived_at')->where('is_active', true)->first(['id']);
            abort_unless($category !== null, 404);
            $q->where('r.category_id', $category->id);
        }
        if (isset($filters['concern'])) {
            $concern = Concern::query()->publiclyVisible()->where('slug', $filters['concern'])->first(['id']);
            abort_unless($concern !== null, 404);
            $q->whereExists(fn ($sub) => $sub->selectRaw('1')->from('educational_revision_concerns as ec')->whereColumn('ec.revision_id', 'r.id')->where('ec.concern_id', $concern->id));
        }
        if (isset($filters['featured'])) {
            $q->where('r.is_featured', (bool) $filters['featured']);
        }
        if (isset($filters['q'])) {
            $literal = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filters['q']);
            $q->where(fn ($s) => $s->whereRaw("r.title LIKE ? ESCAPE '!'", ['%'.$literal.'%'])->orWhereRaw("r.excerpt LIKE ? ESCAPE '!'", ['%'.$literal.'%']));
        }

        return $q;
    }

    public function listing(array $filters)
    {
        EducationalStore::ready();

        return DB::transaction(function () use ($filters) {
            $rows = $this->query($filters)->orderBy('r.sort_order')->orderByDesc('r.created_at')->orderBy('a.id')
                ->paginate($filters['per_page'] ?? 12, ['a.id', 'a.slug', 'r.id as revision_id', 'r.version as revision', 'r.title', 'r.excerpt', 'r.category_id', 'r.category_name', 'r.category_description', 'r.is_featured', 'r.sort_order', 'r.created_at as published_at'], 'page', $filters['page'] ?? 1);
            $ids = $rows->getCollection()->pluck('revision_id')->all();
            $images = $this->images($ids, true)->keyBy('revision_id');
            $visible = DB::table('educational_categories')->whereIn('id', $rows->getCollection()->pluck('category_id')->filter())->whereNull('archived_at')->where('is_active', true)->pluck('slug', 'id');
            $rows->setCollection($rows->getCollection()->map(fn ($row) => $this->summary($row, $visible, $images->get($row->revision_id))));

            return $rows;
        });
    }

    public function item(string $slug): array
    {
        EducationalStore::ready();

        return DB::transaction(function () use ($slug): array {
            $r = $this->query()->where('a.slug', $slug)->first(['r.*', 'a.slug', 'a.id as public_id', 'r.id as revision_id', 'r.version as revision', 'r.created_at as published_at']);
            abort_unless($r, 404);
            $r->id = $r->public_id;
            $category = DB::table('educational_categories')->where('id', $r->category_id)->whereNull('archived_at')->where('is_active', true)->pluck('slug', 'id');
            $images = $this->images([$r->revision_id])->keyBy('media_id');
            $cover = $images->first(fn ($im) => $im->role === 'cover');
            $out = $this->summary($r, $category, $cover);
            $body = json_decode($r->body_document, true, 64, JSON_THROW_ON_ERROR);
            foreach ($body['blocks'] as &$block) {
                if ($block['type'] === 'image') {
                    $image = $images->get($block['media_id']);
                    abort_unless($image && $image->role === 'inline', 503, 'تعذر قراءة وسائط المقال.');
                    $block = ['type' => 'image', 'image' => $this->descriptor($image), 'caption' => $block['caption']];
                }
            }
            unset($block);
            $products = Product::query()->publiclyVisible()->join('educational_revision_products as ep', 'ep.product_id', '=', 'products.id')->where('ep.revision_id', $r->revision_id)->orderBy('ep.sort_order')->orderBy('products.id')->get(['products.id', 'products.name', 'products.slug', 'products.availability_status']);
            $concerns = Concern::query()->publiclyVisible()->join('educational_revision_concerns as ec', 'ec.concern_id', '=', 'concerns.id')->where('ec.revision_id', $r->revision_id)->orderBy('ec.sort_order')->orderBy('concerns.id')->get(['concerns.id', 'concerns.name', 'concerns.slug']);
            $sources = DB::table('educational_revision_sources')->where('revision_id', $r->revision_id)->orderBy('sort_order')->orderBy('url_key')->get(['title', 'url', 'note', 'sort_order'])->map(fn ($s) => (array) $s)->all();

            return $out + ['body_format' => 'structured_blocks', 'body' => $body, 'seo_title' => $r->seo_title, 'meta_description' => $r->meta_description, 'related_products' => $products->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'slug' => $p->slug, 'availability_status' => $p->availability_status->value])->all(), 'related_concerns' => $concerns->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'slug' => $c->slug])->all(), 'sources' => $sources];
        });
    }

    public function media(string $id): void
    {
        EducationalStore::ready();
        abort_unless($this->query()->join('educational_revision_media as rm', 'rm.revision_id', '=', 'r.id')->where('rm.media_id', $id)->exists(), 404);
    }

    private function images(array $ids, bool $coverOnly = false)
    {
        return DB::table('educational_revision_media as rm')->join('educational_media as m', 'm.id', '=', 'rm.media_id')->whereIn('rm.revision_id', $ids)->when($coverOnly, fn ($q) => $q->where('rm.role', 'cover'))->get(['rm.revision_id', 'rm.media_id', 'rm.role', 'rm.alt_text', 'm.width', 'm.height']);
    }

    private function summary(object $r, $visible, ?object $cover): array
    {
        return ['id' => $r->id, 'slug' => $r->slug, 'revision' => (string) $r->revision, 'title' => $r->title, 'excerpt' => $r->excerpt, 'category' => isset($visible[$r->category_id]) ? ['id' => $r->category_id, 'slug' => $visible[$r->category_id], 'name' => $r->category_name, 'description' => $r->category_description] : null, 'cover' => $cover ? $this->descriptor($cover) : null, 'is_featured' => (bool) $r->is_featured, 'sort_order' => $r->sort_order, 'published_at' => $r->published_at];
    }

    private function descriptor(object $image): array
    {
        return ['id' => $image->media_id, 'url' => url('/api/v1/education/media/'.$image->media_id.'.png'), 'alt' => $image->alt_text, 'width' => $image->width, 'height' => $image->height];
    }
}
