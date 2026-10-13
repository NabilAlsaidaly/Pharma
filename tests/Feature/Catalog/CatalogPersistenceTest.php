<?php

namespace Tests\Feature\Catalog;

use App\Domains\Catalog\Domain\Enums\AvailabilityStatus;
use App\Domains\Catalog\Domain\Exceptions\ConflictingPriceOwnership;
use App\Domains\Catalog\Infrastructure\Persistence\Models\Product;
use App\Domains\Catalog\Infrastructure\Persistence\Models\ProductImage;
use App\Domains\Catalog\Infrastructure\Persistence\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Ramsey\Uuid\Uuid;
use Tests\CatalogTestCase;

class CatalogPersistenceTest extends CatalogTestCase
{
    public function test_all_five_entity_identifiers_are_uuidv7_and_relations_have_correct_owners(): void
    {
        $brand = $this->brand();
        $product = $this->product([], $brand);
        $variant = $this->variant($product);
        $image = $this->image($product);
        $concern = $this->concern();
        $product->concerns()->attach($concern);
        foreach ([$brand, $product, $variant, $image, $concern] as $model) {
            $this->assertSame(7, Uuid::fromString($model->id)->getVersion());
            $this->assertFalse($model->getIncrementing());
            $this->assertSame('string', $model->getKeyType());
        }
        $this->assertSame($brand->id, $product->brand->id);
        $this->assertSame($product->id, $variant->product->id);
        $this->assertSame($product->id, $image->product->id);
        $this->assertSame($product->id, $concern->products->sole()->id);
    }

    public function test_slug_uniqueness_is_global_for_each_entity_type(): void
    {
        $brand = $this->brand(['slug' => 'unique']);
        $product = $this->product(['slug' => 'unique'], $brand);
        $concern = $this->concern(['slug' => 'unique']);
        foreach ([$brand, $product, $concern] as $row) {
            $data = $row->getRawOriginal();
            $data['id'] = (string) Str::uuid7();
            $this->assertConstraint(fn () => DB::table($row->getTable())->insert($data), 1062);
        }
    }

    public function test_real_foreign_keys_reject_orphan_products_variants_images_and_pivot_sides(): void
    {
        $brand = $this->brand();
        $product = $this->product([], $brand);
        $concern = $this->concern();
        $missing = (string) Str::uuid7();
        $this->assertConstraint(fn () => DB::table('products')->insert($this->rawRow('products', ['brand_id' => $missing, 'name' => 'x', 'slug' => 'orphan'])), 1452);
        $this->assertConstraint(fn () => DB::table('product_variants')->insert($this->rawRow('product_variants', ['product_id' => $missing, 'name' => 'x'])), 1452);
        $this->assertConstraint(fn () => DB::table('product_images')->insert($this->rawRow('product_images', ['product_id' => $missing, 'path' => 'x.png'])), 1452);
        $this->assertConstraint(fn () => DB::table('concern_product')->insert(['product_id' => $missing, 'concern_id' => $concern->id]), 1452);
        $this->assertConstraint(fn () => DB::table('concern_product')->insert(['product_id' => $product->id, 'concern_id' => $missing]), 1452);
    }

    public function test_pivot_pair_is_unique_and_restrictive_deletion_preserves_dependents(): void
    {
        $brand = $this->brand();
        $p = $this->product([], $brand);
        $c = $this->concern();
        $p->concerns()->attach($c);
        $v = $this->variant($p);
        $i = $this->image($p);
        $this->assertConstraint(fn () => $p->concerns()->attach($c), 1062);
        foreach ([$brand, $p, $c] as $model) {
            $this->assertConstraint(fn () => $model->delete(), 1451);
        }
        $this->assertDatabaseHas('product_variants', ['id' => $v->id]);
        $this->assertDatabaseHas('product_images', ['id' => $i->id]);
    }

    public function test_required_fields_reject_null_and_approved_nullable_fields_allow_null(): void
    {
        $p = $this->product();
        $this->assertNull($p->price_usd);
        $this->assertNull($p->price_syp);
        $this->assertNull($p->short_description);
        $this->assertNull($this->brand()->logo_path);
        foreach (['brands' => ['name', 'slug'], 'products' => ['name', 'slug', 'brand_id'], 'product_variants' => ['name', 'product_id'], 'product_images' => ['path', 'product_id'], 'concerns' => ['name', 'slug']] as $table => $fields) {
            $model = match ($table) {
                'brands' => $this->brand(),'products' => $this->product(),'product_variants' => $this->variant($p),'product_images' => $this->image($p),default => $this->concern()
            };
            foreach ($fields as $field) {
                $this->assertConstraint(fn () => DB::table($table)->where('id', $model->id)->update([$field => null]), 1048);
            }
        }
    }

