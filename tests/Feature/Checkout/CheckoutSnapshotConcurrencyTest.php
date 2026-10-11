<?php

namespace Tests\Feature\Checkout;

use App\Domains\Cart\Application\CartOperations;
use App\Domains\Cart\Domain\Exceptions\CartConflict;
use App\Domains\Cart\Infrastructure\Persistence\Models\Cart;
use App\Domains\Catalog\Infrastructure\Persistence\Models\Brand;
use App\Domains\Catalog\Infrastructure\Persistence\Models\Product;
use App\Domains\Identity\Infrastructure\Persistence\Models\Customer;
use App\Domains\Orders\Application\EvaluateCheckout;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class CheckoutSnapshotConcurrencyTest extends TestCase
{
    use DatabaseMigrations {runDatabaseMigrations as private migrateIsolatedDatabase; }

    private Customer $owner;

    private Product $product;

    public function runDatabaseMigrations(): void
    {
        if (! app()->environment('testing') || config('database.default') !== 'mysql'
            || config('database.connections.mysql.database') !== 'sumaya_pharma_test'
            || DB::selectOne('SELECT DATABASE() AS name')->name !== 'sumaya_pharma_test') {
            throw new \RuntimeException('Checkout concurrency requires isolated MySQL.');
        }
        $this->migrateIsolatedDatabase();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = Customer::query()->create(['google_sub' => 'checkout-fixture', 'name' => 'Fixture', 'email' => 'fixture@example.test']);
        $brand = Brand::query()->create(['name' => 'Fixture', 'slug' => 'checkout-brand', 'status' => 'active']);
        $this->product = Product::query()->create(['brand_id' => $brand->id, 'name' => 'Fixture', 'slug' => 'checkout-product',
            'status' => 'active', 'availability_status' => 'available', 'price_usd' => '2']);
        app(CartOperations::class)->add($this->owner->id, $this->product->id, null, 1);
    }

    private function race(string $boundary, callable $mutation): array
    {
        $directory = storage_path('framework/testing/checkout-race-'.Str::uuid7());
        mkdir($directory);
        $ready = $directory.'/ready';
        $resume = $directory.'/resume';
        $payload = $directory.'/payload.json';
        file_put_contents($payload, json_encode(['boundary' => $boundary, 'customer' => $this->owner->id, 'ready' => $ready, 'resume' => $resume], JSON_THROW_ON_ERROR));
        $worker = new Process([PHP_BINARY, base_path('tests/Support/checkout-snapshot-worker.php'), $payload], base_path(), null, null, 30);
        try {
            $worker->start();
            $deadline = microtime(true) + 20;
            while (! file_exists($ready)) {
                if (microtime(true) > $deadline || ! $worker->isRunning()) {
                    $this->fail('Snapshot worker failed: '.$worker->getErrorOutput().$worker->getOutput());
                }
                usleep(10000);
            }
            $evidence = json_decode(file_get_contents($ready), true, 512, JSON_THROW_ON_ERROR);
            $parent = DB::selectOne('SELECT CONNECTION_ID() AS id, @@transaction_isolation AS isolation');
            $this->assertSame('REPEATABLE-READ', $parent->isolation);
            $this->assertNotSame((int) $parent->id, (int) $evidence['connection']);
            $this->assertSame(1, $evidence['transaction_level']);
            $this->assertTrue($worker->isRunning());
            // Writer must commit while independent evaluation transaction is still paused.
            $mutation();
            $this->assertSame(0, DB::transactionLevel());
            $this->assertTrue($worker->isRunning());
            file_put_contents($resume, 'continue');
            $worker->wait();
            $this->assertTrue($worker->isSuccessful(), $worker->getErrorOutput());
            $result = json_decode($worker->getOutput(), true, 512, JSON_THROW_ON_ERROR);
            $this->assertSame('1', $result['cart']['version']);
            $this->assertSame(1, $result['cart']['item_count']);
            $this->assertSame(1, $result['items'][0]['quantity']);
            $this->assertSame('2.00', $result['pricing']['estimated_items_subtotal']);
            $this->assertSame('valid', $result['selection_status']);
            $this->assertFalse($result['commercial']['reserves_inventory']);
            fwrite(STDOUT, 'CHECKOUT_REAL_SNAPSHOT '.json_encode(['boundary' => $boundary, 'reader_connection' => $evidence['connection'],
                'writer_connection' => $parent->id, 'overlap_transaction' => true, 'writer_committed_before_reader_resumed' => true]).PHP_EOL);

            return $result;
        } finally {
            if ($worker->isRunning()) {
                $worker->stop();
            }
            foreach (glob($directory.'/*') ?: [] as $file) {
                unlink($file);
            }
            rmdir($directory);
        }
    }

    public function test_cart_header_and_item_quantities_share_snapshot_during_update(): void
    {
        $item = Cart::query()->sole()->items()->sole();
        $this->race('cart', fn () => app(CartOperations::class)->update($this->owner->id, $item->id, ['quantity' => 3, 'expected_version' => '1']));
        $next = app(EvaluateCheckout::class)->execute($this->owner->id, 'USD', '2');
        $this->assertSame(3, $next['items'][0]['quantity']);
        $this->assertSame('6.00', $next['pricing']['estimated_items_subtotal']);
        $this->expectException(CartConflict::class);
        app(EvaluateCheckout::class)->execute($this->owner->id, 'USD', '1');
    }

    public function test_catalog_price_and_availability_share_old_committed_snapshot(): void
    {
        $this->race('catalog', fn () => $this->product->update(['price_usd' => '9', 'availability_status' => 'unavailable']));
        $next = app(EvaluateCheckout::class)->execute($this->owner->id, 'USD', '1');
        $this->assertSame('blocked', $next['selection_status']);
        $this->assertSame(['selection_unavailable'], $next['items'][0]['reasons']);
        $this->product->update(['availability_status' => 'available']);
        $this->assertSame('9.00', app(EvaluateCheckout::class)->execute($this->owner->id, 'USD', '1')['pricing']['estimated_items_subtotal']);
    }

    public function test_concurrent_add_commits_without_locking_or_partial_evaluation(): void
    {
        $this->race('catalog', fn () => app(CartOperations::class)->add($this->owner->id, $this->product->id, null, 2));
        $next = app(EvaluateCheckout::class)->execute($this->owner->id, 'USD', '2');
        $this->assertSame(3, $next['items'][0]['quantity']);
        $this->assertSame('6.00', $next['pricing']['estimated_items_subtotal']);
        $this->assertSame(1, Cart::query()->count());
        $this->assertSame(1, DB::table('cart_items')->count());
    }
}
