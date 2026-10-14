<?php

namespace Tests\Feature\OwnerDashboard;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\OwnerDashboardTestCase;

class OwnerDashboardListingsTest extends OwnerDashboardTestCase
{
    public function test_product_filters_sort_page_bounds_and_variant_price_ownership_are_real(): void
    {
        $brand = $this->brand(['name' => 'العلامة الأولى']);
        $otherBrand = $this->brand(['name' => 'العلامة الثانية']);
        $concern = $this->concern();
        for ($i = 0; $i < 26; $i++) {
            $product = $this->product(['name' => 'عناية '.str_pad((string) $i, 2, '0', STR_PAD_LEFT), 'status' => $i === 0 ? 'hidden' : 'active'], $brand);
            $product->concerns()->attach($concern);
        }
        $other = $this->product(['name' => 'مختلف'], $otherBrand);
        $variant = $this->variant($other, ['price_usd' => '12.34', 'price_syp' => '123.45']);
        $this->ownerLogin();
        $filters = ['q' => 'عناية', 'brand' => $brand->id, 'status' => 'active', 'availability' => 'available', 'concern' => $concern->id, 'per_page' => 10, 'sort' => 'name'];
        $response = $this->get(route('owner.products.index', $filters))->assertOk();
        $page = $response->viewData('records');
        $this->assertSame(25, $page->total());
        $this->assertCount(10, $page->items());
        $this->assertSame('عناية 01', $page->items()[0]->name);
        $this->assertStringContainsString('brand='.$brand->id, $page->nextPageUrl());
        $second = $this->get(route('owner.products.index', $filters + ['page' => 2]))->assertOk()->viewData('records');
        $this->assertSame('عناية 11', $second->items()[0]->name);
        $this->get(route('owner.products.index', ['page' => 1000]))->assertOk()->assertSee('لا توجد سجلات تطابق');
        $detail = $this->get(route('owner.products.show', ['id' => $other->id]))->assertOk()->assertSee('12.34')->assertSee('123.45')->assertSee('تسعير هذا المنتج مسجّل لكل خيار');
        $detail->assertDontSee('السعر الحالي بالدولار');
        $this->assertSame($variant->id, $detail->viewData('related')->items()[0]->id);
    }

    public function test_brand_and_concern_lists_counts_hidden_details_and_related_records_are_bounded(): void
    {
        $brand = $this->brand(['status' => 'hidden', 'name' => 'ترطيب']);
        $concern = $this->concern(['status' => 'hidden', 'name' => 'ترطيب']);
        for ($i = 0; $i < 28; $i++) {
            $product = $this->product(['name' => 'Product '.$i], $brand);
            $product->concerns()->attach($concern);
        }
        $this->ownerLogin();
        foreach (['brands' => $brand, 'concerns' => $concern] as $resource => $record) {
            $list = $this->get(route('owner.'.$resource.'.index', ['status' => 'hidden', 'q' => 'ترطيب']))->assertOk();
            $this->assertSame(28, $list->viewData('records')->items()[0]->products_count);
            $detail = $this->get(route('owner.'.$resource.'.show', ['id' => $record->id]))->assertOk();
            $this->assertCount(25, $detail->viewData('related')->items());
            $this->assertSame(28, $detail->viewData('related')->total());
            $this->get(route('owner.'.$resource.'.show', ['id' => $record->id, 'page' => 2]))->assertOk();
        }
    }

    public function test_request_list_safe_fields_currency_dates_and_read_only_detail_snapshot(): void
    {
        $one = $this->requestFixture();
        $two = $this->requestFixture();
        DB::table('checkout_intents')->where('id', $one)->update(['created_at' => '2026-10-12 12:00:00']);
        DB::table('checkout_intents')->where('id', $two)->update(['created_at' => '2026-10-13 12:00:00', 'currency' => 'SYP']);
        $this->ownerLogin();
        $response = $this->get(route('owner.requests.index'))->assertOk();
        foreach (['Private-contact-name-sentinel', 'private-contact-sentinel@example.test', 'Private-address-sentinel', '+1234567890', 'Private-notes-sentinel'] as $private) {
            $response->assertDontSee($private);
        }
        $filtered = $this->get(route('owner.requests.index', ['currency' => 'USD', 'status' => 'submitted', 'from' => '2026-10-12', 'to' => '2026-10-12']))->assertOk()->viewData('records');
        $this->assertSame(1, $filtered->total());
        $this->assertSame($one, $filtered->items()[0]->id);
        $this->assertSame(1, $filtered->items()[0]->items_count);
        DB::table('checkout_intents')->where('id', $one)->update(['notes' => '<script>private-notes-fixture</script>']);
        $this->get(route('owner.requests.show', ['id' => $one]))->assertOk()->assertSee('<script>private-notes-fixture</script>')->assertDontSee('<script>private-notes-fixture</script>', false)->assertSee('Private-address-sentinel');
        $this->get(route('owner.requests.show', ['id' => (string) Str::uuid7()]))->assertNotFound()->assertDontSee('SQLSTATE');
    }

    public static function badFilters(): array
    {
        return [
            'sort-injection' => ['products', ['sort' => 'name; DROP TABLE products']],
            'arbitrary-column' => ['products', ['column' => 'password']],
            'bad-state' => ['products', ['status' => 'deleted']],
            'bad-availability' => ['products', ['availability' => 'reserved']],
            'bad-brand' => ['products', ['brand' => 'wrong']],
            'page-zero' => ['products', ['page' => 0]],
            'page-too-large' => ['products', ['page' => 1001]],
            'size-too-large' => ['products', ['per_page' => 200]],
            'payment-status' => ['requests', ['status' => 'confirmed']],
            'unsupported-currency' => ['requests', ['currency' => 'EUR']],
            'pii-search' => ['requests', ['email' => 'sentinel@example.test']],
            'incomplete-dates' => ['requests', ['from' => '2026-10-12']],
        ];
    }

    #[DataProvider('badFilters')]
    public function test_sort_filter_and_pagination_are_allowlisted(string $resource, array $query): void
    {
        $this->ownerLogin();
        $this->get(route('owner.'.$resource.'.index', $query))->assertUnprocessable()->assertDontSee('SQLSTATE');
    }
}
