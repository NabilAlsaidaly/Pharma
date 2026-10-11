<?php

namespace Tests\Feature\Collections;

use App\Domains\Cart\Domain\Rules\CartQuantity;
use App\Domains\Cart\Infrastructure\Persistence\Models\Cart;
use App\Domains\Cart\Infrastructure\Persistence\Models\CartItem;
use App\Domains\Identity\Domain\Enums\CustomerAccountStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\PersonalCollectionsTestCase;

class CartApiTest extends PersonalCollectionsTestCase
{
    public function test_default_capacity_accepts_two_hundred_selections_and_rejects_the_next(): void
    {
        $this->assertSame(200, config('customer_collections.cart_selection_limit'));
        $product = $this->product();
        $cart = Cart::query()->create(['customer_id' => $this->owner->id]);
        $last = null;
        for ($i = 0; $i < 200; $i++) {
            $variant = $this->variant($product, ['price_usd' => '1']);
            $cart->items()->create(['product_id' => $product->id, 'variant_id' => $variant->id, 'quantity' => 1]);
            $last = $variant;
        }
        $next = $this->variant($product, ['price_usd' => '1']);
        $this->getJson('/api/v1/cart?per_page=48')->assertJsonCount(48, 'data.items')
            ->assertJsonPath('data.item_count', 200)->assertJsonPath('data.totals.USD.estimated_total', '200.00');
        $this->postJson('/api/v1/cart/items', ['product_id' => $product->id, 'variant_id' => $next->id, 'quantity' => 1])->assertConflict();
        $this->postJson('/api/v1/cart/items', ['product_id' => $product->id, 'variant_id' => $last->id, 'quantity' => 1])->assertOk();
        $this->assertSame(200, CartItem::query()->count());
    }

    public function test_version_overflow_rolls_back_quantity_and_delete_without_wrapping(): void
    {
        $id = $this->addItem($this->product(['price_usd' => '1']))['item']['id'];
        $version = '18446744073709551615';
        Cart::query()->sole()->forceFill(['version' => $version])->save();
        $this->patchJson('/api/v1/cart/items/'.$id, ['expected_version' => $version, 'quantity' => 2])->assertConflict();
        $this->deleteJson('/api/v1/cart/items/'.$id)->assertConflict();
        $this->patchJson('/api/v1/cart/items/'.$id, ['expected_version' => '18446744073709551616', 'quantity' => 2])->assertUnprocessable();
        $this->assertSame(1, CartItem::query()->sole()->quantity);
        $this->assertSame($version, Cart::query()->sole()->version);
    }

    public function test_guests_and_suspended_accounts_denied_all_cart_operations(): void
    {
        $this->app['auth']->forgetGuards();
        foreach (['GET /api/v1/cart', 'POST /api/v1/cart/items', 'PATCH /api/v1/cart/items/'.Str::uuid7(), 'DELETE /api/v1/cart/items/'.Str::uuid7()] as $route) {
            [$m,$u] = explode(' ', $route, 2);
            $this->json($m, $u)->assertUnauthorized();
        }
        $this->owner->update(['account_status' => CustomerAccountStatus::Suspended]);
        $this->actingAs($this->owner, 'customer');
        $this->getJson('/api/v1/cart')->assertForbidden();
    }

    public function test_empty_read_does_not_create_cart_and_has_explicit_zero_totals(): void
    {
        $this->getJson('/api/v1/cart')->assertOk()->assertJsonPath('data.id', null)->assertJsonPath('data.item_count', 0)->assertJsonPath('data.totals.USD.estimated_total', '0.00')->assertJsonPath('data.totals.SYP.complete', true);
        $this->assertSame(0, Cart::query()->count());
    }

    public function test_lazy_cart_simple_add_and_intentional_repeat_increment_without_fake_variant(): void
    {
        $p = $this->product(['price_usd' => '12.50']);
        $this->postJson('/api/v1/cart/items', ['product_id' => $p->id, 'quantity' => 2])->assertCreated()->assertJsonPath('data.item.variant', null)->assertJsonPath('data.cart_version', '1');
        $this->postJson('/api/v1/cart/items', ['product_id' => $p->id, 'quantity' => 3])->assertOk()->assertJsonPath('data.item.quantity', 5)->assertJsonPath('data.cart_version', '2');
        $this->assertSame(1, Cart::query()->count());
        $this->assertSame(1, CartItem::query()->count());
        $this->assertSame(0, DB::table('product_variants')->count());
    }

