<?php

namespace Tests\Feature\Checkout;

use App\Domains\Cart\Application\CartOperations;
use App\Domains\Catalog\Infrastructure\Persistence\Models\Brand;
use App\Domains\Catalog\Infrastructure\Persistence\Models\Product;
use App\Domains\Identity\Infrastructure\Persistence\Models\Customer;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class PreliminaryCheckoutConcurrencyTest extends TestCase
{
    use DatabaseMigrations {runDatabaseMigrations as private migrateIsolatedDatabase; }

    private Customer $owner;

    private Product $product;

    public function runDatabaseMigrations(): void
    {
        if (! app()->environment('testing') || config('database.default') !== 'mysql'
            || config('database.connections.mysql.database') !== 'sumaya_pharma_test'
            || DB::selectOne('SELECT DATABASE() AS name')->name !== 'sumaya_pharma_test') {
            throw new \RuntimeException('Preliminary request concurrency requires isolated MySQL.');
        }
        $this->migrateIsolatedDatabase();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = Customer::query()->create(['google_sub' => 'intent-race', 'name' => 'Fixture', 'email' => 'fixture@example.test']);
        $brand = Brand::query()->create(['name' => 'Fixture', 'slug' => 'intent-brand', 'status' => 'active']);
        $this->product = Product::query()->create(['brand_id' => $brand->id, 'name' => 'Fixture', 'slug' => 'intent-product',
            'status' => 'active', 'availability_status' => 'available', 'price_usd' => '2']);
        app(CartOperations::class)->add($this->owner->id, $this->product->id, null, 1);
    }

    private function input(): array
    {
        return ['operation_id' => (string) Str::uuid7(), 'currency' => 'USD', 'expected_cart_version' => '1',
            'contact' => ['full_name' => 'Fixture', 'phone' => '+123456', 'governorate' => 'Damascus', 'city' => 'Damascus', 'address' => 'Street 1', 'notes' => null]];
    }

    private function overlap(array $payloads, string $table = 'customers', ?callable $mutation = null): array
    {
        $dir = storage_path('framework/testing/intent-race-'.Str::uuid7());
        mkdir($dir);
        $workers = [];
        DB::beginTransaction();
        DB::table($table)->where('id', $table === 'customers' ? $this->owner->id : $this->product->id)->lockForUpdate()->first();
        try {
            $connections = [];
            foreach ($payloads as $i => $payload) {
                $ready = $dir.'/ready-'.$i;
                $path = $dir.'/payload-'.$i.'.json';
                file_put_contents($path, json_encode($payload + ['customer' => $this->owner->id, 'ready' => $ready], JSON_THROW_ON_ERROR));
                $workers[$i] = new Process([PHP_BINARY, base_path('tests/Support/preliminary-checkout-worker.php'), $path], base_path(), null, null, 30);
                $workers[$i]->start();
                $deadline = microtime(true) + 20;
                while (! file_exists($ready)) {
                    if (microtime(true) > $deadline || ! $workers[$i]->isRunning()) {
                        $this->fail($workers[$i]->getErrorOutput().$workers[$i]->getOutput());
                    }
                    usleep(10000);
                }
                $connection = (int) file_get_contents($ready);
                $connections[] = $connection;
                // Observe the real blocking locking query, not just process readiness.
                do {
                    $active = collect(DB::select('SHOW FULL PROCESSLIST'))->first(fn ($row) => (int) $row->Id === $connection);
                    $blocked = $active !== null && str_contains((string) $active->Info, 'from `'.$table.'`') && str_contains((string) $active->Info, 'for update');
                    if (! $blocked) {
                        usleep(10000);
                    }
                } while (! $blocked && microtime(true) < $deadline);
                $this->assertTrue($blocked, 'Worker must overlap the parent transaction at the intended row lock.');
            }
            $this->assertCount(count($connections), array_unique($connections));
            foreach ($connections as $connection) {
                $this->assertNotSame((int) DB::selectOne('SELECT CONNECTION_ID() AS id')->id, $connection);
            }
            if ($mutation !== null) {
                $mutation();
            }
            DB::commit();
            $results = [];
            foreach ($workers as $worker) {
                $worker->wait();
                $this->assertTrue($worker->isSuccessful(), $worker->getErrorOutput());
                $results[] = json_decode($worker->getOutput(), true, 512, JSON_THROW_ON_ERROR);
            }
            fwrite(STDOUT, 'PRELIMINARY_REAL_OVERLAP '.json_encode(['lock' => $table, 'worker_connections' => $connections, 'results' => $results]).PHP_EOL);

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

    public function test_same_operation_two_connections_create_one_complete_request(): void
    {
        $p = $this->input();
        $results = $this->overlap([['input' => $p], ['input' => $p]]);
        $this->assertSame($results[0]['id'], $results[1]['id']);
        $this->assertSame(1, count(array_filter($results, fn ($r) => $r['created'])));
        $this->assertDatabaseCount('checkout_intents', 1);
        $this->assertDatabaseCount('checkout_intent_items', 1);
    }

    public function test_distinct_operations_serialize_and_create_two_complete_requests(): void
    {
        $results = $this->overlap([['input' => $this->input()], ['input' => $this->input()]]);
        $this->assertNotSame($results[0]['id'], $results[1]['id']);
        $this->assertDatabaseCount('checkout_intents', 2);
        $this->assertDatabaseCount('checkout_intent_items', 2);
    }

    public function test_cart_writer_and_submission_have_coherent_or_stale_outcome(): void
    {
        $results = $this->overlap([['action' => 'cart', 'product' => $this->product->id], ['input' => $this->input()]]);
        $this->assertSame('2', $results[0]['cart_version']);
        if (isset($results[1]['id'])) {
            $this->assertSame(1, DB::table('checkout_intent_items')->sole()->quantity);
            $this->assertSame(1, DB::table('checkout_intents')->sole()->source_cart_version);
        } else {
            $this->assertArrayHasKey('conflict', $results[1]);
            $this->assertDatabaseCount('checkout_intents', 0);
            $this->assertDatabaseCount('checkout_intent_items', 0);
        }
    }

    public static function catalogChanges(): array
    {
        return ['hidden' => [['status' => 'hidden']], 'unavailable' => [['availability_status' => 'unavailable']],
            'zero-price' => [['price_usd' => '0']], 'missing-price' => [['price_usd' => null]]];
    }

    #[DataProvider('catalogChanges')]
    public function test_catalog_visibility_change_while_waiting_blocks_submission_atomically(array $change): void
    {
        $result = $this->overlap([['input' => $this->input()]], 'products', fn () => $this->product->update($change));
        $this->assertArrayHasKey('conflict', $result[0]);
        $this->assertDatabaseCount('checkout_intents', 0);
        $this->assertDatabaseCount('checkout_intent_items', 0);
    }

    public function test_catalog_variant_creation_while_waiting_invalidates_base_selection(): void
    {
        $result = $this->overlap([['input' => $this->input()]], 'products', fn () => DB::table('product_variants')->insert([
            'id' => (string) Str::uuid7(), 'product_id' => $this->product->id, 'name' => 'New', 'availability_status' => 'available',
            'price_usd' => '3', 'created_at' => now(), 'updated_at' => now()]));
        $this->assertArrayHasKey('conflict', $result[0]);
        $this->assertDatabaseCount('checkout_intents', 0);
    }

    public function test_lost_response_replayed_on_another_connection_after_source_changes(): void
    {
        $p = $this->input();
        $first = $this->overlap([['input' => $p]])[0];
        // The client discards this committed response and retries the original operation.
        DB::table('cart_items')->delete();
        DB::table('carts')->update(['version' => 2]);
        $this->product->update(['status' => 'hidden', 'price_usd' => null]);
        $this->owner->update(['email' => 'changed@example.test']);
        $second = $this->overlap([['input' => $p]])[0];
        $this->assertSame($first['id'], $second['id']);
        $this->assertFalse($second['created']);
        $this->assertSame('fixture@example.test', DB::table('checkout_intents')->sole()->email);
        $this->assertDatabaseCount('checkout_intents', 1);
        $this->assertDatabaseCount('checkout_intent_items', 1);
    }

    public function test_database_failure_on_independent_connection_rolls_back_header_and_items(): void
    {
        $before = [DB::table('customers')->get()->toArray(), DB::table('carts')->get()->toArray(), DB::table('cart_items')->get()->toArray()];
        $result = $this->overlap([['action' => 'failure', 'input' => $this->input()]])[0];
        $this->assertTrue($result['injected_database_failure']);
        $this->assertTrue($result['header_inserted_before_failure']);
        $this->assertSame(1364, $result['mysql_code']);
        $this->assertSame(0, $result['transaction_level']);
        $this->assertDatabaseCount('checkout_intents', 0);
        $this->assertDatabaseCount('checkout_intent_items', 0);
        $this->assertEquals($before, [DB::table('customers')->get()->toArray(), DB::table('carts')->get()->toArray(), DB::table('cart_items')->get()->toArray()]);
    }
}
