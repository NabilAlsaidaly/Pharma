<?php

namespace Tests\Feature\Collections;

use App\Domains\Cart\Application\CartOperations;
use App\Domains\Cart\Application\CartSnapshot;
use App\Domains\Cart\Infrastructure\Persistence\Models\Cart;
use App\Domains\Cart\Infrastructure\Persistence\Models\CartItem;
use App\Domains\Catalog\Infrastructure\Persistence\Models\Brand;
use App\Domains\Catalog\Infrastructure\Persistence\Models\Product;
use App\Domains\Catalog\Infrastructure\Persistence\Models\ProductVariant;
use App\Domains\Identity\Infrastructure\Persistence\Models\Customer;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class CollectionsConcurrencyTest extends TestCase
{
    use DatabaseMigrations {runDatabaseMigrations as private migrateTestDatabase; }

    private Customer $owner;

    private Product $product;

    private string $directory;

    public function runDatabaseMigrations(): void
    {
        if (! app()->environment('testing') || config('database.default') !== 'mysql' || config('database.connections.mysql.database') !== 'sumaya_pharma_test') {
            throw new \RuntimeException('Concurrency tests require isolated MySQL.');
        }
        $this->migrateTestDatabase();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = Customer::query()->create(['google_sub' => (string) Str::uuid7(), 'name' => 'Concurrent', 'email' => 'fixture@example.test']);
        $brand = Brand::query()->create(['name' => 'Concurrent', 'slug' => 'concurrent', 'status' => 'active']);
        $this->product = Product::query()->create(['brand_id' => $brand->id, 'name' => 'Concurrent', 'slug' => 'concurrent', 'status' => 'active', 'availability_status' => 'available', 'price_usd' => '2']);
        $this->directory = storage_path('framework/testing/concurrency-'.Str::uuid7());
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory.'/*') ?: [] as $file) {
            unlink($file);
        }rmdir($this->directory);
        parent::tearDown();
    }

    private function launch(array $payload, int $number): Process
    {
        $file = $this->directory.'/request-'.$number.'.json';
        $payload['ready'] = $this->directory.'/ready-'.$number;
        if (file_exists($payload['ready'])) {
            unlink($payload['ready']);
        }
        file_put_contents($file, json_encode($payload, JSON_THROW_ON_ERROR));
        $process = new Process([PHP_BINARY, base_path('tests/Support/collection-worker.php'), $file], base_path(), null, null, 30);
        $process->start();

        return $process;
    }

    private function waitUntil(callable $condition): void
    {
        $deadline = microtime(true) + 20;
        while (! $condition()) {
            if (microtime(true) > $deadline) {
                $this->fail('Independent worker coordination timed out.');
            }usleep(10000);
        }
    }

    private function overlap(array $a, array $b, bool $raw = false): array
    {
        $a['customer'] = $b['customer'] = $this->owner->id;
        $connection = DB::connection();
        $connection->beginTransaction();
        DB::table($raw ? 'products' : 'customers')->where('id', $raw ? $this->product->id : $this->owner->id)->lockForUpdate()->first();
        $workers = [];
        try {
            $workers = [$this->launch($a, 1), $this->launch($b, 2)];
            $this->waitUntil(fn () => file_exists($this->directory.'/ready-1') && file_exists($this->directory.'/ready-2'));
            $ids = [(int) file_get_contents($this->directory.'/ready-1'), (int) file_get_contents($this->directory.'/ready-2')];
            $this->assertNotSame($ids[0], $ids[1]);
            // Both independent sessions must be inside their blocking SQL statement while parent holds its row lock.
            $this->waitUntil(function () use ($ids, $raw) {
                $running = collect(DB::select('SHOW FULL PROCESSLIST'))->filter(fn ($row) => in_array((int) $row->Id, $ids, true)
                    && str_contains(strtolower($row->Info ?? ''), $raw ? 'insert into `cart_items`' : '`customers`')
                    && ($raw || str_contains(strtolower($row->Info ?? ''), 'for update')));

                return $running->count() === 2;
            });
            $this->assertTrue($workers[0]->isRunning());
            $this->assertTrue($workers[1]->isRunning());
            $connection->commit();
            $results = [];
            foreach ($workers as $worker) {
                $worker->wait();
                $this->assertTrue($worker->isSuccessful(), $worker->getErrorOutput());
                $results[] = json_decode($worker->getOutput(), true, 512, JSON_THROW_ON_ERROR);
            }
            fwrite(STDOUT, 'REAL_CONCURRENCY '.json_encode(['connections' => $ids, 'both_blocked' => true, 'outcomes' => array_column($results, 'outcome')]).PHP_EOL);

            return $results;
        } finally {
            if ($connection->transactionLevel() > 0) {
                $connection->rollBack();
            }
            foreach ($workers as $worker) {
                if ($worker->isRunning()) {
                    $worker->stop();
                }
            }
        }
    }

    public function test_concurrent_first_cart_same_simple_selection_adds_sum_exactly(): void
    {
        $results = $this->overlap(['operation' => 'add', 'product' => $this->product->id, 'quantity' => 2], ['operation' => 'add', 'product' => $this->product->id, 'quantity' => 3]);
        $this->assertSame(['success', 'success'], array_column($results, 'outcome'));
        $this->assertSame(1, Cart::query()->count());
        $this->assertSame(1, CartItem::query()->count());
        $this->assertSame(5, CartItem::query()->sole()->quantity);
        $this->assertSame('2', Cart::query()->sole()->version);
    }

    public function test_concurrent_existing_variant_increments_not_lost(): void
    {
        $this->product->update(['price_usd' => null]);
        $v = ProductVariant::query()->create(['product_id' => $this->product->id, 'name' => 'A', 'price_usd' => '1', 'availability_status' => 'available']);
        app(CartOperations::class)->add($this->owner->id, $this->product->id, $v->id, 1);
        $this->overlap(['operation' => 'add', 'product' => $this->product->id, 'variant' => $v->id, 'quantity' => 2], ['operation' => 'add', 'product' => $this->product->id, 'variant' => $v->id, 'quantity' => 3]);
        $this->assertSame(6, CartItem::query()->sole()->quantity);
        $this->assertSame('3', Cart::query()->sole()->version);
    }

    public function test_concurrent_absolute_updates_one_wins_other_conflicts(): void
    {
        $id = app(CartOperations::class)->add($this->owner->id, $this->product->id, null, 1)['item']['id'];
        $r = $this->overlap(['operation' => 'update', 'item' => $id, 'input' => ['quantity' => 2, 'expected_version' => '1']], ['operation' => 'update', 'item' => $id, 'input' => ['quantity' => 3, 'expected_version' => '1']]);
        $this->assertCount(1, array_filter($r, fn ($v) => $v['outcome'] === 'success'));
        $this->assertSame('stale_cart_version', array_values(array_filter($r, fn ($v) => $v['outcome'] === 'rejected'))[0]['reason']);
        $this->assertContains(CartItem::query()->sole()->quantity, [2, 3]);
    }

    public function test_concurrent_remove_update_linearizes_and_never_resurrects_item(): void
    {
        $id = app(CartOperations::class)->add($this->owner->id, $this->product->id, null, 1)['item']['id'];
        $r = $this->overlap(['operation' => 'remove', 'item' => $id], ['operation' => 'update', 'item' => $id, 'input' => ['quantity' => 2, 'expected_version' => '1']]);
        $this->assertSame('success', $r[0]['outcome']);
        $this->assertSame(0, CartItem::query()->count());
        if ($r[1]['outcome'] === 'rejected') {
            $this->assertSame(ModelNotFoundException::class, $r[1]['type']);
        } else {
            $this->assertSame(2, $r[1]['result']['item']['quantity']);
        }
    }

    public function test_competing_variant_changes_and_merge_destination_add_are_atomic(): void
    {
        $this->product->update(['price_usd' => null]);
        $variants = [];
        foreach (['A', 'B', 'C'] as $name) {
            $variants[] = ProductVariant::query()->create(['product_id' => $this->product->id, 'name' => $name, 'price_usd' => '1', 'availability_status' => 'available']);
        }
        $ops = app(CartOperations::class);
        $id = $ops->add($this->owner->id, $this->product->id, $variants[0]->id, 2)['item']['id'];
        $r = $this->overlap(['operation' => 'update', 'item' => $id, 'input' => ['variant_id' => $variants[1]->id, 'expected_version' => '1']], ['operation' => 'update', 'item' => $id, 'input' => ['variant_id' => $variants[2]->id, 'expected_version' => '1']]);
        $this->assertCount(1, array_filter($r, fn ($v) => $v['outcome'] === 'success'));
        $current = CartItem::query()->sole();
        $destination = $current->variant_id === $variants[1]->id ? $variants[2] : $variants[1];
        $ops->add($this->owner->id, $this->product->id, $destination->id, 3);
        $version = Cart::query()->sole()->version;
        $r = $this->overlap(['operation' => 'update', 'item' => $id, 'input' => ['variant_id' => $destination->id, 'expected_version' => $version]], ['operation' => 'add', 'product' => $this->product->id, 'variant' => $destination->id, 'quantity' => 4]);
        $this->assertSame('success', $r[1]['outcome']);
        $this->assertSame(9, (int) CartItem::query()->sum('quantity'));
        $this->assertLessThanOrEqual(2, CartItem::query()->count());
    }

    public function test_concurrent_favorite_additions_and_add_remove_preserve_unique_owned_pair(): void
    {
        $payload = ['operation' => 'favorite_add', 'product' => $this->product->id];
        $r = $this->overlap($payload, $payload);
        $this->assertSame(['success', 'success'], array_column($r, 'outcome'));
        $this->assertSame(1, DB::table('favorites')->count());
        $r = $this->overlap($payload, ['operation' => 'favorite_remove', 'product' => $this->product->id]);
        $this->assertSame(['success', 'success'], array_column($r, 'outcome'));
        $this->assertLessThanOrEqual(1, DB::table('favorites')->count());
    }

    public function test_real_concurrent_raw_duplicate_null_insert_rejected_by_database(): void
    {
        $cart = Cart::query()->create(['customer_id' => $this->owner->id]);
        $row = ['id' => (string) Str::uuid7(), 'cart_id' => $cart->id, 'product_id' => $this->product->id, 'variant_id' => null, 'quantity' => 1, 'created_at' => now()->format('Y-m-d H:i:s'), 'updated_at' => now()->format('Y-m-d H:i:s')];
        $r = $this->overlap(['operation' => 'raw_insert', 'row' => $row], ['operation' => 'raw_insert', 'row' => array_replace($row, ['id' => (string) Str::uuid7()])], true);
        $this->assertCount(1, array_filter($r, fn ($v) => $v['outcome'] === 'success'));
        $this->assertSame(1062, array_values(array_filter($r, fn ($v) => $v['outcome'] === 'rejected'))[0]['mysql_code']);
        $this->assertSame(1, CartItem::query()->count());
    }

    public function test_read_snapshot_consistent_during_committed_catalog_price_and_availability_change(): void
    {
        app(CartOperations::class)->add($this->owner->id, $this->product->id, null, 1);
        $ready = $this->directory.'/snapshot-ready';
        $resume = $this->directory.'/resume';
        $worker = $this->launch(['operation' => 'snapshot', 'customer' => $this->owner->id, 'snapshot_ready' => $ready, 'resume' => $resume], 1);
        try {
            $this->waitUntil(fn () => file_exists($ready));
            $this->product->update(['price_usd' => '9', 'availability_status' => 'unavailable']);
            file_put_contents($resume, 'go');
            $worker->wait();
            $this->assertTrue($worker->isSuccessful(), $worker->getErrorOutput());
            $r = json_decode($worker->getOutput(), true, 512, JSON_THROW_ON_ERROR);
            $this->assertSame('success', $r['outcome']);
            $this->assertSame('2.00', $r['result']['totals']['USD']['estimated_total']);
            $this->assertSame('available', $r['result']['items'][0]['evaluation']['availability']);
            $this->assertNull(app(CartSnapshot::class)->read($this->owner->id, 1, 12)['totals']['USD']['estimated_total']);
        } finally {
            if ($worker->isRunning()) {
                $worker->stop();
            }
        }
    }
}
