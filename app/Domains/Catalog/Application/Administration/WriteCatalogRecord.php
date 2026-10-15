<?php

namespace App\Domains\Catalog\Application\Administration;

use App\Domains\Catalog\Infrastructure\Persistence\Models\Brand;
use App\Domains\Catalog\Infrastructure\Persistence\Models\Concern;
use App\Domains\Catalog\Infrastructure\Persistence\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class WriteCatalogRecord
{
    public const FIELDS = [
        'products' => ['brand_id', 'name', 'slug', 'short_description', 'full_description', 'status', 'availability_status', 'is_recommended', 'usage_instructions', 'suitable_for', 'benefits', 'price_usd', 'price_syp'],
        'brands' => ['name', 'slug', 'description', 'status', 'sort_order', 'meta_title', 'meta_description'],
        'concerns' => ['name', 'slug', 'status', 'sort_order'],
        'variants' => ['name', 'price_usd', 'price_syp', 'availability_status', 'sort_order'],
    ];

    public function execute(string $type, ?string $id, array $input, CatalogChange $change, ?string $parent = null): Model
    {
        abort_unless(isset(self::FIELDS[$type]), 404);

        return DB::transaction(function () use ($type, $id, $input, $change, $parent): Model {
            if ($type === 'variants') {
                Product::query()->whereKey($parent)->lockForUpdate()->firstOrFail();
            }
            $record = $id ? CatalogRecords::model($type)->newQuery()->whereKey($id)->lockForUpdate()->firstOrFail() : CatalogRecords::model($type);
            if ($id) {
                CatalogRecords::version($record, (string) $input['lock_version']);
                abort_if($type === 'variants' && $record->getAttribute('product_id') !== $parent, 404);
            }
            $values = array_intersect_key($input, array_flip(self::FIELDS[$type]));
            if (! $id && $type === 'variants') {
                $values['product_id'] = $parent;
            }
            if (array_key_exists('slug', $values) && $values['slug'] === null && ! $id || ! $id && $type !== 'variants' && ! isset($values['slug'])) {
                $base = Str::slug($values['name'] ?? '') ?: 'catalog';
                $values['slug'] = substr($base, 0, 210).'-'.Str::lower(Str::random(12));
            }
            if ($id && array_key_exists('slug', $values) && $values['slug'] === null) {
                unset($values['slug']);
            }
            if ($type === 'products') {
                Brand::query()->whereKey($values['brand_id'] ?? $record->getAttribute('brand_id'))->lockForUpdate()->firstOrFail();
            }
            if (array_key_exists('concerns', $input)) {
                $ids = $input['concerns'] ?? [];
                $concerns = Concern::withTrashed()->whereKey($ids)->orderBy('id')->lockForUpdate()->get();
                abort_unless($concerns->count() === count($ids), 422, 'تتضمن الاهتمامات سجلاً غير متاح.');
                $existing = $record instanceof Product && $record->exists ? $record->concerns()->withTrashed()->pluck('concerns.id')->all() : [];
                foreach ($concerns as $concern) {
                    abort_if($concern->trashed() && ! in_array($concern->id, $existing, true), 422, 'لا يمكن إضافة اهتمام مؤرشف.');
                }
            }
            $change->save($type, $record, $id ? 'update' : 'create', $values);
            if ($record instanceof Product && array_key_exists('concerns', $input)) {
                $before = $record->concerns()->withTrashed()->orderBy('concerns.id')->pluck('concerns.id')->all();
                $record->concerns()->sync($input['concerns'] ?? []);
                $after = $input['concerns'] ?? [];
                sort($after);
                $change->record($type, $record, 'associations', ['concerns' => $before], ['concerns' => $after]);
            }

            return $record->refresh();
        }, 3);
    }
}
