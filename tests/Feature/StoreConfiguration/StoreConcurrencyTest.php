<?php

namespace Tests\Feature\StoreConfiguration;

use App\Domains\Cart\Application\CartOperations;
use App\Domains\Catalog\Infrastructure\Persistence\Models\Brand;
use App\Domains\Catalog\Infrastructure\Persistence\Models\Product;
use App\Domains\Content\Application\ManageStoreContent;
use App\Domains\Identity\Infrastructure\Persistence\Models\Customer;
use App\Domains\ManagementIdentity\Application\ProvisionOwner;
use App\Domains\Orders\Application\SubmitPreliminaryCheckoutRequest;
use App\Domains\Settings\Application\ManageStoreSettings;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class StoreConcurrencyTest extends TestCase
{
    use DatabaseMigrations { runDatabaseMigrations as private migrateIsolated; }

    private $owner;

    public function runDatabaseMigrations(): void
    {
        if (! app()->environment('testing') || config('database.default') !== 'mysql' || config('database.connections.mysql.database') !== 'sumaya_pharma_test' || DB::selectOne('SELECT DATABASE() AS name')->name !== 'sumaya_pharma_test') {
            throw new \RuntimeException('Store races require isolated physical MySQL.');
        }
        $this->migrateIsolated();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = app(ProvisionOwner::class)->execute('Store Race Owner', 'store-race@example.test', 'Store-fixture-passphrase-2026', 'Store-fixture-passphrase-2026');
    }

    private function payload(string $domain = 'settings', string $type = 'profile', ?string $id = null, string $version = '0', string $action = 'save', array $values = ['name' => 'اسم اختبار']): array
    {
        return compact('domain', 'type', 'id') + ['command' => ['operation_id' => (string) Str::uuid7(), 'expected_version' => $version, 'action' => $action, 'values' => $values] + (in_array($action, ['publish', 'unpublish', 'archive', 'restore'], true) ? ['confirm' => '1'] : [])];
    }

    private function overlap(array $payloads, ?string $customer = null): array
    {
        $dir = storage_path('framework/testing/store-race-'.Str::uuid7());
        mkdir($dir);
        $workers = [];
        $connections = [];
        DB::beginTransaction();
        DB::table('management_users')->where('id', $this->owner->id)->lockForUpdate()->first();
        if ($customer !== null) {
            DB::table('customers')->where('id', $customer)->lockForUpdate()->first();
        }
        try {
            foreach ($payloads as $i => $payload) {
                $ready = $dir.'/ready-'.$i;
                $path = $dir.'/payload-'.$i.'.json';
                file_put_contents($path, json_encode($payload + ['owner' => $this->owner->id, 'ready' => $ready], JSON_THROW_ON_ERROR));
                $worker = new Process([PHP_BINARY, base_path('tests/Support/store-configuration-worker.php'), $path], base_path(), null, null, 45);
                $workers[] = $worker;
                $worker->start();
                $deadline = microtime(true) + 25;
                $blocked = false;
                do {
                    if (! $worker->isRunning()) {
                        $this->fail($worker->getErrorOutput().$worker->getOutput());
                    }
                    if (file_exists($ready)) {
                        $connection = (int) file_get_contents($ready);
                        $active = collect(DB::select('SHOW FULL PROCESSLIST'))->first(fn ($row) => (int) $row->Id === $connection);
                        $table = $payload['domain'] === 'submit' ? 'customers' : 'management_users';
                        $blocked = $active !== null && str_contains((string) $active->Info, '`'.$table.'`') && str_contains((string) $active->Info, 'for update');
                    }
                    if (! $blocked) {
                        usleep(10000);
                    }
                } while (! $blocked && microtime(true) < $deadline);
                $this->assertTrue($blocked, 'Independent worker must be executing blocked locking SQL.');
                $connections[] = $connection;
            }
            $this->assertCount(count($payloads), array_unique($connections));
            DB::commit();
            $results = [];
            foreach ($workers as $worker) {
                $worker->wait();
                $this->assertTrue($worker->isSuccessful(), $worker->getErrorOutput());
                $results[] = json_decode($worker->getOutput(), true, 512, JSON_THROW_ON_ERROR);
            }
            fwrite(STDOUT, 'STORE_REAL_OVERLAP '.json_encode(['connections' => $connections, 'types' => array_column($payloads, 'type'), 'results' => $results], JSON_THROW_ON_ERROR).PHP_EOL);

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

    private function oneSuccess(array $results): void
    {
        $this->assertSame(1, count(array_filter($results, fn ($r) => $r['success'] ?? false)));
        $this->assertSame(1, count(array_filter($results, fn ($r) => ($r['refused'] ?? null) === 409)));
    }

    public function test_race_a_initial_singleton_creation_rejects_stale_other_tab(): void
    {
        $this->oneSuccess($this->overlap([$this->payload(), $this->payload(values: ['name' => 'آخر'])]));
        $this->assertDatabaseCount('store_profiles', 1);
        $this->assertDatabaseCount('store_profiles_revisions', 1);
        $this->assertDatabaseCount('store_change_events', 1);
    }

    public function test_race_b_policy_publication_preserves_one_current_authoritative_revision(): void
    {
        $seed = $this->payload('content', 'policies', values: ['title' => 'اختبار', 'slug' => 'privacy', 'body' => 'نص اختبار']);
        $id = app(ManageStoreContent::class)->execute('policies', null, $this->owner, $seed['command']);
        $a = $this->payload('content', 'policies', $id, '1', 'publish', []);
        $b = $this->payload('content', 'policies', $id, '1', 'publish', []);
        $this->oneSuccess($this->overlap([$a, $b]));
        $this->assertDatabaseHas('content_policies', ['id' => $id, 'published_version' => 2, 'lock_version' => 2]);
        $this->assertDatabaseCount('content_policies_revisions', 2);
    }

    public function test_race_c_duplicate_operation_creates_exactly_one_change_and_revision(): void
    {
        $payload = $this->payload();
        $results = $this->overlap([$payload, $payload]);
        $this->assertTrue($results[0]['success']);
        $this->assertTrue($results[1]['success']);
        $this->assertSame($results[0]['result']['id'], $results[1]['result']['id']);
        $this->assertDatabaseCount('store_change_events', 1);
        $this->assertDatabaseCount('store_profiles_revisions', 1);
    }

    public function test_race_d_image_replacement_never_introduces_dangling_published_reference(): void
    {
        Storage::fake('local');
        $id = app(ManageStoreSettings::class)->execute('profile', null, $this->owner, $this->payload()['command']);
        $file = UploadedFile::fake()->image('race.png', 40, 20);
        $root = Storage::disk('local')->path('');
        $a = $this->payload(id: $id, version: '1', action: 'media', values: []) + ['file' => $file->getPathname(), 'media_root' => $root];
        $b = $this->payload(id: $id, version: '1', action: 'media', values: []) + ['file' => $file->getPathname(), 'media_root' => $root];
        $this->oneSuccess($this->overlap([$a, $b]));
        $path = DB::table('store_profiles')->sole()->image_path;
        Storage::disk('local')->assertExists($path);
        $this->assertCount(1, Storage::disk('local')->allFiles('storefront'));
        app(ManageStoreSettings::class)->execute('profile', $id, $this->owner, $this->payload(id: $id, version: '2', action: 'publish', values: [])['command']);
        $this->assertDatabaseHas('store_profiles_revisions', ['record_id' => $id, 'version' => 3, 'image_path' => $path]);
    }

    public function test_race_e_delivery_updates_preserve_one_revision_and_reject_the_stale_fee(): void
    {
        $seed = $this->payload(type: 'zones', values: ['name' => 'اختبار', 'governorate' => 'دمشق', 'sort_order' => '0']);
        $id = app(ManageStoreSettings::class)->execute('zones', null, $this->owner, $seed['command']);
        $this->oneSuccess($this->overlap([$this->payload(type: 'zones', id: $id, version: '1', values: ['fee_usd' => '2.00']), $this->payload(type: 'zones', id: $id, version: '1', values: ['fee_syp' => '300.00'])]));
        $this->assertDatabaseCount('store_delivery_zones_revisions', 2);
        $zone = DB::table('store_delivery_zones')->sole();
        $this->assertTrue(($zone->fee_usd === '2.00' && $zone->fee_syp === null) || ($zone->fee_usd === null && $zone->fee_syp === '300.00'));
    }

    public function test_race_f_customer_committed_retry_and_settings_write_preserve_original_submission(): void
    {
        $brand = Brand::query()->create(['name' => 'Fixture', 'slug' => 'store-fixture', 'status' => 'active']);
        $product = Product::query()->create(['name' => 'Fixture', 'slug' => 'store-fixture', 'brand_id' => $brand->id, 'status' => 'active', 'availability_status' => 'available', 'price_usd' => '12']);
        $customer = Customer::query()->create(['name' => 'Fixture', 'email' => 'store-customer@example.test', 'google_sub' => 'store-customer-fixture']);
        app(CartOperations::class)->add($customer->id, $product->id, null, 1);
        $input = ['operation_id' => (string) Str::uuid7(), 'currency' => 'USD', 'expected_cart_version' => '1', 'contact' => ['full_name' => 'Fixture', 'phone' => '+1234567890', 'governorate' => 'دمشق', 'city' => 'دمشق', 'address' => 'Fixture address']];
        $id = app(SubmitPreliminaryCheckoutRequest::class)->execute($customer->id, $input)['intent']->id;
        $before = [DB::table('checkout_intents')->get()->toJson(), DB::table('checkout_intent_items')->get()->toJson()];
        $results = $this->overlap([$this->payload(), ['domain' => 'submit', 'type' => 'checkout_retry', 'customer' => $customer->id, 'input' => $input]], $customer->id);
        $this->assertTrue($results[0]['success']);
        $this->assertSame(['id' => $id, 'created' => false], $results[1]['result']);
        $this->assertSame($before, [DB::table('checkout_intents')->get()->toJson(), DB::table('checkout_intent_items')->get()->toJson()]);
    }
}
