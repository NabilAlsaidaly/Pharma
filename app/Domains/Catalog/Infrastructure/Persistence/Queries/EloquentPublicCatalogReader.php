<?php

namespace App\Domains\Catalog\Infrastructure\Persistence\Queries;

use App\Domains\Catalog\Application\DTOs\CatalogFilters;
use App\Domains\Catalog\Application\Ports\PublicCatalogReader;
use App\Domains\Catalog\Domain\Enums\CatalogStatus;
use App\Domains\Catalog\Infrastructure\Persistence\Models\Brand;
use App\Domains\Catalog\Infrastructure\Persistence\Models\Concern;
use App\Domains\Catalog\Infrastructure\Persistence\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class EloquentPublicCatalogReader implements PublicCatalogReader
{
    public function brands(int $perPage): LengthAwarePaginator
    {
        return Brand::query()->publiclyVisible()->orderBy('sort_order')->orderBy('id')->paginate($perPage);
    }

    public function brand(string $slug): Brand
    {
        return Brand::query()->publiclyVisible()->where('slug', $slug)->firstOrFail();
    }

    public function concerns(int $perPage): LengthAwarePaginator
    {
        return Concern::query()->publiclyVisible()->orderBy('sort_order')->orderBy('id')->paginate($perPage);
    }

    public function concern(string $slug): Concern
    {
        return Concern::query()->publiclyVisible()->where('slug', $slug)->firstOrFail();
    }

    /** @param list<string> $columns
     * @return Builder<Product>
     */
    private function productsQuery(array $columns = ['products.*']): Builder
    {
        return Product::query()->publiclyVisible()->select($columns)
            ->with(['brand:id,name,slug', 'primaryImage'])
            ->withCount('variants')
            ->withMin('variants', 'price_usd')->withMax('variants', 'price_usd')
            ->withMin('variants', 'price_syp')->withMax('variants', 'price_syp');
    }

    public function products(CatalogFilters $filters, ?string $brandId = null, ?string $concernId = null, bool $recommendedOnly = false): LengthAwarePaginator
    {
        $query = $this->productsQuery(['products.id', 'products.brand_id', 'products.name', 'products.slug', 'products.short_description',
            'products.availability_status', 'products.is_recommended', 'products.price_usd', 'products.price_syp', 'products.created_at']);
        if ($brandId !== null) {
            $query->where('brand_id', $brandId);
        }
        if ($concernId !== null) {
            $query->whereHas('concerns', fn (Builder $c) => $c->where('status', CatalogStatus::Active->value)->whereKey($concernId));
        }
        if ($filters->brand !== null) {
            $query->whereHas('brand', fn (Builder $b) => $b->where('slug', $filters->brand));
        }
        if ($filters->concern !== null) {
            $query->whereHas('concerns', fn (Builder $c) => $c->where('status', CatalogStatus::Active->value)->where('slug', $filters->concern));
        }
        if ($filters->availability !== null) {
            $query->where('availability_status', $filters->availability);
        }
        if ($recommendedOnly || $filters->recommended !== null) {
            $query->where('is_recommended', $recommendedOnly || $filters->recommended);
        }
        if ($filters->q !== null) {
            // Explicit escape character makes %, _ and ! literal search input.
            $literal = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filters->q);
            $query->whereRaw("products.name LIKE ? ESCAPE '!'", ['%'.$literal.'%']);
        }
        match ($filters->sort) {
            'name_asc' => $query->orderBy('name'),
            'name_desc' => $query->orderByDesc('name'),
            default => $query->orderByDesc('created_at'),
        };

        return $query->orderBy('products.id')->paginate($filters->perPage);
    }

    public function product(string $slug): Product
    {
        return $this->productsQuery()->with(['variants', 'images', 'concerns' => fn ($c) => $c->where('status', CatalogStatus::Active->value)->orderBy('sort_order')->orderBy('concerns.id')])
            ->where('products.slug', $slug)->firstOrFail();
    }
}
