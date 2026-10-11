<?php

namespace Tests\Feature\Collections;

use App\Domains\Cart\Infrastructure\Persistence\Models\Cart;
use App\Domains\Cart\Infrastructure\Persistence\Models\CartItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;
use Tests\PersonalCollectionsTestCase;

class CollectionsSchemaTest extends PersonalCollectionsTestCase
{
    public function test_cart_and_items_are_uuidv7_with_one_cart_per_customer(): void
    {
        $p = $this->product(['price_usd' => '1']);
        $r = $this->addItem($p);
        $cart = Cart::query()->sole();
        $item = CartItem::query()->sole();
        foreach ([$cart, $item] as $model) {
            $this->assertSame(7, Uuid::fromString($model->id)->getVersion());
            $this->assertFalse($model->getIncrementing());
        }
        $this->assertConstraint(fn () => DB::table('carts')->insert($this->rawRow('carts', ['customer_id' => $this->owner->id])), 1062);
        $this->assertSame('1', $r['cart_version']);
    }

    public function test_favorite_pair_unique_and_foreign_keys_restrict_orphans_and_deletion(): void
    {
        $p = $this->product();
        $row = ['customer_id' => $this->owner->id, 'product_id' => $p->id, 'created_at' => now()];
        DB::table('favorites')->insert($row);
        $this->assertConstraint(fn () => DB::table('favorites')->insert($row), 1062);
        $this->assertConstraint(fn () => DB::table('favorites')->insert(array_replace($row, ['product_id' => (string) Str::uuid7()])), 1452);
        $this->assertConstraint(fn () => DB::table('favorites')->insert(array_replace($row, ['customer_id' => (string) Str::uuid7()])), 1452);
        $this->assertConstraint(fn () => $p->delete(), 1451);
    }

    public function test_generated_key_rejects_duplicate_null_and_real_variants_accepts_distinct_selections(): void
    {
        $simple = $this->product(['price_usd' => '1']);
        $this->addItem($simple);
        $cart = Cart::query()->sole();
        $row = $this->rawRow('cart_items', ['cart_id' => $cart->id, 'product_id' => $simple->id, 'variant_id' => null, 'quantity' => 1]);
        $this->assertConstraint(fn () => DB::table('cart_items')->insert($row), 1062);
        $p = $this->product();
        $a = $this->variant($p, ['price_usd' => '1']);
        $b = $this->variant($p, ['price_usd' => '2']);
        $this->addItem($p, $a->id);
        $this->addItem($p, $b->id);
        $this->assertConstraint(fn () => DB::table('cart_items')->insert(array_replace($row, ['id' => (string) Str::uuid7(), 'product_id' => $p->id, 'variant_id' => $a->id])), 1062);
        $this->assertSame(3, CartItem::query()->count());
        $this->assertSame('', CartItem::query()->where('product_id', $simple->id)->sole()->getRawOriginal('selection_key'));
    }

    public function test_composite_fk_rejects_wrong_product_and_reparenting_and_allows_null_variant(): void
    {
        $p = $this->product();
        $v = $this->variant($p, ['price_usd' => '1']);
        $this->addItem($p, $v->id);
        $other = $this->product();
        $cart = Cart::query()->sole();
        $this->assertConstraint(fn () => DB::table('cart_items')->insert($this->rawRow('cart_items', ['cart_id' => $cart->id, 'product_id' => $other->id, 'variant_id' => $v->id, 'quantity' => 1])), 1452);
        $this->assertConstraint(fn () => DB::table('product_variants')->where('id', $v->id)->update(['product_id' => $other->id]), 1451);
        $this->assertConstraint(fn () => $v->delete(), 1451);
        DB::table('cart_items')->insert($this->rawRow('cart_items', ['cart_id' => $cart->id, 'product_id' => $other->id, 'variant_id' => null, 'quantity' => 1]));
        $this->assertSame(2, CartItem::query()->count());
    }

    public function test_quantity_checks_orphans_and_catalog_identity_schema_preserved(): void
    {
        $p = $this->product(['price_usd' => '1']);
        $this->addItem($p);
        $cart = Cart::query()->sole();
        CartItem::query()->delete();
        foreach ([0, -1] as $quantity) {
            $this->assertConstraint(fn () => DB::table('cart_items')->insert($this->rawRow('cart_items', ['cart_id' => $cart->id, 'product_id' => $p->id, 'quantity' => $quantity])), 3819);
        }
        $this->assertConstraint(fn () => DB::table('cart_items')->insert($this->rawRow('cart_items', ['cart_id' => (string) Str::uuid7(), 'product_id' => $p->id, 'quantity' => 1])), 1452);
        $this->assertCount(9, DB::select('SHOW COLUMNS FROM customers'));
        $tables = array_map(fn ($r) => array_values((array) $r)[0], DB::select('SHOW TABLES'));
        foreach (['orders', 'payments', 'reservations', 'stock_movements'] as $table) {
            $this->assertNotContains($table, $tables);
        }
        $columns = array_column(DB::select('SHOW COLUMNS FROM cart_items'), 'Field');
        $this->assertNotContains('price_usd', $columns);
        $this->assertNotContains('currency', $columns);
    }
}
