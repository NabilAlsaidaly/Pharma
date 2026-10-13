<?php

namespace App\Domains\Catalog\Infrastructure\Persistence\Queries;

use App\Domains\Catalog\Application\Ports\CatalogSelectionReader;
use App\Domains\Catalog\Domain\Enums\CatalogStatus;
use App\Domains\Catalog\Infrastructure\Persistence\Models\Brand;
use App\Domains\Catalog\Infrastructure\Persistence\Models\Product;
use App\Domains\Catalog\Infrastructure\Persistence\Models\ProductVariant;

class EloquentCatalogSelectionReader implements CatalogSelectionReader
{
    public function read(array $productIds, array $variantIds, bool $lock = false): array
    {
        if ($productIds === []) {
            return [];
        }
        $query = Product::query()->select(['id', 'brand_id', 'name', 'slug', 'status', 'availability_status', 'price_usd', 'price_syp'])
            ->whereKey(array_unique($productIds))->orderBy('id');
        // A locking outer query does not make its aggregate subquery a current read.
        // Structural Variant changes must be observed after waiting for the Product lock.
        $query->withCount($lock ? ['variants' => fn ($variants) => $variants->lockForUpdate()] : 'variants');
        if ($lock) {
            $query->lockForUpdate();
        }
        $products = $query->get();
        $variantQuery = ProductVariant::query()->whereKey(array_unique(array_filter($variantIds)))->orderBy('id');
        if ($lock) {
            $variantQuery->lockForUpdate();
        }
        $variants = $variantQuery->get()->keyBy('id');
        $brandQuery = Brand::query()->select(['id', 'status'])->whereKey($products->pluck('brand_id'))->orderBy('id');
        if ($lock) {
            $brandQuery->lockForUpdate();
        }
        $brands = $brandQuery->get()->keyBy('id');
        $products->load('primaryImage');
        $result = [];
        foreach ($products as $product) {
            $brand = $brands->get($product->brand_id);
            $result[$product->id] = ['id' => $product->id, 'name' => $product->name, 'slug' => $product->slug,
                'visible' => $product->status === CatalogStatus::Active && $brand?->status === CatalogStatus::Active,
                'availability' => $product->availability_status, 'price_usd' => $product->price_usd, 'price_syp' => $product->price_syp,
                'variants_count' => $product->variants_count, 'image' => $product->primaryImage?->only(['id', 'path', 'is_primary', 'sort_order']),
                'variants' => []];
        }
        foreach ($variants as $variant) {
            if (isset($result[$variant->product_id])) {
                $result[$variant->product_id]['variants'][$variant->id] = ['id' => $variant->id, 'name' => $variant->name,
                    'availability' => $variant->availability_status, 'price_usd' => $variant->price_usd, 'price_syp' => $variant->price_syp];
            }
        }

        return $result;
    }
}
