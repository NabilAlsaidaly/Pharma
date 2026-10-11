<?php

namespace Tests\Feature\Checkout;

use App\Domains\Cart\Application\CartEvaluator;
use App\Domains\Cart\Infrastructure\Persistence\Models\Cart;
use App\Domains\Cart\Infrastructure\Persistence\Models\CartItem;
use App\Domains\Catalog\Application\Ports\CatalogSelectionReader;
use App\Domains\Catalog\Infrastructure\Persistence\Models\Product;
use App\Domains\Identity\Application\Ports\GoogleOAuthClient;
use App\Domains\Identity\Domain\Enums\CustomerAccountStatus;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Fakes\FakeGoogleOAuthClient;
use Tests\PersonalCollectionsTestCase;

class CheckoutEvaluationTest extends PersonalCollectionsTestCase
{
    private const URL = '/api/v1/checkout/evaluations';

    private function evaluate(string $currency = 'USD', ?string $version = null)
    {
        return $this->postJson(self::URL, ['currency' => $currency,
            'expected_cart_version' => $version ?? Cart::query()->where('customer_id', $this->owner->id)->first()?->version ?? '0']);
    }

    private function assertNonpayable($response): void
    {
        $this->assertSame(['payable_total' => null, 'required_advance' => null, 'delivery_fee' => null,
            'quote_issued' => false, 'payment_instructions_available' => false, 'can_confirm_order' => false, 'reserves_inventory' => false], $response->json('data.commercial'));
    }

    public function test_no_cart_and_persistent_empty_cart_are_blocked_without_creation(): void
    {
        $r = $this->evaluate()->assertOk()->assertJsonPath('data.cart.id', null)->assertJsonPath('data.cart.version', '0')
            ->assertJsonPath('data.cart.item_count', 0)->assertJsonPath('data.selection_status', 'blocked')->assertJsonPath('data.blockers', ['empty_cart'])
            ->assertJsonPath('data.pricing.priced_subtotal', '0.00')->assertJsonPath('data.pricing.estimated_items_subtotal', '0.00');
        $this->assertNonpayable($r);
        $this->assertDatabaseCount('carts', 0);
        $cart = Cart::query()->create(['customer_id' => $this->owner->id]);
        $cart->forceFill(['version' => '9'])->save();
        $this->evaluate()->assertJsonPath('data.cart.id', $cart->id)->assertJsonPath('data.cart.version', '9')->assertJsonPath('data.items', []);
    }

    public function test_session_authentication_guest_bearer_suspension_and_public_catalog(): void
    {
        $this->app['auth']->forgetGuards();
        foreach ([[], ['Authorization' => 'Bearer 1|unsupported-token']] as $headers) {
            $this->postJson(self::URL, ['currency' => 'USD', 'expected_cart_version' => '0'], $headers)->assertUnauthorized()->assertJsonPath('error.code', 'UNAUTHENTICATED');
        }
        $this->getJson('/api/v1/products')->assertOk();
        $this->owner->update(['account_status' => CustomerAccountStatus::Suspended]);
        $this->actingAs($this->owner, 'customer');
        $this->evaluate()->assertForbidden();
        $this->assertDatabaseCount('carts', 0);
    }

    public function test_actual_google_session_owns_only_its_cart(): void
    {
        $other = $this->otherCustomer();
        $otherCart = Cart::query()->create(['customer_id' => $other->id]);
        $product = $this->product(['price_usd' => '7']);
        $otherCart->items()->create(['product_id' => $product->id, 'quantity' => 4]);
        $this->app->instance(GoogleOAuthClient::class, new FakeGoogleOAuthClient($this->identity()));
        $this->app['auth']->forgetGuards();
        $this->continueBrowserSession($this->get('/api/v1/auth/google/callback'));
        $this->evaluate()->assertOk()->assertJsonPath('data.cart.id', null)->assertJsonPath('data.items', []);
        $this->assertDatabaseCount('cart_items', 1);
    }

