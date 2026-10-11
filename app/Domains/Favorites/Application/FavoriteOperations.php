<?php

namespace App\Domains\Favorites\Application;

use App\Domains\Catalog\Application\Ports\CatalogSelectionReader;
use App\Domains\Catalog\Infrastructure\Persistence\Models\Product;
use App\Domains\Identity\Infrastructure\Persistence\Models\Customer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class FavoriteOperations
{
    public function __construct(private readonly CatalogSelectionReader $catalog) {}

    public function listing(string $customerId, int $perPage): LengthAwarePaginator
    {
        return Product::query()->publiclyVisible()->join('favorites', 'favorites.product_id', '=', 'products.id')
            ->where('favorites.customer_id', $customerId)
            ->select(['products.id', 'products.brand_id', 'products.name', 'products.slug', 'products.short_description',
                'products.availability_status', 'products.is_recommended', 'products.price_usd', 'products.price_syp'])
            ->with(['brand:id,name,slug', 'primaryImage'])->withCount('variants')
            ->withMin('variants', 'price_usd')->withMax('variants', 'price_usd')->withMin('variants', 'price_syp')->withMax('variants', 'price_syp')
            ->orderByDesc('favorites.created_at')->orderBy('products.id')->paginate($perPage);
    }

    public function check(string $customerId, string $productId): array
    {
        Product::query()->publiclyVisible()->whereKey($productId)->firstOrFail(['id']);

        return ['product_id' => $productId, 'is_favorite' => DB::table('favorites')->where('customer_id', $customerId)->where('product_id', $productId)->exists()];
    }

    public function add(string $customerId, string $productId): array
    {
        return DB::transaction(function () use ($customerId, $productId) {
            Customer::query()->whereKey($customerId)->lockForUpdate()->firstOrFail();
            $products = $this->catalog->read([$productId], [], true);
            abort_if(! ($products[$productId]['visible'] ?? false), 404);
            $query = DB::table('favorites')->where('customer_id', $customerId)->where('product_id', $productId);
            $created = ! $query->exists();
            if ($created) {
                DB::table('favorites')->insert(['customer_id' => $customerId, 'product_id' => $productId, 'created_at' => now()]);
            }

            return ['product_id' => $productId, 'is_favorite' => true, 'created' => $created];
        }, 3);
    }

    public function remove(string $customerId, string $productId): array
    {
        return DB::transaction(function () use ($customerId, $productId) {
            Customer::query()->whereKey($customerId)->lockForUpdate()->firstOrFail();
            DB::table('favorites')->where('customer_id', $customerId)->where('product_id', $productId)->delete();

            return ['product_id' => $productId, 'is_favorite' => false];
        }, 3);
    }
}