    public function test_variants_require_explicit_owning_selection_and_no_default(): void
    {
        $p = $this->product();
        $v = $this->variant($p, ['price_usd' => '2']);
        $other = $this->variant($this->product(), ['price_usd' => '3']);
        foreach ([null, $other->id, (string) Str::uuid7()] as $id) {
            $this->postJson('/api/v1/cart/items', ['product_id' => $p->id, 'variant_id' => $id, 'quantity' => 1])->assertUnprocessable();
        }
        $this->postJson('/api/v1/cart/items', ['product_id' => $p->id, 'variant_id' => $v->id, 'quantity' => 1])->assertCreated()->assertJsonPath('data.item.pricing.USD.unit_price', '2.00');
        $simple = $this->product(['price_usd' => '5']);
        $this->postJson('/api/v1/cart/items', ['product_id' => $simple->id, 'variant_id' => $v->id, 'quantity' => 1])->assertUnprocessable();
    }

    public static function invalidQuantities(): array
    {
        return [[0], [-1], [1.5], ['2'], ['invalid'], [2147483648], [true], [null], [1e30]];
    }

    #[DataProvider('invalidQuantities')]
    public function test_invalid_quantities_rejected_without_lazy_cart_creation(mixed $quantity): void
    {
        $p = $this->product(['price_usd' => '1']);
        $this->postJson('/api/v1/cart/items', ['product_id' => $p->id, 'quantity' => $quantity])->assertUnprocessable();
        $this->assertSame(0, Cart::query()->count());
    }

    public function test_absolute_quantity_patch_expected_version_and_stale_replay(): void
    {
        $p = $this->product(['price_usd' => '1']);
        $r = $this->addItem($p);
        $id = $r['item']['id'];
        $this->patchJson('/api/v1/cart/items/'.$id, ['quantity' => 4])->assertUnprocessable();
        $this->patchJson('/api/v1/cart/items/'.$id, ['quantity' => 4, 'expected_version' => '1'])->assertOk()->assertJsonPath('data.cart_version', '2');
        $this->patchJson('/api/v1/cart/items/'.$id, ['quantity' => 7, 'expected_version' => '1'])->assertConflict();
        $this->assertSame(4, CartItem::query()->sole()->quantity);
        $this->patchJson('/api/v1/cart/items/'.$id, ['quantity' => 4, 'expected_version' => '2'])->assertJsonPath('data.cart_version', '2');
        $this->patchJson('/api/v1/cart/items/'.$id, ['quantity' => 2, 'variant_id' => null, 'expected_version' => '2'])->assertUnprocessable();
    }

    public function test_variant_change_preserves_quantity_and_collision_merges_destination_atomically(): void
    {
        $p = $this->product();
        $a = $this->variant($p, ['price_usd' => '1']);
        $b = $this->variant($p, ['price_usd' => '3']);
        $c = $this->variant($p, ['price_usd' => '4']);
        $r = $this->addItem($p, $a->id, 2);
        $id = $r['item']['id'];
        $this->patchJson('/api/v1/cart/items/'.$id, ['variant_id' => $c->id, 'expected_version' => '1'])->assertOk()->assertJsonPath('data.item.quantity', 2)->assertJsonPath('data.item.id', $id);
        $dest = $this->addItem($p, $b->id, 3)['item']['id'];
        $this->patchJson('/api/v1/cart/items/'.$id, ['variant_id' => $b->id, 'expected_version' => '3'])->assertOk()->assertJsonPath('data.merged', true)->assertJsonPath('data.removed_item_id', $id)->assertJsonPath('data.item.id', $dest)->assertJsonPath('data.item.quantity', 5)->assertJsonPath('data.item.pricing.USD.unit_price', '3.00');
        $this->assertSame(1, CartItem::query()->count());
    }

    public function test_invalid_variant_target_rolls_back_source_and_version(): void
    {
        $p = $this->product();
        $a = $this->variant($p, ['price_usd' => '1']);
        $id = $this->addItem($p, $a->id)['item']['id'];
        $foreign = $this->variant($this->product(), ['price_usd' => '2']);
        foreach ([$foreign->id, (string) Str::uuid7()] as $variant) {
            $this->patchJson('/api/v1/cart/items/'.$id, ['variant_id' => $variant, 'expected_version' => '1'])->assertUnprocessable();
        }
        $this->assertSame($a->id, CartItem::query()->sole()->variant_id);
        $this->assertSame('1', Cart::query()->sole()->version);
    }

