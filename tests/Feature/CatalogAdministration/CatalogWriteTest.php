<?php

namespace Tests\Feature\CatalogAdministration;

use App\Domains\Catalog\Application\Administration\ArchiveCatalogRecord;
use App\Domains\Catalog\Application\Administration\CatalogChange;
use App\Domains\Catalog\Infrastructure\Persistence\Models\Brand;
use App\Domains\Catalog\Infrastructure\Persistence\Models\Concern;
use App\Domains\Catalog\Infrastructure\Persistence\Models\Product;
use App\Domains\Catalog\Infrastructure\Persistence\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\OwnerDashboardTestCase;

class CatalogWriteTest extends OwnerDashboardTestCase
{
    public function test_named_directory_search_preserves_current_associations_and_empty_browser_selection_detaches_only_links(): void
    {
        $product = $this->product();
        $existing = $this->concern(['name' => 'Existing linked concern']);
        $found = $this->concern(['name' => 'Needle searchable concern']);
        $product->concerns()->attach($existing->id);
        for ($i = 0; $i < 51; $i++) {
            $this->concern(['name' => 'Directory '.$i]);
        }
        $existing->delete();
        $this->ownerLogin();
        $this->get(route('owner.catalog.edit', ['type' => 'products', 'id' => $product->id, 'concern_q' => 'Needle', 'brand_q' => 'No match']))
            ->assertOk()->assertSee('Existing linked concern')->assertSee('Needle searchable concern')->assertSee($product->brand->name)->assertDontSee('Directory 0');
        $this->patch($this->url('products', $product->id), ['lock_version' => '1', '_concerns_present' => '1'])->assertRedirect();
        $this->assertSame(0, $product->concerns()->withTrashed()->count());
        $this->assertNotNull($existing->fresh());
        $this->assertNotNull($found->fresh());
        $this->assertSame('2', (string) $product->fresh()->lock_version);
        $this->assertDatabaseCount('catalog_audit_events', 2);
    }

    private function url(string $type, ?string $id = null): string
    {
        return route($id ? 'owner.catalog.update' : 'owner.catalog.store', array_filter(['type' => $type, 'id' => $id]));
    }

    public function test_product_create_safe_defaults_decimal_strings_associations_and_persistent_attribution(): void
    {
        $brand = $this->brand();
        $concern = $this->concern();
        $this->ownerLogin();
        $this->post($this->url('products'), ['brand_id' => $brand->id, 'name' => 'مرطب <script>bad()</script>', 'price_usd' => '0', 'price_syp' => '123456.78', 'concerns' => [$concern->id]])->assertRedirect();
        $product = Product::query()->firstOrFail();
        $this->assertSame('hidden', $product->status->value);
        $this->assertSame('unavailable', $product->availability_status->value);
        $this->assertFalse($product->is_recommended);
        $this->assertSame('0.00', $product->price_usd);
        $this->assertSame('123456.78', $product->price_syp);
        $this->assertTrue(Str::isUuid($product->id));
        $this->assertSame([$concern->id], $product->concerns()->pluck('concerns.id')->all());
        $this->get(route('owner.catalog.edit', ['type' => 'products', 'id' => $product->id]))->assertOk()->assertSee('&lt;script&gt;', false)->assertDontSee('<script>bad()', false);
        $event = DB::table('catalog_audit_events')->where('action', 'create')->first();
        $this->assertSame($this->owner->id, $event->owner_id);
        $this->assertSame($product->id, $event->entity_id);
        $this->assertSame('0.00', json_decode($event->after_values, true)['price_usd']);
        $this->assertNotEmpty($event->request_id);
        $this->assertDatabaseCount('checkout_intents', 0);
    }

    public function test_omitted_fields_are_preserved_explicit_clear_is_null_and_stale_browser_version_is_rejected(): void
    {
        $product = $this->product(['price_usd' => '12.34', 'price_syp' => '900', 'short_description' => 'Original']);
        $this->ownerLogin();
        $this->patch($this->url('products', $product->id), ['lock_version' => '1', 'name' => 'أحدث'])->assertRedirect();
        $product->refresh();
        $this->assertSame('12.34', $product->price_usd);
        $this->assertSame('900.00', $product->price_syp);
        $this->assertSame('Original', $product->short_description);
        $this->patch($this->url('products', $product->id), ['lock_version' => '1', 'name' => 'Stale'])->assertStatus(409)->assertSee('تغيّر السجل');
        $this->assertSame('أحدث', $product->fresh()->name);
        $this->patch($this->url('products', $product->id), ['lock_version' => '2', 'price_usd' => null, 'short_description' => ''])->assertRedirect();
        $product->refresh();
        $this->assertNull($product->price_usd);
        $this->assertNull($product->short_description);
        $this->assertSame('900.00', $product->price_syp);
        $this->assertSame(2, DB::table('catalog_audit_events')->count());
        $this->assertSame('12.34', json_decode(DB::table('catalog_audit_events')->where('action', 'update')->orderByDesc('id')->first()->before_values, true)['price_usd']);
    }

