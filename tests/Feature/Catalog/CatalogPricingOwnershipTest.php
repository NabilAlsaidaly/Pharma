<?php

namespace Tests\Feature\Catalog;

use App\Domains\Cart\Application\CartEvaluator;
use App\Domains\Cart\Application\CartOperations;
use App\Domains\Cart\Application\CartSnapshot;
use App\Domains\Cart\Domain\Exceptions\CartConflict;
use App\Domains\Catalog\Application\Administration\ArchiveCatalogRecord;
use App\Domains\Catalog\Application\Administration\CatalogChange;
use App\Domains\Catalog\Application\DTOs\CatalogFilters;
use App\Domains\Catalog\Application\Ports\CatalogSelectionReader;
use App\Domains\Catalog\Application\Ports\PublicCatalogReader;
use App\Domains\Catalog\Domain\Exceptions\ConflictingPriceOwnership;
use App\Domains\Catalog\Infrastructure\Persistence\Models\Product;
use App\Domains\Catalog\Infrastructure\Persistence\Models\ProductVariant;
use App\Domains\Favorites\Application\FavoriteOperations;
use App\Domains\Orders\Application\SubmitPreliminaryCheckoutRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\OwnerDashboardTestCase;

class CatalogPricingOwnershipTest extends OwnerDashboardTestCase
{
    private function archiveVariant(ProductVariant $variant, bool $restore = false): void
    {
        app(ArchiveCatalogRecord::class)->execute('variants', $variant->id, (string) $variant->fresh()->lock_version, $restore, new CatalogChange($this->owner, 'pricing-regression'));
    }

    public function test_archiving_the_only_variant_does_not_change_public_pricing_ownership(): void
    {
        $product = $this->product();
        $variant = $this->variant($product, ['price_usd' => '20.00']);
        app(ArchiveCatalogRecord::class)->execute('variants', $variant->id, '1', false, new CatalogChange($this->owner, 'pricing-regression'));
        $response = $this->get('/api/v1/products/'.$product->slug)->assertOk();
        $selection = app(CatalogSelectionReader::class)->read([$product->id], [$variant->id])[$product->id];
        $evaluated = app(CartEvaluator::class)->item(['id' => 'fixture', 'product_id' => $product->id, 'variant_id' => $variant->id, 'quantity' => 1], $selection);
        fwrite(STDOUT, 'FIX_6C_001_REPRO '.json_encode(['physical' => $product->variants()->withTrashed()->count(), 'current' => $product->variants()->count(), 'public_pricing' => $response->json('data.pricing'), 'public_variants' => $response->json('data.variants'), 'internal_structural_count' => $selection['variants_count'], 'internal_selected_variants' => $selection['variants'], 'cart_evaluation' => $evaluated['evaluation']], JSON_THROW_ON_ERROR).PHP_EOL);
        $this->assertSame(1, $product->variants()->withTrashed()->count());
        $this->assertSame(0, $product->variants()->count());
        $this->assertSame(1, $selection['variants_count']);
        $this->assertSame('blocked', $evaluated['evaluation']['state']);
        $response->assertJsonPath('data.variants', [])->assertJsonPath('data.pricing.source', 'variants')
            ->assertJsonPath('data.pricing.price_usd', null)->assertJsonPath('data.pricing.price_syp', null)
            ->assertJsonPath('data.pricing.range_usd', ['min' => null, 'max' => null])
            ->assertJsonPath('data.pricing.range_syp', ['min' => null, 'max' => null]);
    }

    public function test_no_physical_variants_preserve_direct_prices_null_zero_and_existing_json_shape(): void
    {
        $product = $this->product(['price_usd' => '12.34', 'price_syp' => '98765.43']);
        foreach (['/products', '/products/'.$product->slug] as $path) {
            $data = $this->get('/api/v1'.$path)->assertOk()->json('data');
            $row = $path === '/products' ? $data[0] : $data;
            $this->assertSame(['source' => 'product', 'price_usd' => '12.34', 'price_syp' => '98765.43'], $row['pricing']);
            $this->assertArrayNotHasKey('has_physical_variants', $row);
        }
        $product->update(['price_usd' => '0', 'price_syp' => null]);
        $this->get('/api/v1/products/'.$product->slug)->assertOk()->assertJsonPath('data.pricing', ['source' => 'product', 'price_usd' => '0.00', 'price_syp' => null]);
        $this->assertSame(0, $product->variants()->withTrashed()->count());
    }

