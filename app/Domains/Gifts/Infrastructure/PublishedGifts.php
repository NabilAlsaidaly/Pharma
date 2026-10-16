<?php

namespace App\Domains\Gifts\Infrastructure;

use App\Domains\Gifts\Domain\GiftRules;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class PublishedGifts
{
    /** Eligibility is in SQL before pagination; no sparse post-filtered pages or hidden component metadata. */
    public function query(): Builder
    {
        GiftStore::ready();

        // Fixed, server-owned MySQL join order also survives paginator COUNT/EXISTS clones:
        // select current Package pointers before probing their immutable revision/components.
        return DB::query()->fromRaw('gift_packages AS p STRAIGHT_JOIN gift_package_revisions AS r ON r.package_id = p.id AND r.version = p.published_version')
            ->whereNull('p.archived_at')->whereNotNull('p.published_version')->where('r.availability', '!=', 'unavailable')
            ->whereNotExists(function ($q): void {
                // Keep this indexed current-revision probe dependent: materializing an anti-join
                // otherwise scans unrelated historical composition revisions as history grows.
                $q->selectRaw('/*+ NO_SEMIJOIN() */ 1')->from('gift_revision_components as c')
                    ->leftJoin('products as cp', 'cp.id', '=', 'c.product_id')
                    ->leftJoin('brands as b', 'b.id', '=', 'cp.brand_id')
                    ->leftJoin('product_variants as v', function ($j): void {
                        $j->on('v.id', '=', 'c.variant_id')->on('v.product_id', '=', 'c.product_id');
                    })->whereColumn('c.revision_id', 'r.id')->where('c.type', 'catalog')
                    ->where(function ($bad): void {
                        $bad->whereNull('cp.id')->orWhereNotNull('cp.deleted_at')->orWhere('cp.status', '!=', 'active')->orWhere('cp.availability_status', 'unavailable')
                            ->orWhereNull('b.id')->orWhereNotNull('b.deleted_at')->orWhere('b.status', '!=', 'active')
                            ->orWhere(function ($invalid): void {
                                $invalid->whereNotNull('c.variant_id')->where(function ($v): void {
                                    $v->whereNull('v.id')->orWhereNotNull('v.deleted_at')->orWhere('v.availability_status', 'unavailable');
                                });
                            })->orWhere(function ($physical): void {
                                $physical->whereNull('c.variant_id')->whereExists(fn ($v) => $v->selectRaw('1')->from('product_variants as physical_v')->whereColumn('physical_v.product_id', 'c.product_id'));
                            });
                    });
            })->select(['p.id', 'p.slug', 'r.id as revision_id', 'r.version', 'r.name', 'r.short_description', 'r.description', 'r.suitability', 'r.price_usd', 'r.price_syp', 'r.availability', 'r.sort_order', 'r.created_at as published_at'])
            ->selectSub(function ($q): void {
                $q->from('gift_revision_components as rc')->leftJoin('products as rp', 'rp.id', '=', 'rc.product_id')->leftJoin('product_variants as rv', 'rv.id', '=', 'rc.variant_id')
                    ->whereColumn('rc.revision_id', 'r.id')->where('rc.type', 'catalog')->where(fn ($w) => $w->where('rp.availability_status', 'on_request')->orWhere('rv.availability_status', 'on_request'))->selectRaw('COUNT(*)');
            }, 'review_components');
    }

    public function listing(array $filters)
    {
        $q = $this->query();
        if (isset($filters['occasion_id'])) {
            $q->whereExists(fn ($s) => $s->selectRaw('1')->from('gift_revision_occasions as go')->join('gift_occasions as o', 'o.id', '=', 'go.occasion_id')->whereColumn('go.revision_id', 'r.id')->where('o.id', $filters['occasion_id'])->where('o.is_active', true)->whereNull('o.archived_at'));
        }
        if (isset($filters['availability'])) {
            $q->where('r.availability', $filters['availability']);
        }
        $page = $q->orderBy('r.sort_order')->orderBy('p.id')->paginate($filters['per_page'] ?? 12, ['*'], 'page', $filters['page'] ?? 1);
        $page->setCollection(collect($this->present($page->items(), false)));

        return $page;
    }

    public function item(string $slug): array
    {
        $row = $this->query()->where('p.slug', $slug)->first();
        abort_if(! $row, 404);

        return $this->present([$row], true)[0];
    }

    private function present(array $rows, bool $detail): array
    {
        $ids = array_column($rows, 'revision_id');
        $images = DB::table('gift_revision_images')->whereIn('revision_id', $ids)->orderByDesc('is_primary')->orderBy('sort_order')->orderBy('media_id')->get()->groupBy('revision_id');
        $occasions = DB::table('gift_revision_occasions as ro')->join('gift_occasions as o', 'o.id', '=', 'ro.occasion_id')->whereIn('ro.revision_id', $ids)->where('o.is_active', true)->whereNull('o.archived_at')->select(['ro.revision_id', 'ro.occasion_id as id', 'ro.name'])->orderBy('o.sort_order')->orderBy('o.id')->get()->groupBy('revision_id');
        $components = $detail ? DB::table('gift_revision_components')->whereIn('revision_id', $ids)->orderBy('sort_order')->orderBy('id')->get()->groupBy('revision_id') : collect();
        $out = [];
        foreach ($rows as $row) {
            $ims = collect($images->get($row->revision_id, []))->map(fn ($im) => ['id' => $im->media_id, 'url' => url('/api/v1/gifts/media/'.$im->media_id.'.png'), 'alt_text' => $im->alt_text, 'is_primary' => (bool) $im->is_primary, 'sort_order' => $im->sort_order])->values()->all();
            $review = $row->availability === 'on_request' || $row->review_components > 0;
            $item = ['id' => $row->id, 'slug' => $row->slug, 'name' => $row->name, 'short_description' => $row->short_description, 'published_version' => (string) $row->version, 'prices' => ['USD' => $row->price_usd, 'SYP' => $row->price_syp], 'availability' => $row->availability,
                'eligibility' => ['USD' => GiftRules::positive($row->price_usd) ? ($review ? 'requires_fulfillment_review' : 'eligible') : 'currency_price_unavailable', 'SYP' => GiftRules::positive($row->price_syp) ? ($review ? 'requires_fulfillment_review' : 'eligible') : 'currency_price_unavailable'],
                'primary_image' => $ims[0] ?? null, 'occasions' => collect($occasions->get($row->revision_id, []))->map(fn ($o) => ['id' => $o->id, 'name' => $o->name])->all()];
            if ($detail) {
                $item += ['description' => $row->description, 'suitability' => $row->suitability, 'body_format' => 'text/plain', 'images' => $ims, 'components' => collect($components->get($row->revision_id, []))->map(fn ($c) => ['type' => $c->type, 'product_id' => $c->product_id, 'variant_id' => $c->variant_id, 'name' => $c->name, 'variant_name' => $c->variant_name, 'description' => $c->description, 'quantity' => $c->quantity, 'sort_order' => $c->sort_order])->all()];
            }
            $out[] = $item;
        }

        return $out;
    }

    public function media(string $id): void
    {
        abort_unless($this->query()->whereExists(fn ($q) => $q->selectRaw('1')->from('gift_revision_images as im')->whereColumn('im.revision_id', 'r.id')->where('im.media_id', $id))->exists(), 404);
    }
}