    public static function invalidFields(): array
    {
        return [
            'negative' => ['price_usd', '-1'], 'scale' => ['price_usd', '1.234'], 'overflow' => ['price_syp', '10000000000000000'],
            'exponent' => ['price_usd', '1e2'], 'float' => ['price_usd', 1.25], 'locale' => ['price_syp', '1,200'],
            'state' => ['status', 'deleted'], 'availability' => ['availability_status', 'in_stock'],
            'id' => ['id', 'bad'], 'created' => ['created_at', '2020-01-01'], 'currency' => ['currency', 'EUR'],
            'control' => ['name', "bad\0name"], 'invalid_brand' => ['brand_id', 'bad'], 'missing_brand' => ['brand_id', '019aa000-0000-7000-8000-000000000001'],
        ];
    }

    #[DataProvider('invalidFields')]
    public function test_invalid_and_unexpected_product_values_do_not_mutate_or_audit(string $field, mixed $value): void
    {
        $brand = $this->brand();
        $this->ownerLogin();
        $this->post($this->url('products'), array_replace(['name' => 'منتج', 'brand_id' => $brand->id], [$field => $value]))->assertStatus(422);
        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('catalog_audit_events', 0);
    }

    public function test_slug_is_reserved_in_archive_and_name_update_preserves_established_slug(): void
    {
        $product = $this->product(['slug' => 'reserved']);
        $this->ownerLogin();
        $this->patch($this->url('products', $product->id), ['lock_version' => '1', 'name' => 'اسم آخر'])->assertRedirect();
        $this->assertSame('reserved', $product->fresh()->slug);
        app(ArchiveCatalogRecord::class)->execute('products', $product->id, '2', false, new CatalogChange($this->owner, 'fixture'));
        $this->post($this->url('products'), ['name' => 'جديد', 'slug' => 'reserved', 'brand_id' => $product->brand_id])->assertStatus(422);
        $this->assertSame(1, Product::withTrashed()->count());
    }

    public function test_first_variant_requires_explicit_parent_clear_and_archived_variants_still_reserve_price_ownership(): void
    {
        $product = $this->product(['price_usd' => '10']);
        $this->ownerLogin();
        $payload = ['name' => 'خيار', 'parent' => $product->id, 'price_usd' => '20'];
        $this->post($this->url('variants'), $payload)->assertStatus(409);
        $this->assertSame('10.00', $product->fresh()->price_usd);
        $this->patch($this->url('products', $product->id), ['lock_version' => '1', 'price_usd' => null])->assertRedirect();
        $this->post($this->url('variants'), $payload)->assertRedirect();
        $variant = ProductVariant::query()->firstOrFail();
        $this->patch($this->url('variants', $variant->id), ['lock_version' => '1', 'sort_order' => 5, 'price_syp' => '40000', 'availability_status' => 'on_request'])->assertRedirect();
        $this->assertSame('20.00', $variant->fresh()->price_usd);
        $this->assertSame(5, $variant->fresh()->sort_order);
        $this->patch($this->url('variants', $variant->id), ['lock_version' => '2', 'product_id' => $this->product()->id])->assertStatus(422);
        app(ArchiveCatalogRecord::class)->execute('variants', $variant->id, '2', false, new CatalogChange($this->owner, 'fixture'));
        $this->patch($this->url('products', $product->id), ['lock_version' => '2', 'price_usd' => '3'])->assertStatus(409);
        $this->assertNull($product->fresh()->price_usd);
        app(ArchiveCatalogRecord::class)->execute('variants', $variant->id, '3', true, new CatalogChange($this->owner, 'fixture'));
        $this->assertSame($product->id, $variant->fresh()->product_id);
        $this->assertSame('20.00', $variant->fresh()->price_usd);
    }

    public function test_brands_concerns_sort_metadata_associations_and_archival_preserve_children_and_pivots(): void
    {
        $this->ownerLogin();
        $this->post($this->url('brands'), ['name' => 'علامة', 'slug' => 'brand-new', 'description' => '<b>Text</b>', 'meta_title' => 'عنوان', 'meta_description' => 'وصف', 'sort_order' => 9])->assertRedirect();
        $brand = Brand::query()->where('slug', 'brand-new')->firstOrFail();
        $this->post($this->url('concerns'), ['name' => 'اهتمام', 'status' => 'active', 'sort_order' => 4])->assertRedirect();
        $concern = Concern::query()->firstOrFail();
        $product = $this->product(['brand_id' => $brand->id]);
        $this->patch($this->url('products', $product->id), ['lock_version' => '1', 'concerns' => [$concern->id, $concern->id]])->assertStatus(422);
        $this->patch($this->url('products', $product->id), ['lock_version' => '1', 'concerns' => [$concern->id]])->assertRedirect();
        $this->assertDatabaseCount('concern_product', 1);
        $change = new CatalogChange($this->owner, 'fixture');
        app(ArchiveCatalogRecord::class)->execute('concerns', $concern->id, '1', false, $change);
        $this->patch($this->url('products', $product->id), ['lock_version' => '2', 'name' => 'تعديل', 'concerns' => [$concern->id]])->assertRedirect();
        $other = $this->product();
        $this->patch($this->url('products', $other->id), ['lock_version' => '1', 'concerns' => [$concern->id]])->assertStatus(422);
        app(ArchiveCatalogRecord::class)->execute('brands', $brand->id, '1', false, $change);
        $this->assertSame(2, Product::query()->count());
        $this->assertDatabaseCount('concern_product', 1);
        $this->get('/api/v1/products/'.$product->slug)->assertNotFound();
        app(ArchiveCatalogRecord::class)->execute('brands', $brand->id, '2', true, $change);
        $this->assertSame('active', $product->fresh()->status->value);
        $this->assertSame('hidden', $brand->fresh()->status->value);
    }
}
