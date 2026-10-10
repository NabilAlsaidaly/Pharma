<?php

namespace Tests\Feature\Catalog;

use App\Domains\Catalog\Infrastructure\Persistence\Models\ProductVariant;
use Illuminate\Support\Facades\Route;
use Tests\CatalogTestCase;

class PublicCatalogApiTest extends CatalogTestCase
{
    public function test_guests_can_browse_all_eight_endpoints_without_customer_authentication(): void
    {
        $brand = $this->brand();
        $p = $this->product(['is_recommended' => true], $brand);
        $c = $this->concern();
        $p->concerns()->attach($c);
        foreach (['/brands', '/brands/'.$brand->slug, '/brands/'.$brand->slug.'/products', '/products', '/products/recommended', '/products/'.$p->slug, '/concerns', '/concerns/'.$c->slug.'/products'] as $path) {
            $response = $this->get('/api/v1'.$path);
            $response->assertOk()->assertJsonPath('success', true)->assertHeader('X-Request-ID');
        }
    }

    public function test_active_products_with_no_prices_are_visible_and_have_no_implicit_variant(): void
    {
        $p = $this->product();
        $this->assertSame(0, ProductVariant::query()->count());
        $this->get('/api/v1/products')->assertOk()->assertJsonPath('data.0.id', $p->id)
            ->assertJsonPath('data.0.pricing.source', 'product')->assertJsonPath('data.0.pricing.price_usd', null)->assertJsonPath('data.0.pricing.price_syp', null);
        $this->get('/api/v1/products/'.$p->slug)->assertOk()->assertJsonPath('data.variants', [])->assertJsonPath('data.primary_image', null)->assertJsonPath('data.images', []);
    }

    public function test_product_only_prices_are_decimal_strings_and_missing_currency_never_becomes_zero(): void
    {
        $p = $this->product(['price_usd' => '12.50']);
        $this->get('/api/v1/products/'.$p->slug)->assertOk()->assertJsonPath('data.pricing.price_usd', '12.50')->assertJsonPath('data.pricing.price_syp', null);
        $p->update(['price_usd' => null, 'price_syp' => '0.00']);
        $this->get('/api/v1/products/'.$p->slug)->assertOk()->assertJsonPath('data.pricing.price_usd', null)->assertJsonPath('data.pricing.price_syp', '0.00');
    }

    public function test_variant_prices_are_independent_and_listing_ranges_ignore_null_without_inheritance(): void
    {
        $p = $this->product();
        $a = $this->variant($p, ['price_usd' => '10.00', 'sort_order' => 0]);
        $b = $this->variant($p, ['price_syp' => '150000.00', 'sort_order' => 1]);
        $this->get('/api/v1/products')->assertOk()->assertJsonPath('data.0.pricing.source', 'variants')
            ->assertJsonPath('data.0.pricing.price_usd', null)->assertJsonPath('data.0.pricing.range_usd.min', '10.00')->assertJsonPath('data.0.pricing.range_syp.max', '150000.00');
        $this->get('/api/v1/products/'.$p->slug)->assertOk()->assertJsonPath('data.variants.0.id', $a->id)
            ->assertJsonPath('data.variants.0.price_syp', null)->assertJsonPath('data.variants.1.id', $b->id)->assertJsonPath('data.variants.1.price_usd', null);
    }

    public function test_variant_products_with_all_missing_prices_have_null_ranges_and_remain_visible(): void
    {
        $p = $this->product();
        $this->variant($p);
        $this->get('/api/v1/products')->assertOk()->assertJsonPath('data.0.pricing.range_usd.min', null)->assertJsonPath('data.0.pricing.range_usd.max', null)
            ->assertJsonPath('data.0.pricing.range_syp.min', null)->assertJsonPath('data.0.pricing.range_syp.max', null);
    }

