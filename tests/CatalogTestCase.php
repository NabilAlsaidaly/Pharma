<?php

namespace Tests;

use App\Domains\Catalog\Infrastructure\Persistence\Models\Brand;
use App\Domains\Catalog\Infrastructure\Persistence\Models\Concern;
use App\Domains\Catalog\Infrastructure\Persistence\Models\Product;
use App\Domains\Catalog\Infrastructure\Persistence\Models\ProductImage;
use App\Domains\Catalog\Infrastructure\Persistence\Models\ProductVariant;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

abstract class CatalogTestCase extends IdentityTestCase
{
    private int $sequence = 0;

    protected function brand(array $values = []): Brand
    {
        return Brand::query()->create(array_replace(['name' => 'علامة تجريبية', 'slug' => 'brand-'.++$this->sequence, 'status' => 'active'], $values));
    }

    protected function product(array $values = [], ?Brand $brand = null): Product
    {
        return Product::query()->create(array_replace(['brand_id' => ($brand ?? $this->brand())->id, 'name' => 'مرطب البشرة',
            'slug' => 'product-'.++$this->sequence, 'status' => 'active', 'availability_status' => 'available'], $values));
    }

    protected function concern(array $values = []): Concern
    {
        return Concern::query()->create(array_replace(['name' => 'الجفاف', 'slug' => 'concern-'.++$this->sequence, 'status' => 'active'], $values));
    }

    protected function variant(Product $product, array $values = []): ProductVariant
    {
        return ProductVariant::query()->create(array_replace(['product_id' => $product->id, 'name' => '50ml', 'availability_status' => 'available'], $values));
    }

    protected function image(Product $product, array $values = []): ProductImage
    {
        return ProductImage::query()->create(array_replace(['product_id' => $product->id, 'path' => 'catalog/test.png'], $values));
    }

    protected function assertConstraint(callable $operation, int $mysqlCode): void
    {
        try {
            $operation();
            $this->fail('Expected a real MySQL constraint rejection.');
        } catch (QueryException $e) {
            $this->assertSame($mysqlCode, $e->errorInfo[1]);
        }
    }

    protected function rawRow(string $table, array $values): array
    {
        return array_replace(['id' => (string) Str::uuid7(), 'created_at' => now(), 'updated_at' => now()], $values);
    }
}
