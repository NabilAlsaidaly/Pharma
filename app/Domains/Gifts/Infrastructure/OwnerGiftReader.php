<?php

namespace App\Domains\Gifts\Infrastructure;

use App\Domains\Gifts\Application\GiftInput;
use Illuminate\Support\Facades\DB;

final class OwnerGiftReader
{
    public function packages(array $filters)
    {
        GiftStore::ready();

        return DB::table('gift_packages as p')->join('gift_package_revisions as r', function ($j): void {
            $j->on('r.package_id', '=', 'p.id')->on('r.version', '=', 'p.lock_version');
        })->select(['p.id', 'p.slug', 'p.lock_version', 'p.published_version', 'p.archived_at', 'r.name', 'r.price_usd', 'r.price_syp'])->orderByDesc('p.created_at')->orderBy('p.id')->paginate(25, ['*'], 'page', $filters['page'] ?? 1);
    }

    public function package(?string $id, array $filters): array
    {
        GiftStore::ready();

        return DB::transaction(function () use ($id, $filters): array {
            $record = $id ? DB::table('gift_packages')->where('id', $id)->first() : null;
            abort_if($id && ! $record, 404);
            $draft = $record ? GiftStore::revision($id, (string) $record->lock_version) : ['name' => '', 'availability' => 'unavailable', 'sort_order' => 0, 'components' => [], 'images' => [], 'occasions' => []];
            $history = DB::table('gift_package_revisions')->where('package_id', $id)->orderByDesc('version')->paginate(25, ['version', 'action', 'created_at'], 'page', $filters['page'] ?? 1);
            $snapshot = isset($filters['revision']) && $record ? GiftStore::revision($id, $filters['revision']) : null;
            $products = DB::table('products as p')->join('brands as b', 'b.id', '=', 'p.brand_id')->whereNull('p.deleted_at')->whereNull('b.deleted_at')->where('p.status', 'active')->where('b.status', 'active');
            if (isset($filters['catalog_q'])) {
                $products->whereRaw("p.name LIKE ? ESCAPE '!'", ['%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filters['catalog_q']).'%']);
            }
            $products = $products->select(['p.id', 'p.name'])->orderBy('p.name')->orderBy('p.id')->offset((($filters['catalog_page'] ?? 1) - 1) * 50)->limit(50)->get();
            $variantQuery = DB::table('product_variants')->whereIn('product_id', $products->pluck('id'))->whereNull('deleted_at');
            if (isset($filters['variant_q'])) {
                $variantQuery->whereRaw("name LIKE ? ESCAPE '!'", ['%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filters['variant_q']).'%']);
            }
            $variants = $variantQuery->orderBy('product_id')->orderBy('sort_order')->orderBy('id')->offset((($filters['variant_page'] ?? 1) - 1) * 500)->limit(500)->get(['id', 'product_id', 'name', 'deleted_at'])->groupBy('product_id');
            $choices = [];
            foreach ($products as $p) {
                $vs = $variants->get($p->id, collect());
                if ($vs->isEmpty()) {
                    // Separate SQL physical existence batch below prevents a truncated directory implying direct selection.
                    $choices[$p->id.'|'] = $p->name;
                } else {
                    foreach ($vs as $v) {
                        if ($v->deleted_at === null) {
                            $choices[$p->id.'|'.$v->id] = $p->name.' — '.$v->name;
                        }
                    }
                }
            }
            $physical = DB::table('product_variants')->whereIn('product_id', $products->pluck('id'))->distinct()->pluck('product_id');
            foreach ($physical as $pid) {
                unset($choices[$pid.'|']);
            }
            foreach ($draft['components'] as $c) {
                if ($c['type'] === 'catalog') {
                    $choices[$c['product_id'].'|'.($c['variant_id'] ?? '')] ??= $c['name'].($c['variant_name'] ? ' — '.$c['variant_name'] : '').' (اختيار محفوظ؛ يخضع للتحقق)';
                }
            }
            $occasionQuery = DB::table('gift_occasions')->whereNull('archived_at')->where('is_active', true);
            if (isset($filters['occasion_q'])) {
                $occasionQuery->whereRaw("name LIKE ? ESCAPE '!'", ['%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filters['occasion_q']).'%']);
            }
            $occasionChoices = $occasionQuery->orderBy('sort_order')->orderBy('id')->offset((($filters['occasion_page'] ?? 1) - 1) * 100)->limit(100)->get()->keyBy('id');
            $retainedOccasions = DB::table('gift_occasions')->whereIn('id', array_column($draft['occasions'], 'occasion_id'))->get()->keyBy('id');
            $occasionChoices = $occasionChoices->union($retainedOccasions)->values();

            return compact('record', 'draft', 'history', 'snapshot', 'choices', 'occasionChoices');
        });
    }

    public static function filters(array $input): array
    {
        GiftInput::only($input, ['page', 'catalog_q', 'catalog_page', 'variant_q', 'variant_page', 'occasion_q', 'occasion_page', 'revision']);
        foreach (['catalog_q', 'variant_q', 'occasion_q'] as $key) {
            if (array_key_exists($key, $input) && ($input[$key] === '' || $input[$key] === null)) {
                unset($input[$key]);
            }
        }
        $v = validator($input, ['page' => 'sometimes|integer|between:1,1000', 'catalog_q' => 'sometimes|string|max:100', 'catalog_page' => 'sometimes|integer|between:1,1000', 'variant_q' => 'sometimes|string|max:100', 'variant_page' => 'sometimes|integer|between:1,1000', 'occasion_q' => 'sometimes|string|max:100', 'occasion_page' => 'sometimes|integer|between:1,1000', 'revision' => 'sometimes|string|regex:/^[1-9][0-9]{0,19}$/D'])->validate();
        if (isset($v['revision'])) {
            GiftInput::version($v['revision'], advancing: false);
        }

        return $v;
    }
}