    public function test_variant_availability_obeys_parent_precedence_without_overwriting_parent(): void
    {
        $p = $this->product(['availability_status' => 'on_request']);
        $this->variant($p, ['availability_status' => 'available']);
        $this->get('/api/v1/products/'.$p->slug)->assertOk()->assertJsonPath('data.availability_status', 'on_request')
            ->assertJsonPath('data.variants.0.availability_status', 'available')->assertJsonPath('data.variants.0.effective_availability_status', 'on_request');
        $p->update(['availability_status' => 'unavailable']);
        $this->get('/api/v1/products/'.$p->slug)->assertOk()->assertJsonPath('data.variants.0.effective_availability_status', 'unavailable');
        $this->assertSame('unavailable', $p->fresh()->availability_status->value);
    }

    public function test_hidden_products_never_leak_through_any_product_browse_path(): void
    {
        $b = $this->brand();
        $c = $this->concern();
        $hidden = $this->product(['status' => 'hidden', 'is_recommended' => true], $b);
        $hidden->concerns()->attach($c);
        $this->variant($hidden);
        $this->image($hidden);
        foreach (['/products', '/products/recommended', '/products?q='.urlencode($hidden->name), '/brands/'.$b->slug.'/products', '/concerns/'.$c->slug.'/products'] as $path) {
            $this->get('/api/v1'.$path)->assertOk()->assertJsonCount(0, 'data');
        }
        $this->get('/api/v1/products/'.$hidden->slug)->assertNotFound()->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND');
    }

    public function test_hidden_brand_blocks_its_active_products_and_direct_urls(): void
    {
        $b = $this->brand(['status' => 'hidden']);
        $p = $this->product(['is_recommended' => true], $b);
        $c = $this->concern();
        $p->concerns()->attach($c);
        $this->get('/api/v1/brands')->assertOk()->assertJsonCount(0, 'data');
        foreach (['/products', '/products/recommended', '/products?q='.urlencode($p->name), '/concerns/'.$c->slug.'/products'] as $path) {
            $this->get('/api/v1'.$path)->assertOk()->assertJsonCount(0, 'data');
        }
        foreach (['/products/'.$p->slug, '/brands/'.$b->slug, '/brands/'.$b->slug.'/products'] as $path) {
            $this->get('/api/v1'.$path)->assertNotFound();
        }
    }

    public function test_hidden_concerns_are_not_exposed_and_do_not_hide_otherwise_visible_products(): void
    {
        $p = $this->product();
        $c = $this->concern(['status' => 'hidden']);
        $p->concerns()->attach($c);
        $this->get('/api/v1/concerns')->assertOk()->assertJsonCount(0, 'data');
        $this->get('/api/v1/concerns/'.$c->slug.'/products')->assertNotFound();
        $this->get('/api/v1/products?concern='.$c->slug)->assertOk()->assertJsonCount(0, 'data');
        $this->get('/api/v1/products/'.$p->slug)->assertOk()->assertJsonPath('data.id', $p->id)->assertJsonCount(0, 'data.concerns');
    }