    public function test_decimal_precision_optional_prices_and_zero_are_preserved_without_conversion(): void
    {
        $p = $this->product(['price_usd' => '9999999999999999.99']);
        $this->assertSame('9999999999999999.99', $p->fresh()->price_usd);
        $this->assertNull($p->fresh()->price_syp);
        $p->update(['price_usd' => null, 'price_syp' => '0.00']);
        $this->assertNull($p->fresh()->price_usd);
        $this->assertSame('0.00', $p->fresh()->price_syp);
        $other = $this->product();
        $v = $this->variant($other, ['price_syp' => '125000.25']);
        $this->assertSame('125000.25', $v->fresh()->price_syp);
        $this->assertNull($v->fresh()->price_usd);
    }

    public function test_negative_prices_and_invalid_states_are_rejected_by_database_checks(): void
    {
        $p = $this->product();
        $v = $this->variant($p);
        foreach ([$p, $v] as $model) {
            foreach (['price_usd', 'price_syp'] as $field) {
                $this->assertConstraint(fn () => DB::table($model->getTable())->where('id', $model->id)->update([$field => '-0.01']), 3819);
            }
            $this->assertConstraint(fn () => DB::table($model->getTable())->where('id', $model->id)->update(['availability_status' => 'reserved']), 3819);
        }
        foreach ([$this->brand(), $p, $this->concern()] as $model) {
            $this->assertConstraint(fn () => DB::table($model->getTable())->where('id', $model->id)->update(['status' => 'draft']), 3819);
        }
    }

    public function test_price_mutators_reject_floats_negatives_and_precision_loss(): void
    {
        foreach ([-1, '-0.01', 0.1, '1.001', '99999999999999999', '1e3'] as $value) {
            foreach ([new Product, new ProductVariant] as $model) {
                try {
                    $model->price_usd = $value;
                    $this->fail('Invalid price accepted');
                } catch (InvalidArgumentException) {
                    $this->assertTrue(true);
                }
            }
        }
    }

    public function test_variant_creation_cannot_conflict_with_parent_prices(): void
    {
        $p = $this->product(['price_usd' => '10.00']);
        $this->expectException(ConflictingPriceOwnership::class);
        $this->variant($p, ['price_usd' => '20.00']);
    }

    public function test_parent_price_updates_cannot_conflict_with_existing_variants(): void
    {
        $p = $this->product();
        $this->variant($p);
        $this->expectException(ConflictingPriceOwnership::class);
        $p->update(['price_syp' => '123.00']);
    }

    public function test_primary_image_uniqueness_is_database_enforced_and_secondary_images_are_unlimited(): void
    {
        $p = $this->product();
        $this->image($p);
        $this->image($p);
        $this->image($p, ['is_primary' => true]);
        $this->assertConstraint(fn () => $this->image($p, ['is_primary' => true]), 1062);
        $this->image($this->product(), ['is_primary' => true]);
        $this->assertSame(4, ProductImage::query()->count());
    }

    public function test_availability_precedence_covers_every_parent_variant_combination(): void
    {
        foreach (AvailabilityStatus::cases() as $parent) {
            foreach (AvailabilityStatus::cases() as $variant) {
                $expected = in_array(AvailabilityStatus::Unavailable, [$parent, $variant], true) ? AvailabilityStatus::Unavailable : (in_array(AvailabilityStatus::OnRequest, [$parent, $variant], true) ? AvailabilityStatus::OnRequest : AvailabilityStatus::Available);
                $this->assertSame($expected, $parent->effectiveWith($variant));
            }
        }
    }

    public function test_schema_indexes_checks_and_customer_schema_are_intact_without_stock_tables(): void
    {
        $this->assertCount(9, Schema::getColumnListing('customers'));
        $this->assertFalse(Schema::hasColumn('customers', 'password'));
        $this->assertTrue(Schema::hasTable('checkout_intents'));
        $this->assertTrue(Schema::hasTable('checkout_intent_items'));
        foreach (['inventories', 'stock_movements', 'stock_reservations', 'orders', 'payments'] as $table) {
            $this->assertFalse(Schema::hasTable($table));
        }
        foreach (['products' => ['brand_id', 'status', 'created_at', 'id'], 'product_variants' => ['product_id', 'sort_order', 'id'], 'product_images' => ['product_id', 'sort_order', 'id'], 'concern_product' => ['concern_id', 'product_id']] as $table => $columns) {
            $this->assertTrue(Schema::hasIndex($table, $columns));
        }
        foreach (['products', 'product_variants', 'product_images', 'concern_product'] as $table) {
            $this->assertNotEmpty(Schema::getForeignKeys($table));
        }
    }
}