    public function test_overflow_add_and_merge_roll_back_every_change(): void
    {
        $p = $this->product();
        $a = $this->variant($p, ['price_usd' => '1']);
        $b = $this->variant($p, ['price_usd' => '1']);
        $first = $this->addItem($p, $a->id, CartQuantity::MAX)['item']['id'];
        $source = $this->addItem($p, $b->id)['item']['id'];
        $this->postJson('/api/v1/cart/items', ['product_id' => $p->id, 'variant_id' => $a->id, 'quantity' => 1])->assertUnprocessable();
        $this->patchJson('/api/v1/cart/items/'.$source, ['variant_id' => $a->id, 'expected_version' => '2'])->assertUnprocessable();
        $this->assertSame(2, CartItem::query()->count());
        $this->assertSame('2', Cart::query()->sole()->version);
        $this->assertSame(CartQuantity::MAX, CartItem::query()->findOrFail($first)->quantity);
    }

    public function test_missing_zero_and_unavailable_rejected_without_cart_creation(): void
    {
        foreach ([[], ['price_usd' => '0'], ['price_usd' => '1', 'availability_status' => 'unavailable']] as $values) {
            $p = $this->product($values);
            $this->postJson('/api/v1/cart/items', ['product_id' => $p->id, 'quantity' => 1])->assertConflict();
        }
        $this->assertSame(0, Cart::query()->count());
    }

    public function test_on_request_allowed_flagged_and_estimated_without_buying_promise(): void
    {
        $p = $this->product(['price_syp' => '5', 'availability_status' => 'on_request']);
        $this->addItem($p, null, 2);
        $response = $this->getJson('/api/v1/cart')->assertJsonPath('data.requires_request_count', 1)->assertJsonPath('data.items.0.evaluation.requires_request', true)->assertJsonPath('data.totals.SYP.estimated_total', '10.00')->assertJsonPath('data.totals.USD.estimated_total', null);
        $this->assertStringNotContainsString('is_purchasable', $response->getContent());
    }

    public function test_current_prices_no_conversion_no_snapshot_and_whole_cart_totals_beyond_page(): void
    {
        $p = $this->product(['price_usd' => '2.50']);
        $this->addItem($p, null, 2);
        $q = $this->product(['price_usd' => '3.25', 'price_syp' => '4']);
        $this->addItem($q);
        $p->update(['price_usd' => '4.50']);
        $this->getJson('/api/v1/cart?per_page=1')->assertJsonCount(1, 'data.items')->assertJsonPath('data.item_count', 2)->assertJsonPath('data.totals.USD.estimated_total', '12.25')->assertJsonPath('data.totals.SYP.estimated_total', null)->assertJsonPath('data.totals.SYP.priced_subtotal', '4.00')->assertJsonPath('data.totals.SYP.exclusion_reasons.missing_price', 1);
        $this->assertSame('2', Cart::query()->sole()->version);
    }

    public function test_max_decimal_price_times_max_quantity_is_exact_without_native_integer_overflow(): void
    {
        $p = $this->product(['price_usd' => '9999999999999999.99']);
        $this->addItem($p, null, CartQuantity::MAX);
        $this->getJson('/api/v1/cart')->assertJsonPath('data.totals.USD.estimated_total', '21474836469999999978525163.53');
    }

    public function test_retained_missing_zero_unavailable_items_allow_reduction_not_increase(): void
    {
        foreach ([['price_usd' => null], ['price_usd' => '0'], ['availability_status' => 'unavailable']] as $changes) {
            $p = $this->product(['price_usd' => '1']);
            $id = $this->addItem($p, null, 2)['item']['id'];
            $p->update($changes);
            $version = Cart::query()->sole()->version;
            $this->patchJson('/api/v1/cart/items/'.$id, ['quantity' => 3, 'expected_version' => $version])->assertConflict();
            $this->patchJson('/api/v1/cart/items/'.$id, ['quantity' => 1, 'expected_version' => $version])->assertOk();
        }
        $this->getJson('/api/v1/cart')->assertJsonPath('data.item_count', 3)->assertJsonPath('data.totals.USD.estimated_total', null);
    }

