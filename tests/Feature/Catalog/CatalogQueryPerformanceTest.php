<?php

namespace Tests\Feature\Catalog;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Tests\CatalogTestCase;

class CatalogQueryPerformanceTest extends CatalogTestCase
{
    private function capture(string $uri): array
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        Model::preventLazyLoading();
        try {
            $response = $this->get($uri);
            $response->assertOk();

            return [$response, DB::getQueryLog()];
        } finally {
            DB::disableQueryLog();
            Model::preventLazyLoading(false);
        }
    }

    public function test_product_listing_queries_do_not_grow_with_brand_image_or_variant_count(): void
    {
        for ($i = 0; $i < 2; $i++) {
            $p = $this->product();
            $this->image($p);
            $this->variant($p, ['price_usd' => '5.00']);
        }
        [$small,$smallSql] = $this->capture('/api/v1/products?per_page=48');
        $small->assertJsonCount(2, 'data');
        for ($i = 0; $i < 28; $i++) {
            $p = $this->product();
            $this->image($p);
            $this->image($p);
            $this->variant($p, ['price_syp' => '100.00']);
        }
        [$large,$largeSql] = $this->capture('/api/v1/products?per_page=48');
        $large->assertJsonCount(30, 'data');
        $this->assertSame(count($smallSql), count($largeSql));
        $this->assertNotNull($large->json('data.29.brand.id'));
        $this->assertNotNull($large->json('data.29.primary_image.id'));
        fwrite(STDERR, 'CATALOG_QUERY_COUNTS '.json_encode(['products_2' => count($smallSql), 'products_30' => count($largeSql)]).PHP_EOL);
    }

    public function test_every_read_path_has_bounded_queries_and_detail_does_not_load_unrelated_products(): void
    {
        $b = $this->brand();
        $c = $this->concern();
        $p = $this->product(['is_recommended' => true], $b);
        $p->concerns()->attach($c);
        $this->variant($p);
        $this->image($p);
        $counts = [];
        foreach (['brands' => '/brands', 'brand_detail' => '/brands/'.$b->slug, 'products' => '/products', 'product_detail' => '/products/'.$p->slug,
            'brand_products' => '/brands/'.$b->slug.'/products', 'concern_products' => '/concerns/'.$c->slug.'/products', 'recommended' => '/products/recommended', 'search' => '/products?q='.urlencode('مرطب'), 'concerns' => '/concerns'] as $key => $uri) {
            [$r,$queries] = $this->capture('/api/v1'.$uri);
            $counts[$key] = count($queries);
        }
        for ($i = 0; $i < 10; $i++) {
            $other = $this->product([], $b);
            $this->variant($other);
            $this->image($other);
        }
        [$detail,$queries] = $this->capture('/api/v1/products/'.$p->slug);
        $detail->assertJsonCount(1, 'data.variants')->assertJsonCount(1, 'data.images');
        $this->assertSame($counts['product_detail'], count($queries));
        fwrite(STDERR, 'CATALOG_PATH_COUNTS '.json_encode($counts).PHP_EOL);
    }

    public function test_filters_and_limits_execute_in_sql_and_explain_is_observed_on_mysql(): void
    {
        $b = $this->brand();
        $p = $this->product(['name' => 'needle', 'is_recommended' => true], $b);
        for ($i = 0; $i < 20; $i++) {
            $this->product([], $b);
        }
        [$r,$queries] = $this->capture('/api/v1/products?q=needle&brand='.$b->slug.'&per_page=1');
        $r->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $p->id);
        $list = collect($queries)->first(fn ($q) => str_contains($q['query'], 'from `products`') && str_contains($q['query'], 'limit 1'));
        $this->assertNotNull($list);
        $this->assertStringContainsString('LIKE ?', $list['query']);
        $this->assertContains('%needle%', $list['bindings']);
        $plan = DB::select('EXPLAIN FORMAT=TRADITIONAL '.$list['query'], $list['bindings']);
        $this->assertNotEmpty($plan);
        $brief = array_map(fn ($row) => ['table' => $row->table, 'type' => $row->type, 'key' => $row->key, 'rows' => $row->rows, 'extra' => $row->Extra], $plan);
        fwrite(STDERR, 'CATALOG_EXPLAIN '.json_encode($brief).PHP_EOL);
    }
}