    public function test_mixed_then_all_archived_then_restored_variants_keep_ownership_and_current_currency_ranges(): void
    {
        $product = $this->product();
        $current = $this->variant($product, ['name' => 'Current selection', 'price_usd' => '20', 'price_syp' => null]);
        $url = '/api/v1/products/'.$product->slug;
        $this->get($url)->assertOk()->assertJsonPath('data.pricing.source', 'variants')->assertJsonPath('data.variants.0.id', $current->id)->assertJsonPath('data.pricing.range_usd', ['min' => '20.00', 'max' => '20.00']);
        $archived = $this->variant($product, ['name' => 'Archived sentinel', 'price_usd' => '0', 'price_syp' => '9000']);
        $this->archiveVariant($archived);
        $this->get($url)->assertOk()->assertJsonCount(1, 'data.variants')->assertJsonPath('data.variants.0.id', $current->id)
            ->assertJsonPath('data.pricing.range_usd', ['min' => '20.00', 'max' => '20.00'])
            ->assertJsonPath('data.pricing.range_syp', ['min' => null, 'max' => null])->assertDontSee('Archived sentinel');
        $this->archiveVariant($current);
        $this->get($url)->assertOk()->assertJsonPath('data.pricing.source', 'variants')->assertJsonPath('data.variants', [])
            ->assertJsonPath('data.pricing.range_usd', ['min' => null, 'max' => null])->assertJsonPath('data.pricing.range_syp', ['min' => null, 'max' => null]);
        try {
            $product->price_usd = '5';
            $product->save();
            $this->fail('Archived physical Variants must still reserve price ownership.');
        } catch (ConflictingPriceOwnership) {
            $this->assertNull($product->fresh()->price_usd);
        }
        $this->archiveVariant($archived, true);
        $this->get($url)->assertOk()->assertJsonPath('data.id', $product->id)->assertJsonPath('data.variants.0.id', $archived->id)
            ->assertJsonCount(1, 'data.variants')->assertJsonPath('data.pricing.source', 'variants')
            ->assertJsonPath('data.pricing.price_usd', null)->assertJsonPath('data.pricing.price_syp', null)
            ->assertJsonPath('data.pricing.range_usd', ['min' => '0.00', 'max' => '0.00'])
            ->assertJsonPath('data.pricing.range_syp', ['min' => '9000.00', 'max' => '9000.00']);
    }

    public function test_every_product_resource_path_including_favorites_uses_physical_ownership_without_archived_metadata(): void
    {
        $product = $this->product(['is_recommended' => true]);
        $concern = $this->concern();
        $product->concerns()->attach($concern);
        $variant = $this->variant($product, ['name' => 'Archived private sentinel', 'price_usd' => '20']);
        $customer = $this->customer();
        app(FavoriteOperations::class)->add($customer->id, $product->id);
        $this->actingAs($customer, 'customer')->withHeader('Origin', 'https://frontend.test');
        $this->archiveVariant($variant);
        $paths = ['/products', '/products/recommended', '/brands/'.$product->brand->slug.'/products', '/concerns/'.$concern->slug.'/products', '/products?q='.urlencode($product->name), '/products?brand='.$product->brand->slug.'&concern='.$concern->slug.'&recommended=true', '/favorites'];
        foreach ($paths as $path) {
            $response = $this->get('/api/v1'.$path)->assertOk()->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.id', $product->id)->assertJsonPath('data.0.pricing.source', 'variants')
                ->assertJsonPath('data.0.pricing.price_usd', null)->assertJsonPath('data.0.pricing.price_syp', null)
                ->assertJsonPath('data.0.pricing.range_usd', ['min' => null, 'max' => null])
                ->assertJsonPath('data.0.pricing.range_syp', ['min' => null, 'max' => null]);
            $row = $response->json('data.0');
            foreach (['has_physical_variants', 'variants_count', 'deleted_at', 'variants'] as $internal) {
                $this->assertArrayNotHasKey($internal, $row);
            }
            $response->assertDontSee('Archived private sentinel')->assertDontSee($variant->id);
        }
    }