    public static function invalidBodies(): array
    {
        $valid = ['currency' => 'USD', 'expected_cart_version' => '0'];
        $cases = [['expected_cart_version' => '0'], ['currency' => 'USD']];
        foreach (['usd', 'syp', 'EUR', null, '', 1, [], (object) [], ' USD ', "USD\n"] as $v) {
            $cases[] = array_replace($valid, ['currency' => $v]);
        }
        foreach ([0, -1, 1.5, null, [], (object) [], '001', '-1', ' 0 ', '1e2', '18446744073709551616', '999999999999999999999', "0\n"] as $v) {
            $cases[] = array_replace($valid, ['expected_cart_version' => $v]);
        }
        foreach (['customer_id', 'cart_id', 'product_id', 'quantity', 'unit_price', 'price', 'subtotal', 'total', 'deposit', 'required_payment', 'payment_method', 'verified', 'is_paid', 'reservation', 'stock', 'status', 'role', 'staff_id'] as $field) {
            $cases[] = $valid + [$field => 'forged'];
        }

        return array_map(fn ($body) => [$body], $cases);
    }

    #[DataProvider('invalidBodies')]
    public function test_exact_wire_input_rejects_invalid_and_untrusted_fields(array $body): void
    {
        $r = $this->postJson(self::URL, $body)->assertUnprocessable()->assertJsonPath('error.code', 'VALIDATION_FAILED');
        $this->assertSame($r->headers->get('X-Request-ID'), $r->json('request_id'));
        $this->assertDatabaseCount('carts', 0);
    }

    public function test_query_fields_body_shape_and_maximum_version(): void
    {
        foreach (['customer_id=forged', 'currency=USD', 'expected_cart_version=0', 'unknown[x]=1'] as $query) {
            $this->postJson(self::URL.'?'.$query, ['currency' => 'USD', 'expected_cart_version' => '0'])->assertUnprocessable();
        }
        $this->postJson(self::URL, [])->assertUnprocessable();
        Cart::query()->create(['customer_id' => $this->owner->id])->forceFill(['version' => '18446744073709551615'])->save();
        $this->evaluate('USD', '18446744073709551615')->assertOk();
        $this->evaluate('USD', '0')->assertConflict()->assertJsonPath('error.fields.selection', ['stale_cart_version']);
    }

    public function test_actual_csrf_rejects_missing_token_and_accepts_correct_token(): void
    {
        $this->app->bind(PreventRequestForgery::class, fn ($app) => new class($app, $app['encrypter']) extends PreventRequestForgery
        {
            protected function runningUnitTests(): bool
            {
                return false;
            }
        });
        $this->evaluate()->assertStatus(419)->assertJsonPath('error.code', 'SESSION_EXPIRED');
        $this->postJson(self::URL, ['currency' => 'USD', 'expected_cart_version' => '0'], ['X-CSRF-TOKEN' => $this->app['session']->token()])->assertOk();
        $this->assertDatabaseCount('carts', 0);
    }

    public function test_body_limit_without_business_writes(): void
    {
        $this->postJson(self::URL, ['currency' => 'USD', 'expected_cart_version' => '0', 'padding' => str_repeat('x', 16384)])
            ->assertStatus(413)->assertJsonPath('error.code', 'PAYLOAD_TOO_LARGE');
        $this->assertDatabaseCount('carts', 0);
    }

    public function test_shared_post_limiter_and_customer_isolation(): void
    {
        config(['customer_collections.mutations_per_minute' => 1]);
        $this->evaluate()->assertOk();
        $this->postJson('/api/v1/favorites', ['product_id' => $this->product()->id])->assertStatus(429)->assertHeader('Retry-After');
        $this->actingAs($this->otherCustomer(), 'customer');
        $this->postJson(self::URL, ['currency' => 'USD', 'expected_cart_version' => '0'])->assertOk();
    }

    public function test_exact_simple_and_variant_prices_currency_independence_and_current_values(): void
    {
        $simple = $this->product(['price_usd' => '12.50', 'price_syp' => '50000']);
        $this->addItem($simple, null, 2);
        $parent = $this->product();
        $a = $this->variant($parent, ['price_usd' => '5', 'price_syp' => '20000']);
        $this->variant($parent, ['price_usd' => '99']);
        $this->addItem($parent, $a->id, 3);
        $r = $this->evaluate()->assertOk()->assertJsonPath('data.selection_status', 'valid')
            ->assertJsonPath('data.pricing.estimated_items_subtotal', '40.00')->assertJsonPath('data.pricing.complete', true);
        $this->assertNonpayable($r);
        $this->evaluate('SYP')->assertJsonPath('data.pricing.estimated_items_subtotal', '160000.00');
        $a->update(['price_usd' => '6']);
        $this->evaluate()->assertJsonPath('data.pricing.estimated_items_subtotal', '43.00');
    }