    public function test_hidden_product_and_brand_placeholders_redact_every_catalog_field_and_reactivate(): void
    {
        foreach (['product', 'brand'] as $hidden) {
            $brand = $this->brand();
            $p = $this->product(['price_usd' => '1'], $brand);
            $id = $this->addItem($p, null, 2)['item']['id'];
            ($hidden === 'product' ? $p : $brand)->update(['status' => 'hidden']);
            $version = Cart::query()->sole()->version;
            $response = $this->getJson('/api/v1/cart')->assertJsonPath('data.totals.USD.complete', false);
            $entry = array_values(array_filter($response->json('data.items'), fn ($i) => $i['id'] === $id))[0];
            $this->assertNull($entry['product']);
            $this->assertNull($entry['variant']);
            $this->assertNull($entry['pricing']);
            $this->assertSame(['selection_inaccessible'], $entry['evaluation']['reasons']);
            $this->assertStringNotContainsString($p->id, json_encode($entry));
            $this->patchJson('/api/v1/cart/items/'.$id, ['quantity' => 3, 'expected_version' => $version])->assertConflict();
            $this->patchJson('/api/v1/cart/items/'.$id, ['variant_id' => null, 'expected_version' => $version])->assertConflict();
            $this->patchJson('/api/v1/cart/items/'.$id, ['quantity' => 1, 'expected_version' => $version])->assertOk();
            ($hidden === 'product' ? $p : $brand)->update(['status' => 'active']);
        }
        $this->getJson('/api/v1/cart')->assertJsonPath('data.totals.USD.estimated_total', '2.00');
    }

    public function test_effective_variant_availability_uses_existing_v1_and_price_owner(): void
    {
        $p = $this->product(['availability_status' => 'on_request']);
        $v = $this->variant($p, ['price_usd' => '3']);
        $this->addItem($p, $v->id);
        $v->update(['availability_status' => 'unavailable']);
        $this->getJson('/api/v1/cart')->assertJsonPath('data.items.0.evaluation.availability', 'unavailable')->assertJsonPath('data.items.0.pricing.USD.unit_price', '3.00')->assertJsonPath('data.totals.USD.exclusion_reasons.selection_unavailable', 1);
    }

    public function test_retained_simple_item_becoming_variant_product_is_invalid_not_default_selected(): void
    {
        $p = $this->product(['price_usd' => '1']);
        $this->addItem($p);
        $p->update(['price_usd' => null]);
        $this->variant($p, ['price_usd' => '2']);
        $this->getJson('/api/v1/cart')->assertJsonPath('data.items.0.evaluation.reasons.0', 'selection_invalid')->assertJsonPath('data.totals.USD.estimated_total', null);
    }

    public function test_idor_stolen_uuid_no_reads_edits_or_deletes_other_customers_and_forged_prices_rejected(): void
    {
        $p = $this->product(['price_usd' => '1']);
        $id = $this->addItem($p)['item']['id'];
        $other = $this->otherCustomer();
        $this->actingAs($other, 'customer');
        $this->getJson('/api/v1/cart')->assertJsonPath('data.item_count', 0);
        foreach ([$id, (string) Str::uuid7()] as $target) {
            $this->patchJson('/api/v1/cart/items/'.$target, ['quantity' => 4, 'expected_version' => '1'])->assertNotFound();
            $this->deleteJson('/api/v1/cart/items/'.$target)->assertOk();
        }
        foreach (['customer_id', 'cart_id', 'price', 'unit_price', 'total', 'is_purchasable', 'stock'] as $key) {
            $this->postJson('/api/v1/cart/items', ['product_id' => $p->id, 'quantity' => 1, $key => 'forged'])->assertUnprocessable();
        }
        $this->assertSame(1, CartItem::query()->sole()->quantity);
    }

    public function test_delete_idempotent_no_get_cleanup_and_no_inventory_payment_order_schema(): void
    {
        $p = $this->product(['price_usd' => '1']);
        $id = $this->addItem($p)['item']['id'];
        $this->deleteJson('/api/v1/cart/items/'.$id)->assertOk()->assertJsonPath('data.cart_version', '2');
        $this->deleteJson('/api/v1/cart/items/'.$id)->assertJsonPath('data.cart_version', '2');
        $this->getJson('/api/v1/cart')->assertJsonPath('data.item_count', 0);
        $this->assertSame(1, Cart::query()->count());
        foreach (['orders', 'payments', 'reservations', 'stock_movements'] as $t) {
            $this->assertFalse(DB::getSchemaBuilder()->hasTable($t));
        }
    }

    public function test_operational_selection_cap_and_pagination_bounds(): void
    {
        $p = $this->product(['price_usd' => '1']);
        $this->addItem($p);
        config(['customer_collections.cart_selection_limit' => 1]);
        $q = $this->product(['price_usd' => '2']);
        $this->postJson('/api/v1/cart/items', ['product_id' => $q->id, 'quantity' => 1])->assertConflict();
        $this->postJson('/api/v1/cart/items', ['product_id' => $p->id, 'quantity' => 1])->assertOk();
        $this->getJson('/api/v1/cart?per_page=49')->assertUnprocessable();
        $this->getJson('/api/v1/cart?page=100')->assertJsonCount(0, 'data.items')->assertJsonPath('data.totals.USD.estimated_total', '2.00');
    }
}
