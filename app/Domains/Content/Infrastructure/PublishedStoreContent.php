<?php

namespace App\Domains\Content\Infrastructure;

use App\Domains\Catalog\Infrastructure\Persistence\Models\Brand;
use App\Domains\Catalog\Infrastructure\Persistence\Models\Concern;
use App\Domains\Catalog\Infrastructure\Persistence\Models\Product;
use App\Domains\Content\Application\ContentTypes;
use App\Support\BusinessRaster;
use App\Support\RevisionedStore;
use Illuminate\Support\Facades\DB;

final class PublishedStoreContent
{
    public function query(array $spec)
    {
        RevisionedStore::requireSchema($spec);

        return DB::table($spec['table'].' as d')->join($spec['table'].'_revisions as r', function ($join): void {
            $join->on('r.record_id', '=', 'd.id')->on('r.version', '=', 'd.published_version');
        })->whereNull('d.archived_at')->select(array_map(fn ($field) => 'r.'.$field, array_keys($spec['fields'])))
            ->addSelect(['d.id', 'r.version as revision', 'r.created_at as published_at']);
    }

    public function item(string $type, string $slug): array
    {
        abort_unless(in_array($type, ['pages', 'policies'], true), 404);
        $record = $this->query(ContentTypes::get($type))->where('r.slug', $slug)->first();
        abort_if($record === null, 404);

        return array_replace((array) $record, ['revision' => (string) $record->revision, 'body_format' => 'text/plain']);
    }

    public function listing(string $type, int $page, int $perPage): array
    {
        abort_unless(in_array($type, ['banners', 'faqs'], true), 404);
        $spec = ContentTypes::get($type);
        $query = $this->query($spec)->orderBy('r.sort_order')->orderBy('d.id');
        if ($type === 'banners') {
            $query->addSelect('r.image_path');
        }
        $records = $query->paginate($perPage, ['*'], 'page', $page);
        // Batched Catalog visibility checks, not one query or filesystem probe per banner.
        $destinations = ['products' => [], 'brands' => [], 'concerns' => []];
        if ($type === 'banners') {
            foreach ($records as $record) {
                if ($record->link !== null && preg_match('#^/(products|brands|concerns)/([a-z0-9-]+)/?(?:\?.*)?$#D', $record->link, $match)) {
                    $destinations[$match[1]][] = $match[2];
                }
            }
        }
        $visible = [];
        foreach (['products' => Product::class, 'brands' => Brand::class, 'concerns' => Concern::class] as $kind => $model) {
            if ($destinations[$kind] === []) {
                continue;
            }
            $q = $model::query()->whereIn('slug', $destinations[$kind])->where('status', 'active');
            if ($kind === 'products') {
                $q->whereHas('brand', fn ($brand) => $brand->where('status', 'active'));
            }
            $visible[$kind] = $q->pluck('slug')->all();
        }
        $data = [];
        foreach ($records as $record) {
            $item = (array) $record;
            $item['revision'] = (string) $record->revision;
            if ($type === 'banners') {
                if ($record->link !== null && preg_match('#^/(products|brands|concerns)/([a-z0-9-]+)/?(?:\?.*)?$#D', $record->link, $match) && ! in_array($match[2], $visible[$match[1]] ?? [], true)) {
                    // Preserve the public banner without promising a hidden/archived Catalog destination.
                    $item['link'] = null;
                }
                $item['image_url'] = BusinessRaster::url($item['image_path']);
                unset($item['image_path']);
            } else {
                $item['answer_format'] = 'text/plain';
            }
            $data[] = $item;
        }

        return ['data' => $data, 'meta' => ['current_page' => $records->currentPage(), 'per_page' => $records->perPage(), 'total' => $records->total(), 'last_page' => $records->lastPage()]];
    }
}
