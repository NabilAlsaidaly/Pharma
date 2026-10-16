<?php

namespace Tests\Feature\Gifts;

use App\Domains\Catalog\Infrastructure\Persistence\Models\Brand;
use App\Domains\Catalog\Infrastructure\Persistence\Models\Product;
use App\Domains\Gifts\Application\ManageGiftPackage;
use App\Domains\Gifts\Application\SubmitGiftRequest;
use App\Domains\Gifts\Infrastructure\GiftStore;
use App\Domains\Identity\Infrastructure\Persistence\Models\Customer;
use App\Domains\ManagementIdentity\Application\ProvisionOwner;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class GiftConcurrencyTest extends TestCase
{
    use DatabaseMigrations { runDatabaseMigrations as private migrateIsolated; }

    private $owner;

    private string $mediaRoot;

    private string $package;

    private string $customer;

    public function runDatabaseMigrations(): void
    {
        if (! app()->environment('testing') || config('database.default') !== 'mysql' || config('database.connections.mysql.database') !== 'sumaya_pharma_test' || DB::selectOne('SELECT DATABASE() AS name')->name !== 'sumaya_pharma_test') {
            throw new \RuntimeException('Gift races require isolated physical MySQL.');
        }
        $this->migrateIsolated();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = app(ProvisionOwner::class)->execute('Gift Race Owner', 'gift-race@example.test', 'Gift-fixture-passphrase-2026', 'Gift-fixture-passphrase-2026');
        $this->mediaRoot = storage_path('framework/testing/gift-race-media-'.Str::uuid7());
        config(['filesystems.disks.local.root' => $this->mediaRoot]);
        $this->customer = Customer::query()->create(['google_sub' => 'gift-race', 'name' => 'Gift Customer', 'email' => 'gift-customer@example.test', 'account_status' => 'active'])->id;
        $this->package = app(ManageGiftPackage::class)->execute(null, $this->owner, $this->command('0', 'save', ['slug' => 'race-gift', 'values' => ['name' => 'باقة سباق', 'price_usd' => '25.00', 'availability' => 'available'], 'components' => [['type' => 'manual', 'name' => 'صندوق', 'quantity' => 1, 'sort_order' => 0]]]));
        app(ManageGiftPackage::class)->execute($this->package, $this->owner, $this->command('1', 'media', ['alt_text' => 'باقة']), UploadedFile::fake()->image('race.png', 20, 20));
        app(ManageGiftPackage::class)->execute($this->package, $this->owner, $this->command('2', 'publish'));
    }

    protected function tearDown(): void
    {
        if (isset($this->mediaRoot) && is_dir($this->mediaRoot)) {
            $allowed = realpath(storage_path('framework/testing'));
            $expected = realpath($this->mediaRoot);
            $actual = realpath(Storage::disk('local')->path(''));
            if ($allowed === false || $expected === false || $actual !== $expected || ! str_starts_with($expected, $allowed.DIRECTORY_SEPARATOR.'gift-race-media-')) {
                throw new \RuntimeException('Refusing Gift fixture cleanup outside its verified temporary root.');
            }
            Storage::disk('local')->deleteDirectory('gifts');
            if (is_dir($this->mediaRoot)) {
                rmdir($this->mediaRoot);
            }
        }
        parent::tearDown();
    }

    private function command(string $version, string $action, array $extra = []): array
    {
        return ['operation_id' => (string) Str::uuid7(), 'expected_version' => $version, 'action' => $action] + (in_array($action, ['publish', 'unpublish', 'archive'], true) ? ['confirm' => '1'] : []) + $extra;
    }

    private function input(): array
    {
        return ['operation_id' => (string) Str::uuid7(), 'package_id' => $this->package, 'expected_published_version' => (string) DB::table('gift_packages')->where('id', $this->package)->value('published_version'), 'currency' => 'USD', 'recipient' => ['full_name' => 'اسم', 'phone' => '+963123456789', 'governorate' => 'دمشق', 'city' => 'دمشق', 'address' => 'عنوان']];
    }

    private function worker(string $kind, array $input, ?string $id = null): array
    {
        return ['kind' => $kind, 'input' => $input, 'id' => $id ?? $this->package, 'customer' => $this->customer];
    }

    private function overlap(array $payloads, string $table, string $id): array
    {
        $dir = storage_path('framework/testing/gift-workers-'.Str::uuid7());
        mkdir($dir);
        $workers = [];
        $connections = [];
        DB::beginTransaction();
        DB::table($table)->where('id', $id)->lockForUpdate()->first();
        try {
            foreach ($payloads as $i => $payload) {
                $ready = $dir.'/ready-'.$i;
                $path = $dir.'/payload-'.$i.'.json';
                file_put_contents($path, json_encode($payload + ['owner' => $this->owner->id, 'ready' => $ready, 'media_root' => $this->mediaRoot], JSON_THROW_ON_ERROR));
                $worker = new Process([PHP_BINARY, base_path('tests/Support/gift-worker.php'), $path], base_path(), null, null, 60);
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
                        $blocked = $active !== null && str_contains((string) $active->Info, '`'.$table.'`') && str_contains((string) $active->Info, 'for update');
                    }
                    if (! $blocked) {
                        usleep(10000);
                    }
                } while (! $blocked && microtime(true) < $deadline);
                $this->assertTrue($blocked, 'Independent worker must be blocked in physical locking SQL.');
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
            fwrite(STDOUT, 'GIFT_REAL_OVERLAP '.json_encode(['barrier' => $table, 'connections' => $connections, 'kinds' => array_column($payloads, 'kind'), 'results' => $results], JSON_THROW_ON_ERROR).PHP_EOL);

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
            foreach (glob($dir.'/*') ?: [] as $file) {
                unlink($file);
            }
            rmdir($dir);
        }
    }

    public static function adminRaces(): array
    {
        return [['edit'], ['publication'], ['composition'], ['media']];
    }

    #[DataProvider('adminRaces')]
    public function test_conflicting_admin_writes_preserve_one_complete_revision(string $kind): void
    {
        $a = $this->command('3', 'save', ['values' => ['name' => 'أول']]);
        $b = $this->command('3', 'save', ['values' => ['name' => 'ثان']]);
        if ($kind === 'publication') {
            $a = $this->command('3', 'publish');
            $b = $this->command('3', 'unpublish');
        }
        if ($kind === 'composition') {
            $a = $this->command('3', 'publish');
            $b = $this->command('3', 'save', ['components' => [['type' => 'manual', 'name' => 'تركيب جديد', 'quantity' => 3, 'sort_order' => 0]]]);
        }
        $payloads = [$this->worker('package', $a), $this->worker('package', $b)];
        if ($kind === 'media') {
            $sourceImage = UploadedFile::fake()->image('race.png');
            $file = $sourceImage->getPathname();
            $payloads = [$this->worker('package', $this->command('3', 'media', ['alt_text' => 'بديل أول'])) + ['file' => $file], $this->worker('package', $this->command('3', 'media', ['alt_text' => 'بديل ثان'])) + ['file' => $file]];
        }
        $results = $this->overlap($payloads, 'management_users', $this->owner->id);
        $this->assertSame(1, count(array_filter($results, fn ($r) => $r['success'] ?? false)));
        $this->assertSame(1, count(array_filter($results, fn ($r) => ($r['refused'] ?? null) === 409)));
        $this->assertDatabaseCount('gift_package_revisions', 4);
        $this->assertDatabaseCount('gift_change_events', 4);
        $p = DB::table('gift_packages')->where('id', $this->package)->first();
        if ($p->published_version !== null) {
            $r = GiftStore::revision($p->id, (string) $p->published_version);
            $this->assertSame('صندوق', $r['components'][0]['name']);
            $this->assertCount(1, $r['images']);
            $this->assertFileExists($this->mediaRoot.'/gifts/'.$r['images'][0]['media_id'].'.png');
        }
    }

    public static function requestRaces(): array
    {
        return [['duplicate'], ['conflicting'], ['replay'], ['independent']];
    }

    #[DataProvider('requestRaces')]
    public function test_customer_operations_are_atomic_and_durable(string $kind): void
    {
        $a = $this->input();
        $b = $a;
        if ($kind === 'independent') {
            $b['operation_id'] = (string) Str::uuid7();
        }
        if ($kind === 'conflicting') {
            $b['message'] = 'مختلف';
        }
        if ($kind === 'replay') {
            app(SubmitGiftRequest::class)->execute($this->customer, $a);
            app(ManageGiftPackage::class)->execute($this->package, $this->owner, $this->command('3', 'unpublish'));
            DB::table('customers')->where('id', $this->customer)->update(['email' => 'changed@example.test']);
        }
        $results = $this->overlap([$this->worker('request', $a), $this->worker('request', $b)], 'customers', $this->customer);
        $success = array_values(array_filter($results, fn ($r) => $r['success'] ?? false));
        $this->assertCount($kind === 'conflicting' ? 1 : 2, $success);
        if (count($success) === 2 && $kind !== 'independent') {
            $this->assertSame($success[0]['result']['id'], $success[1]['result']['id']);
        }
        if ($kind === 'independent') {
            $this->assertNotSame($success[0]['result']['id'], $success[1]['result']['id']);
        }
        $this->assertDatabaseCount('gift_requests', $kind === 'independent' ? 2 : 1);
        $this->assertDatabaseCount('gift_request_components', $kind === 'independent' ? 2 : 1);
    }

    public function test_catalog_archival_and_submission_have_coherent_serialization(): void
    {
        $brand = Brand::query()->create(['name' => 'علامة', 'slug' => 'gift-race-brand', 'status' => 'active']);
        $product = Product::query()->create(['brand_id' => $brand->id, 'name' => 'منتج', 'slug' => 'gift-race-product', 'status' => 'active', 'availability_status' => 'available']);
        app(ManageGiftPackage::class)->execute($this->package, $this->owner, $this->command('3', 'save', ['components' => [['type' => 'catalog', 'product_id' => $product->id, 'quantity' => 1, 'sort_order' => 0]]]));
        app(ManageGiftPackage::class)->execute($this->package, $this->owner, $this->command('4', 'publish'));
        $results = $this->overlap([$this->worker('archive_product', [], $product->id), $this->worker('request', $this->input())], 'products', $product->id);
        $this->assertTrue($results[0]['success']);
        $this->assertSame(($results[1]['success'] ?? false) ? 1 : 0, DB::table('gift_requests')->count());
        if (! ($results[1]['success'] ?? false)) {
            $this->assertSame(409, $results[1]['refused']);
        }
        $this->assertNotNull($product->fresh()->deleted_at);
    }

    public function test_unpublication_and_submission_cannot_mix_publication_facts(): void
    {
        $results = $this->overlap([$this->worker('package', $this->command('3', 'unpublish')), $this->worker('request', $this->input())], 'gift_packages', $this->package);
        $this->assertTrue($results[0]['success']);
        $this->assertSame(($results[1]['success'] ?? false) ? 1 : 0, DB::table('gift_requests')->count());
        $this->assertNull(DB::table('gift_packages')->where('id', $this->package)->value('published_version'));
    }

    public function test_owner_review_tabs_do_not_lose_events_or_mutate_submission(): void
    {
        $rid = app(SubmitGiftRequest::class)->execute($this->customer, $this->input())['id'];
        $original = DB::table('gift_requests')->where('id', $rid)->first();
        $a = ['operation_id' => (string) Str::uuid7(), 'expected_version' => '0', 'kind' => 'transition', 'target' => 'under_review'];
        $b = array_replace($a, ['operation_id' => (string) Str::uuid7(), 'target' => 'awaiting_contact']);
        $results = $this->overlap([$this->worker('review', $a, $rid), $this->worker('review', $b, $rid)], 'management_users', $this->owner->id);
        $this->assertSame(1, count(array_filter($results, fn ($r) => $r['success'] ?? false)));
        $this->assertDatabaseCount('gift_review_events', 1);
        $this->assertEquals($original, DB::table('gift_requests')->where('id', $rid)->first());
    }
}
