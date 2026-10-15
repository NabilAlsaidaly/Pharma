<?php

namespace Tests\Feature\CatalogAdministration;

use App\Domains\Cart\Application\CartOperations;
use App\Domains\Catalog\Application\Administration\ArchiveCatalogRecord;
use App\Domains\Catalog\Application\Administration\CatalogChange;
use App\Domains\Catalog\Application\Administration\WriteCatalogRecord;
use App\Domains\Catalog\Infrastructure\Persistence\Models\Brand;
use App\Domains\Catalog\Infrastructure\Persistence\Models\Product;
use App\Domains\Catalog\Infrastructure\Persistence\Models\ProductImage;
use App\Domains\Catalog\Infrastructure\Persistence\Models\ProductVariant;
use App\Domains\Identity\Infrastructure\Persistence\Models\Customer;
use App\Domains\ManagementIdentity\Application\ProvisionOwner;
use App\Domains\ManagementIdentity\Infrastructure\Persistence\Models\ManagementUser;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class CatalogConcurrencyTest extends TestCase
{
    use DatabaseMigrations { runDatabaseMigrations as private migrateIsolated; }

    private ManagementUser $owner;

    private Product $product;

    private Customer $customer;

    public function runDatabaseMigrations(): void
    {
        if (! app()->environment('testing') || config('database.default') !== 'mysql' || config('database.connections.mysql.database') !== 'sumaya_pharma_test' || DB::selectOne('SELECT DATABASE() AS name')->name !== 'sumaya_pharma_test') {
            throw new \RuntimeException('Catalog races require isolated MySQL.');
        }
        $this->migrateIsolated();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = app(ProvisionOwner::class)->execute('Race Owner', 'race-owner@example.test', 'Race-fixture-passphrase-2026', 'Race-fixture-passphrase-2026');
        $brand = Brand::query()->create(['name' => 'Race', 'slug' => 'race', 'status' => 'active']);
        $this->product = Product::query()->create(['name' => 'Race Product', 'slug' => 'race-product', 'brand_id' => $brand->id, 'status' => 'active', 'availability_status' => 'available']);
        $this->customer = Customer::query()->create(['name' => 'Race Customer', 'google_sub' => (string) Str::uuid7(), 'email' => 'race-customer@example.test']);
    }

    private function change(): CatalogChange
    {
        return new CatalogChange($this->owner, 'main-race');
    }

    private function overlap(array $payloads, string $table = 'products', ?string $key = null, ?callable $mutation = null, array $waitTables = []): array
    {
        $dir = storage_path('framework/testing/catalog-race-'.Str::uuid7());
        mkdir($dir);
        $workers = [];
        $connections = [];
        DB::beginTransaction();
        DB::table($table)->where('id', $key ?? $this->product->id)->lockForUpdate()->first();
        try {
            foreach ($payloads as $index => $payload) {
                $ready = $dir.'/ready-'.$index;
                $file = $dir.'/payload-'.$index.'.json';
                file_put_contents($file, json_encode($payload + ['owner' => $this->owner->id, 'ready' => $ready], JSON_THROW_ON_ERROR));
                $worker = new Process([PHP_BINARY, base_path('tests/Support/catalog-admin-worker.php'), $file], base_path(), null, null, 40);
                $workers[] = $worker;
                $worker->start();
                $deadline = microtime(true) + 25;
                do {
                    if (! $worker->isRunning()) {
                        $this->fail($worker->getErrorOutput().$worker->getOutput());
                    }
                    if (file_exists($ready)) {
                        $connection = (int) file_get_contents($ready);
                        $active = collect(DB::select('SHOW FULL PROCESSLIST'))->first(fn ($r) => (int) $r->Id === $connection);
                        $expectedTable = $active !== null && collect($waitTables ?: [$table])->contains(fn ($name) => str_contains((string) $active->Info, '`'.$name.'`'));
                        $blocked = $expectedTable && (str_contains((string) $active->Info, 'for update') || str_contains((string) $active->Info, 'insert into'));
                    } else {
                        $blocked = false;
                    }
                    if (! $blocked) {
                        usleep(10000);
                    }
                } while (! $blocked && microtime(true) < $deadline);
                $this->assertTrue($blocked, 'Observe independent SQL waiting on the held row/gap, not sequential execution.');
                $this->assertNotSame((int) DB::selectOne('SELECT CONNECTION_ID() AS id')->id, $connection);
                $connections[] = $connection;
            }
            $this->assertCount(count($payloads), array_unique($connections));
            if ($mutation) {
                $mutation();
            }
            DB::commit();
            $results = [];
            foreach ($workers as $worker) {
                $worker->wait();
                $this->assertTrue($worker->isSuccessful(), $worker->getErrorOutput());
                $results[] = json_decode($worker->getOutput(), true, 512, JSON_THROW_ON_ERROR);
            }
            fwrite(STDOUT, 'CATALOG_ADMIN_REAL_OVERLAP '.json_encode(['table' => $table, 'connections' => $connections, 'actions' => array_column($payloads, 'action'), 'results' => $results], JSON_THROW_ON_ERROR).PHP_EOL);

            return $results;
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            foreach ($workers as $worker) {
                if ($worker->isRunning()) {
                    $worker->stop();
                }
            }
            foreach (glob($dir.'/*') ?: [] as $path) {
                unlink($path);
            }
            rmdir($dir);
        }
    }

    public function test_two_owner_edits_serialize_and_one_stale_edit_cannot_overwrite(): void
    {
        $results = $this->overlap([
            ['action' => 'write', 'type' => 'products', 'id' => $this->product->id, 'input' => ['lock_version' => '1', 'name' => 'First']],
            ['action' => 'write', 'type' => 'products', 'id' => $this->product->id, 'input' => ['lock_version' => '1', 'name' => 'Second']],
        ]);
        $this->assertSame(1, count(array_filter($results, fn ($r) => $r['success'] ?? false)));
        $this->assertSame('2', (string) $this->product->fresh()->lock_version);
        $this->assertDatabaseCount('catalog_audit_events', 1);
    }

    public function test_parent_price_vs_first_variant_cannot_both_commit(): void
    {
        $results = $this->overlap([
            ['action' => 'write', 'type' => 'products', 'id' => $this->product->id, 'input' => ['lock_version' => '1', 'price_usd' => '9']],
            ['action' => 'write', 'type' => 'variants', 'parent' => $this->product->id, 'input' => ['name' => 'Variant', 'price_usd' => '11']],
        ]);
        $this->assertSame(1, count(array_filter($results, fn ($r) => $r['success'] ?? false)));
        $this->assertFalse($this->product->fresh()->price_usd !== null && $this->product->variants()->withTrashed()->exists());
        $this->assertDatabaseCount('catalog_audit_events', 1);
    }

    public function test_product_archive_committed_while_cart_add_waits_prevents_new_admission(): void
    {
        $this->product->update(['price_usd' => '5']);
        $result = $this->overlap([['action' => 'add', 'customer' => $this->customer->id, 'product' => $this->product->id]], mutation: fn () => app(ArchiveCatalogRecord::class)->execute('products', $this->product->id, '1', false, $this->change()));
        $this->assertArrayHasKey('refused', $result[0]);
        $this->assertDatabaseCount('cart_items', 0);
        $this->assertTrue($this->product->fresh()->trashed());
    }

    public function test_variant_archival_while_checkout_waits_retains_cart_but_refuses_new_submission(): void
    {
        $variant = ProductVariant::query()->create(['product_id' => $this->product->id, 'name' => 'Variant', 'price_usd' => '5', 'availability_status' => 'available']);
        app(CartOperations::class)->add($this->customer->id, $this->product->id, $variant->id, 1);
        $payload = ['operation_id' => (string) Str::uuid7(), 'currency' => 'USD', 'expected_cart_version' => '1', 'contact' => ['full_name' => 'Race Customer', 'phone' => '+963999999999', 'governorate' => 'دمشق', 'city' => 'دمشق', 'address' => 'عنوان اختبار', 'notes' => null]];
        $result = $this->overlap([['action' => 'submit', 'customer' => $this->customer->id, 'input' => $payload]], mutation: fn () => app(ArchiveCatalogRecord::class)->execute('variants', $variant->id, '1', false, $this->change()));
        $this->assertArrayHasKey('refused', $result[0]);
        $this->assertDatabaseCount('checkout_intents', 0);
        $this->assertDatabaseCount('cart_items', 1);
    }

    public function test_brand_hide_while_customer_selection_waits_enforces_effective_visibility(): void
    {
        $this->product->update(['price_usd' => '5']);
        $result = $this->overlap([['action' => 'add', 'customer' => $this->customer->id, 'product' => $this->product->id]], 'brands', $this->product->brand_id, fn () => app(WriteCatalogRecord::class)->execute('brands', $this->product->brand_id, ['lock_version' => '1', 'status' => 'hidden'], $this->change()));
        $this->assertArrayHasKey('refused', $result[0]);
        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_two_primary_assignments_preserve_database_uniqueness_and_truthful_audit(): void
    {
        $a = ProductImage::query()->create(['product_id' => $this->product->id, 'path' => 'a.png']);
        $b = ProductImage::query()->create(['product_id' => $this->product->id, 'path' => 'b.png']);
        $results = $this->overlap([['action' => 'primary', 'product' => $this->product->id, 'image' => $a->id], ['action' => 'primary', 'product' => $this->product->id, 'image' => $b->id]]);
        $this->assertSame(2, count(array_filter($results, fn ($r) => $r['success'] ?? false)));
        $this->assertSame(1, ProductImage::query()->where('is_primary', 1)->count());
        $this->assertDatabaseCount('catalog_audit_events', 3);
    }

    public function test_overlapping_bulk_actions_cannot_silently_overwrite_or_commit_false_audit_success(): void
    {
        $preview = ['type' => 'products', 'scope' => 'ids', 'versions' => [$this->product->id => '1'], 'archived' => false, 'filters' => [], 'action' => 'hidden'];
        $other = array_replace($preview, ['action' => 'recommend']);
        $results = $this->overlap([['action' => 'bulk', 'bulk_id' => (string) Str::uuid7(), 'preview' => $preview], ['action' => 'bulk', 'bulk_id' => (string) Str::uuid7(), 'preview' => $other]]);
        $this->assertSame(1, count(array_filter($results, fn ($r) => $r['success'] ?? false)));
        $this->assertDatabaseCount('catalog_audit_events', 1);
        $this->assertSame('2', (string) $this->product->fresh()->lock_version);
    }

    public function test_restore_vs_parent_price_change_keeps_all_physical_variant_price_ownership(): void
    {
        $variant = ProductVariant::query()->create(['product_id' => $this->product->id, 'name' => 'Old', 'price_usd' => '2']);
        app(ArchiveCatalogRecord::class)->execute('variants', $variant->id, '1', false, $this->change());
        $results = $this->overlap([['action' => 'restore', 'type' => 'variants', 'id' => $variant->id, 'version' => '2'], ['action' => 'write', 'type' => 'products', 'id' => $this->product->id, 'input' => ['lock_version' => '1', 'price_usd' => '9']]]);
        $this->assertSame(1, count(array_filter($results, fn ($r) => $r['success'] ?? false)));
        $this->assertFalse($variant->fresh()->trashed());
        $this->assertNull($this->product->fresh()->price_usd);
        $this->assertDatabaseCount('catalog_audit_events', 2);
    }

    public function test_product_restore_waits_for_brand_archive_and_does_not_restore_brand_or_publish(): void
    {
        app(ArchiveCatalogRecord::class)->execute('products', $this->product->id, '1', false, $this->change());
        $result = $this->overlap([['action' => 'restore', 'type' => 'products', 'id' => $this->product->id, 'version' => '2']], 'brands', $this->product->brand_id, fn () => app(ArchiveCatalogRecord::class)->execute('brands', $this->product->brand_id, '1', false, $this->change()));
        $this->assertTrue($result[0]['success']);
        $this->assertFalse($this->product->fresh()->trashed());
        $this->assertSame(0, Product::query()->publiclyVisible()->count());
        $this->assertTrue(Brand::withTrashed()->findOrFail($this->product->brand_id)->trashed());
    }

    public static function additionalAdmissionRaces(): array
    {
        return [['variant_update'], ['product_submit'], ['brand_submit']];
    }

    #[DataProvider('additionalAdmissionRaces')]
    public function test_other_locked_customer_boundaries_observe_committed_catalog_invalidation(string $scenario): void
    {
        $variant = null;
        if ($scenario === 'variant_update') {
            $variant = ProductVariant::query()->create(['product_id' => $this->product->id, 'name' => 'Selected', 'price_usd' => '5', 'availability_status' => 'available']);
        } else {
            $this->product->update(['price_usd' => '5']);
        }
        app(CartOperations::class)->add($this->customer->id, $this->product->id, $variant?->id, 1);
        $item = DB::table('cart_items')->first();
        $input = ['operation_id' => (string) Str::uuid7(), 'currency' => 'USD', 'expected_cart_version' => '1', 'contact' => ['full_name' => 'Race Customer', 'phone' => '+963999999999', 'governorate' => 'دمشق', 'city' => 'دمشق', 'address' => 'عنوان اختبار', 'notes' => null]];
        $payload = $scenario === 'variant_update' ? ['action' => 'cart_update', 'customer' => $this->customer->id, 'item' => $item->id, 'input' => ['quantity' => 2, 'expected_version' => '1']] : ['action' => 'submit', 'customer' => $this->customer->id, 'input' => $input];
        $mutation = match ($scenario) {
            'variant_update' => fn () => app(ArchiveCatalogRecord::class)->execute('variants', $variant->id, '1', false, $this->change()),
            'product_submit' => fn () => app(ArchiveCatalogRecord::class)->execute('products', $this->product->id, '1', false, $this->change()),
            'brand_submit' => fn () => app(WriteCatalogRecord::class)->execute('brands', $this->product->brand_id, ['lock_version' => '1', 'status' => 'hidden'], $this->change()),
        };
        $result = $this->overlap([$payload], $scenario === 'brand_submit' ? 'brands' : 'products', $scenario === 'brand_submit' ? $this->product->brand_id : $this->product->id, $mutation);
        $this->assertArrayHasKey('refused', $result[0]);
        $this->assertDatabaseCount('checkout_intents', 0);
        $this->assertSame(1, DB::table('cart_items')->first()->quantity);
        // Decrease/removal remains available for retained blocked selections.
        app(CartOperations::class)->remove($this->customer->id, $item->id);
        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_two_simultaneous_slug_creations_use_database_uniqueness_without_duplicate_audit(): void
    {
        $input = ['name' => 'Duplicate slug', 'slug' => 'same-slug', 'brand_id' => $this->product->brand_id];
        $results = $this->overlap([['action' => 'write', 'type' => 'products', 'input' => $input], ['action' => 'write', 'type' => 'products', 'input' => $input]], key: 'ffffffff-ffff-7fff-8fff-ffffffffffff', waitTables: ['products', 'brands']);
        $this->assertSame(1, count(array_filter($results, fn ($r) => $r['success'] ?? false)));
        $this->assertSame(1, Product::query()->where('slug', 'same-slug')->count());
        $this->assertDatabaseCount('catalog_audit_events', 1);
    }

    public function test_concurrent_archive_restore_never_reports_a_false_second_transition(): void
    {
        app(ArchiveCatalogRecord::class)->execute('products', $this->product->id, '1', false, $this->change());
        $results = $this->overlap([['action' => 'restore', 'type' => 'products', 'id' => $this->product->id, 'version' => '2'], ['action' => 'archive', 'type' => 'products', 'id' => $this->product->id, 'version' => '2']]);
        $this->assertTrue($results[0]['success']);
        $this->assertFalse($this->product->fresh()->trashed());
        $this->assertSame('3', (string) $this->product->fresh()->lock_version);
        $this->assertDatabaseCount('catalog_audit_events', 2);
    }

    public function test_variant_price_update_vs_parent_structure_price_attempt_cannot_create_dual_ownership(): void
    {
        $variant = ProductVariant::query()->create(['product_id' => $this->product->id, 'name' => 'Variant', 'price_usd' => '3']);
        $results = $this->overlap([['action' => 'write', 'type' => 'variants', 'id' => $variant->id, 'parent' => $this->product->id, 'input' => ['lock_version' => '1', 'price_usd' => '4']], ['action' => 'write', 'type' => 'products', 'id' => $this->product->id, 'input' => ['lock_version' => '1', 'price_usd' => '8']]]);
        $this->assertTrue($results[0]['success']);
        $this->assertArrayHasKey('refused', $results[1]);
        $this->assertSame('4.00', $variant->fresh()->price_usd);
        $this->assertNull($this->product->fresh()->price_usd);
        $this->assertDatabaseCount('catalog_audit_events', 1);
    }
}