    public function test_hidden_product_and_brand_have_only_safe_owned_projection(): void
    {
        foreach (['product', 'brand'] as $target) {
            $p = $this->product(['price_usd' => '6']);
            $id = $this->addItem($p)['item']['id'];
            ($target === 'product' ? $p : $p->brand)->update(['status' => 'hidden']);
            $r = $this->evaluate()->assertOk()->assertJsonPath('data.selection_status', 'blocked');
            $item = collect($r->json('data.items'))->firstWhere('cart_item_id', $id);
            $this->assertSame(['cart_item_id' => $id, 'quantity' => 1, 'unit_price' => null, 'line_estimate' => null,
                'reasons' => ['selection_inaccessible'], 'requires_fulfillment_review' => false], $item);
            $this->assertStringNotContainsString($p->id, $r->getContent());
            $this->assertStringNotContainsString($p->name, $r->getContent());
        }
        $this->assertDatabaseCount('cart_items', 2);
        foreach (Product::query()->get() as $product) {
            $product->update(['status' => 'active']);
            $product->brand->update(['status' => 'active']);
        }
        $this->evaluate()->assertJsonPath('data.selection_status', 'valid')->assertJsonPath('data.pricing.estimated_items_subtotal', '12.00');
    }

    public function test_structural_changes_and_wrong_variant_are_blocked_without_repair(): void
    {
        $p = $this->product(['price_usd' => '2']);
        $id = $this->addItem($p)['item']['id'];
        $p->update(['price_usd' => null]);
        $v = $this->variant($p, ['price_usd' => '9']);
        $this->evaluate()->assertJsonPath('data.items.0.reasons', ['selection_invalid']);
        // Model-only malformed object verifies the shared ownership evaluator; real FK forbids its persistence.
        $reader = app(CatalogSelectionReader::class);
        $other = $this->product();
        $wrong = $this->variant($other, ['price_usd' => '99']);
        $item = app(CartEvaluator::class)->item(['id' => $id, 'quantity' => 1, 'variant_id' => $wrong->id], $reader->read([$p->id], [$wrong->id])[$p->id]);
        $this->assertSame(['selection_invalid'], $item['evaluation']['reasons']);
        $this->assertNull(CartItem::query()->findOrFail($id)->variant_id);
        $this->assertDatabaseHas('product_variants', ['id' => $v->id]);
    }

    public function test_partial_prices_precedence_and_on_request_review(): void
    {
        $valid = $this->product(['price_usd' => '1.25']);
        $this->addItem($valid, null, 3);
        $missing = $this->product(['price_syp' => '100']);
        $this->addItem($missing);
        $zero = $this->product(['price_usd' => '3']);
        $this->addItem($zero);
        $zero->update(['price_usd' => '0']);
        $unavailable = $this->product(['price_usd' => '4']);
        $this->addItem($unavailable);
        $unavailable->update(['availability_status' => 'unavailable', 'price_usd' => null]);
        $request = $this->product(['price_usd' => '2', 'availability_status' => 'on_request']);
        $this->addItem($request, null, 2);
        $r = $this->evaluate()->assertJsonPath('data.selection_status', 'blocked')->assertJsonPath('data.pricing.priced_subtotal', '7.75')
            ->assertJsonPath('data.pricing.estimated_items_subtotal', null)->assertJsonPath('data.pricing.excluded_item_count', 3)
            ->assertJsonPath('data.pricing.exclusion_reasons.missing_price', 1)->assertJsonPath('data.pricing.exclusion_reasons.zero_price', 1)
            ->assertJsonPath('data.pricing.exclusion_reasons.selection_unavailable', 1)->assertJsonPath('data.requires_fulfillment_review_count', 1);
        $this->assertNonpayable($r);
        $this->evaluate('SYP')->assertJsonPath('data.pricing.priced_subtotal', '100.00')
            ->assertJsonPath('data.pricing.exclusion_reasons.missing_price', 3)->assertJsonPath('data.pricing.estimated_items_subtotal', null);
    }