    public function test_brand_concern_availability_and_recommendation_filters_are_composable(): void
    {
        $b = $this->brand();
        $c = $this->concern();
        $p = $this->product(['availability_status' => 'on_request', 'is_recommended' => true], $b);
        $p->concerns()->attach($c);
        $this->product([], $b);
        $this->product();
        $uri = '/api/v1/products?brand='.$b->slug.'&concern='.$c->slug.'&availability=on_request&recommended=true';
        $this->get($uri)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $p->id);
        $this->get('/api/v1/products?recommended=false')->assertOk()->assertJsonCount(2, 'data');
        $this->get('/api/v1/products?brand=missing')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_arabic_search_matches_names_and_literal_wildcards_cannot_broaden_results(): void
    {
        $p = $this->product(['name' => 'مرطب للبشرة الحساسة']);
        $this->product(['name' => 'منظف']);
        $percent = $this->product(['name' => '50% special']);
        $this->get('/api/v1/products?q='.urlencode('الحساسة'))->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $p->id);
        $this->get('/api/v1/products?q=%25')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $percent->id);
        $this->get('/api/v1/products?q='.urlencode("' OR 1=1 --"))->assertOk()->assertJsonCount(0, 'data');
        $this->get('/api/v1/products?q=_')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_pagination_is_bounded_and_empty_pages_are_safe(): void
    {
        $b = $this->brand();
        for ($i = 0; $i < 14; $i++) {
            $this->product([], $b);
        }
        $this->get('/api/v1/products')->assertOk()->assertJsonCount(12, 'data')->assertJsonPath('meta.total', 14)->assertJsonPath('meta.per_page', 12);
        $this->get('/api/v1/products?page=2')->assertOk()->assertJsonCount(2, 'data');
        $this->get('/api/v1/products?page=99')->assertOk()->assertJsonCount(0, 'data')->assertJsonPath('meta.total', 14);
    }

    public function test_invalid_pagination_filters_and_sorting_use_safe_validation_contract(): void
    {
        foreach (['per_page=49', 'per_page=0', 'page=-1', 'sort=price_usd', 'recommended=maybe', 'availability=reserved', 'brand[]=bad', 'q[]=bad', 'q='.str_repeat('a', 201)] as $query) {
            $r = $this->get('/api/v1/products?'.$query);
            $r->assertUnprocessable()->assertJsonPath('error.code', 'VALIDATION_FAILED');
            $this->assertSame($r->headers->get('X-Request-ID'), $r->json('request_id'));
        }
    }

    public function test_pagination_links_keep_validated_filters_and_exclude_unknown_parameters(): void
    {
        $b = $this->brand();
        $this->product([], $b);
        $this->product([], $b);
        $r = $this->get('/api/v1/products?per_page=1&brand='.$b->slug.'&q='.urlencode('مرطب').'&untrusted=ignored');
        $r->assertOk();
        parse_str(parse_url($r->json('links.next'), PHP_URL_QUERY), $query);
        $this->assertSame($b->slug, $query['brand']);
        $this->assertSame('مرطب', $query['q']);
        $this->assertSame('1', $query['per_page']);
        $this->assertArrayNotHasKey('untrusted', $query);
    }

    public function test_name_sort_has_deterministic_uuid_tie_breaker(): void
    {
        $b = $this->brand();
        $a = $this->product(['name' => 'same'], $b);
        $z = $this->product(['name' => 'same'], $b);
        $this->get('/api/v1/products?sort=name_asc')->assertOk()->assertJsonPath('data.0.id', $a->id)->assertJsonPath('data.1.id', $z->id);
        $this->get('/api/v1/products?sort=name_desc')->assertOk()->assertJsonPath('data.0.id', $a->id);
    }

    public function test_detail_only_returns_its_own_ordered_variants_images_and_visible_concerns(): void
    {
        $p = $this->product();
        $other = $this->product();
        $this->variant($other);
        $this->image($other);
        $last = $this->variant($p, ['sort_order' => 10]);
        $first = $this->variant($p, ['sort_order' => 1]);
        $lastImage = $this->image($p, ['sort_order' => 10]);
        $firstImage = $this->image($p, ['sort_order' => 1]);
        $c = $this->concern();
        $p->concerns()->attach($c);
        $this->get('/api/v1/products/'.$p->slug)->assertOk()->assertJsonCount(2, 'data.variants')->assertJsonPath('data.variants.0.id', $first->id)->assertJsonPath('data.variants.1.id', $last->id)
            ->assertJsonCount(2, 'data.images')->assertJsonPath('data.images.0.id', $firstImage->id)->assertJsonPath('data.images.1.id', $lastImage->id)->assertJsonPath('data.concerns.0.id', $c->id);
    }

    public function test_public_catalog_has_exactly_eight_read_routes_and_rejects_every_write_method(): void
    {
        $routes = collect(Route::getRoutes())->filter(fn ($r) => preg_match('#^api/v1/(brands|products|concerns)(/|$)#', $r->uri()));
        $this->assertCount(8, $routes);
        foreach ($routes as $route) {
            $this->assertSame(['GET', 'HEAD'], $route->methods());
        }
        foreach (['POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
            foreach (['/products', '/brands', '/concerns', '/products/example', '/products/example/images'] as $path) {
                $response = $this->call($method, '/api/v1'.$path);
                $this->assertContains($response->status(), [404, 405]);
                $response->assertJsonPath('success', false);
            }
        }
    }
}