    public function test_archived_cart_selection_is_retained_blocked_decreasable_removable_and_checkout_history_is_immutable(): void
    {
        $customer = $this->customer();
        $product = $this->product();
        $variant = $this->variant($product, ['price_usd' => '20', 'availability_status' => 'available']);
        $cart = app(CartOperations::class);
        $item = $cart->add($customer->id, $product->id, $variant->id, 2)['item'];
        $payload = ['operation_id' => (string) Str::uuid7(), 'currency' => 'USD', 'expected_cart_version' => '1', 'contact' => ['full_name' => 'Fixture Customer', 'phone' => '+963999999999', 'governorate' => 'دمشق', 'city' => 'دمشق', 'address' => 'عنوان تجريبي', 'notes' => null]];
        $checkout = app(SubmitPreliminaryCheckoutRequest::class);
        $submitted = $checkout->execute($customer->id, $payload);
        $history = [DB::table('checkout_intents')->get()->toJson(), DB::table('checkout_intent_items')->get()->toJson()];
        $this->archiveVariant($variant);
        $snapshot = app(CartSnapshot::class)->complete($customer->id);
        $this->assertSame($variant->id, DB::table('cart_items')->sole()->variant_id);
        $this->assertSame('blocked', $snapshot['items'][0]['evaluation']['state']);
        $this->assertSame(['selection_invalid'], $snapshot['items'][0]['evaluation']['reasons']);
        $this->assertNull($snapshot['items'][0]['pricing']);
        $this->assertNull($snapshot['totals']['USD']['estimated_total']);
        foreach ([null, $variant->id] as $selection) {
            try {
                $cart->add($customer->id, $product->id, $selection, 1);
                $this->fail('Neither direct nor archived Variant admission is valid.');
            } catch (ValidationException) {
                $this->assertDatabaseCount('cart_items', 1);
            }
        }
        try {
            $checkout->execute($customer->id, array_replace($payload, ['operation_id' => (string) Str::uuid7()]));
            $this->fail('Archived selection must not create a new request.');
        } catch (CartConflict $exception) {
            $this->assertSame('checkout_selection_blocked', $exception->reason);
        }
        $cart->update($customer->id, $item['id'], ['quantity' => 1, 'expected_version' => '1']);
        $this->assertSame(1, DB::table('cart_items')->sole()->quantity);
        $cart->remove($customer->id, $item['id']);
        $this->assertDatabaseCount('cart_items', 0);
        $replay = $checkout->execute($customer->id, $payload);
        $this->assertFalse($replay['created']);
        $this->assertSame($submitted['intent']->id, $replay['intent']->id);
        $this->assertSame($history, [DB::table('checkout_intents')->get()->toJson(), DB::table('checkout_intent_items')->get()->toJson()]);
    }

    public function test_ownership_projection_stays_in_bounded_listing_sql_without_hydrating_variants(): void
    {
        $customer = $this->customer();
        $counts = [];
        foreach ([2, 20] as $target) {
            while (Product::query()->count() < $target) {
                $product = $this->product();
                $this->archiveVariant($this->variant($product, ['price_usd' => '20']));
                app(FavoriteOperations::class)->add($customer->id, $product->id);
            }
            foreach (['public' => fn () => app(PublicCatalogReader::class)->products(new CatalogFilters(perPage: 48)), 'favorites' => fn () => app(FavoriteOperations::class)->listing($customer->id, 48)] as $type => $read) {
                DB::enableQueryLog();
                DB::flushQueryLog();
                try {
                    $records = $read();
                    $queries = DB::getQueryLog();
                    $counts[$target][$type] = count($queries);
                    foreach ($records as $record) {
                        $this->assertTrue($record->has_physical_variants);
                        $this->assertSame(0, $record->variants_count);
                        $this->assertFalse($record->relationLoaded('variants'));
                    }
                    $sql = collect($queries)->first(fn ($query) => str_contains($query['query'], 'has_physical_variants'));
                    $this->assertNotNull($sql);
                    $this->assertStringContainsString('exists(', $sql['query']);
                } finally {
                    DB::disableQueryLog();
                    DB::flushQueryLog();
                }
            }
        }
        $this->assertSame($counts[2], $counts[20]);
        fwrite(STDOUT, 'FIX_6C_001_QUERY_COUNTS '.json_encode($counts, JSON_THROW_ON_ERROR).PHP_EOL);
    }
}
