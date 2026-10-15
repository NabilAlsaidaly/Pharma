<?php

namespace Tests\Feature\CatalogAdministration;

use App\Domains\Catalog\Infrastructure\Persistence\Models\Product;
use App\Domains\Catalog\Infrastructure\Persistence\Models\ProductImage;
use App\Domains\Catalog\Infrastructure\Persistence\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Tests\OwnerDashboardTestCase;

class CatalogPerformanceTest extends OwnerDashboardTestCase
{
    private function measured(callable $work): int
    {
        $this->app['auth']->forgetGuards();
        DB::enableQueryLog();
        DB::flushQueryLog();
        try {
            $work();

            return count(DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }
    }

    public function test_thirteen_real_http_paths_remain_bounded_at_fifty_and_two_hundred_products(): void
    {
        $brand = $this->brand();
        $concern = $this->concern();
        $product = $this->product(['brand_id' => $brand->id, 'name' => 'مرطب قياس']);
        $product->concerns()->attach($concern);
        for ($i = 0; $i < 10; $i++) {
            ProductVariant::query()->create(['product_id' => $product->id, 'name' => 'خيار '.$i]);
        }
        for ($i = 0; $i < 20; $i++) {
            ProductImage::query()->create(['product_id' => $product->id, 'path' => 'fixture/'.$i.'.png']);
        }
        $variant = $product->variants()->firstOrFail();
        $archived = $this->product(['brand_id' => $brand->id]);
        $archived->delete();
        $this->requestFixture(); // representative retained Cart and immutable Checkout references
        $this->ownerLogin();
        $measurements = [];
        foreach ([50, 200] as $target) {
            while (Product::withTrashed()->count() < $target) {
                $this->product(['brand_id' => $brand->id, 'name' => 'مرطب '.Product::withTrashed()->count()]);
            }
            $counts = [];
            foreach (['product_list' => route('owner.products.index'), 'product_edit' => route('owner.catalog.edit', ['type' => 'products', 'id' => $product->id]), 'product_detail' => route('owner.products.show', ['id' => $product->id]), 'image_listing' => route('owner.catalog.edit', ['type' => 'products', 'id' => $product->id]), 'brand_list' => route('owner.brands.index'), 'concern_list' => route('owner.concerns.index'), 'recycle' => route('owner.catalog.recycle', ['type' => 'products']), 'search' => route('owner.search', ['q' => 'مرطب']), 'overview' => route('owner.dashboard')] as $name => $url) {
                $counts[$name] = $this->measured(fn () => $this->get($url)->assertOk());
            }
            $counts['create'] = $this->measured(fn () => $this->post(route('owner.catalog.store', ['type' => 'products']), ['name' => 'قياس إنشاء', 'brand_id' => $brand->id])->assertRedirect());
            $counts['update'] = $this->measured(fn () => $this->patch(route('owner.catalog.update', ['type' => 'products', 'id' => $product->id]), ['lock_version' => (string) $product->fresh()->lock_version, 'name' => 'مرطب قياس'])->assertRedirect());
            $counts['variant'] = $this->measured(fn () => $this->patch(route('owner.catalog.update', ['type' => 'variants', 'id' => $variant->id]), ['lock_version' => (string) $variant->fresh()->lock_version, 'price_usd' => '5.25'])->assertRedirect());
            $this->post(route('owner.catalog.preview', ['type' => 'products']), ['scope' => 'ids', 'ids' => [$product->id], 'action' => 'recommend'])->assertOk();
            $counts['bulk'] = $this->measured(fn () => $this->post(route('owner.catalog.execute'), ['token' => session('catalog_confirmation.token'), 'confirm' => '1'])->assertRedirect());
            $measurements[$target] = $counts;
        }
        $this->assertSame($measurements[50], $measurements[200]);
        foreach ($measurements[200] as $name => $count) {
            $this->assertLessThanOrEqual(30, $count, $name);
        }
        $plans = DB::select('EXPLAIN SELECT id FROM products WHERE deleted_at IS NULL ORDER BY id LIMIT 25');
        fwrite(STDOUT, 'CATALOG_ADMIN_HTTP_COUNTS '.json_encode($measurements).' ARCHIVE_EXPLAIN '.json_encode($plans).PHP_EOL);
    }
}