    public function test_parent_variant_availability_and_review_without_payable_instructions(): void
    {
        $p = $this->product(['availability_status' => 'on_request']);
        $v = $this->variant($p, ['price_usd' => '5']);
        $this->addItem($p, $v->id, 2);
        $r = $this->evaluate()->assertJsonPath('data.selection_status', 'requires_review')->assertJsonPath('data.pricing.estimated_items_subtotal', '10.00');
        $this->assertNonpayable($r);
        $v->update(['availability_status' => 'unavailable']);
        $this->evaluate()->assertJsonPath('data.items.0.reasons', ['selection_unavailable']);
        $p->update(['availability_status' => 'available']);
        $v->update(['availability_status' => 'on_request']);
        $this->evaluate()->assertJsonPath('data.selection_status', 'requires_review')->assertJsonPath('data.items.0.requires_fulfillment_review', true);
        $p->update(['availability_status' => 'unavailable']);
        $v->update(['availability_status' => 'available']);
        $this->evaluate()->assertJsonPath('data.items.0.reasons', ['selection_unavailable']);
    }

    public function test_large_decimal_estimates_are_exact(): void
    {
        $p = $this->product(['price_usd' => '9999999999999999.99']);
        $this->addItem($p, null, 2147483647);
        $this->evaluate()->assertJsonPath('data.pricing.estimated_items_subtotal', '21474836469999999978525163.53');
    }

    public function test_full_cart_query_counts_order_cap_and_existing_pagination(): void
    {
        config(['customer_collections.mutations_per_minute' => 1000]);
        $counts = [];
        for ($i = 1; $i <= 201; $i++) {
            $p = $this->product(['price_usd' => '2']);
            if ($i <= 200) {
                $this->addItem($p);
            } else {
                Cart::query()->sole()->items()->create(['product_id' => $p->id, 'quantity' => 1]);
            }
            if (in_array($i, [2, 50, 200], true)) {
                DB::connection()->enableQueryLog();
                DB::connection()->flushQueryLog();
                $r = $this->evaluate('USD', (string) $i)->assertOk()->assertJsonCount($i, 'data.items')->assertJsonPath('data.pricing.estimated_items_subtotal', $i * 2 .'.00');
                $queries = DB::getQueryLog();
                $counts[$i] = count($queries);
                foreach ($queries as $query) {
                    $this->assertStringNotContainsString('for update', strtolower($query['query']));
                }
                DB::connection()->disableQueryLog();
                $this->assertSame($r->json('data.items'), $this->evaluate()->json('data.items'));
            }
        }
        $this->assertSame($counts[2], $counts[50]);
        $this->assertSame($counts[50], $counts[200]);
        $this->assertLessThanOrEqual(8, $counts[200]);
        $this->evaluate()->assertConflict()->assertJsonPath('error.fields.selection', ['cart_evaluation_capacity_exceeded']);
        $this->getJson('/api/v1/cart?per_page=48')->assertOk()->assertJsonCount(48, 'data.items')->assertJsonPath('data.item_count', 201);
        $plan = DB::select('EXPLAIN FORMAT=TRADITIONAL SELECT * FROM cart_items WHERE cart_id = ? ORDER BY created_at DESC, id ASC LIMIT 201', [Cart::query()->sole()->id]);
        $this->assertNotNull($plan[0]->key);
        fwrite(STDOUT, 'CHECKOUT_QUERY_COUNTS '.json_encode($counts).' CHECKOUT_EXPLAIN '.json_encode($plan).PHP_EOL);
    }

    public function test_all_business_rows_and_schema_preserved_on_success_blocked_conflict_and_invalid_input(): void
    {
        $p = $this->product(['price_usd' => '2']);
        $this->addItem($p);
        $p->update(['status' => 'hidden']);
        $snapshot = function (): array {
            $out = [];
            foreach (DB::select('SHOW TABLES') as $table) {
                $name = array_values((array) $table)[0];
                $out[$name] = [DB::table($name)->get()->toArray(), DB::select('SHOW CREATE TABLE `'.$name.'`')];
            }

            return $out;
        };
        $before = $snapshot();
        $this->evaluate()->assertOk();
        $this->evaluate('SYP')->assertOk();
        $this->evaluate('USD', '0')->assertConflict();
        $this->postJson(self::URL, ['currency' => 'usd', 'expected_cart_version' => '1'])->assertUnprocessable();
        $this->assertEquals($before, $snapshot());
        $p->update(['status' => 'active']);
        $before = $snapshot();
        $this->evaluate()->assertJsonPath('data.selection_status', 'valid');
        $this->assertEquals($before, $snapshot());
        $routes = collect(app('router')->getRoutes())->filter(fn ($r) => str_starts_with($r->uri(), 'api/v1/checkout'));
        $this->assertCount(1, $routes);
        $this->assertSame(['POST'], $routes->first()->methods());
    }
}
