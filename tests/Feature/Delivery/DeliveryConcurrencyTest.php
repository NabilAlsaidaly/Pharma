<?php

namespace Tests\Feature\Delivery;

use App\Domains\Delivery\Application\ManageDeliveryWorker;
use App\Domains\ManagementIdentity\Application\ProvisionOwner;
use App\Domains\Settings\Application\ManageStoreSettings;
use App\Domains\Settings\Infrastructure\PublishedDeliveryZones;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class DeliveryConcurrencyTest extends TestCase
{
    use DatabaseMigrations { runDatabaseMigrations as private migrateIsolated; }

    private $owner;

    public function runDatabaseMigrations(): void
    {
        if (! app()->environment('testing') || config('database.default') !== 'mysql' || config('database.connections.mysql.database') !== 'sumaya_pharma_test' || DB::selectOne('SELECT DATABASE() AS name')->name !== 'sumaya_pharma_test') {
            throw new \RuntimeException('Delivery races require isolated physical MySQL.');
        }
        $this->migrateIsolated();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = app(ProvisionOwner::class)->execute('Delivery Race Owner', 'delivery-race@example.test', 'Delivery-fixture-passphrase-2026', 'Delivery-fixture-passphrase-2026');
    }

    private function command(array $values = [], string $version = '0', string $action = 'save'): array
    {
        return ['operation_id' => (string) Str::uuid7(), 'expected_version' => $version, 'action' => $action, 'values' => $values] + ($action !== 'save' ? ['confirm' => '1'] : []);
    }

    private function overlap(array $payloads, bool $read = false): array
    {
        $dir = storage_path('framework/testing/delivery-workers-'.Str::uuid7());
        mkdir($dir);
        $workers = [];
        $connections = [];
        try {
            foreach ($payloads as $i => $payload) {
                $ready = $dir.'/ready-'.$i;
                $path = $dir.'/payload-'.$i.'.json';
                file_put_contents($path, json_encode($payload + ['owner' => $this->owner->id, 'ready' => $ready], JSON_THROW_ON_ERROR));
                $worker = new Process([PHP_BINARY, base_path('tests/Support/delivery-worker.php'), $path], base_path(), null, null, 60);
                $workers[] = $worker;
                $worker->start();
                $deadline = microtime(true) + 25;
                $blocked = false;
                do {
                    if (file_exists($ready)) {
                        $connection = (int) file_get_contents($ready);
                        if ($read) {
                            $blocked = true;
                        } else {
                            $active = collect(DB::select('SHOW FULL PROCESSLIST'))->first(fn ($row) => (int) $row->Id === $connection);
                            $sql = strtolower((string) ($active->Info ?? ''));
                            $blocked = str_contains($sql, '`management_users`') && str_contains($sql, 'for update');
                        }
                    }
                    if (! $blocked) {
                        usleep(10000);
                    }
                } while (! $blocked && $worker->isRunning() && microtime(true) < $deadline);
                $this->assertTrue($blocked, $worker->getErrorOutput().$worker->getOutput());
                $connections[] = $connection;
            }
            $this->assertCount(count($workers), array_unique($connections));
            $this->assertNotContains((int) DB::selectOne('SELECT CONNECTION_ID() AS id')->id, $connections);
            if (! $read) {
                DB::commit();
            }
            $results = [];
            foreach ($workers as $worker) {
                $worker->wait();
                $this->assertTrue($worker->isSuccessful(), $worker->getErrorOutput());
                $results[] = json_decode($worker->getOutput(), true, 32, JSON_THROW_ON_ERROR);
            }
            fwrite(STDOUT, 'DELIVERY_REAL_OVERLAP '.json_encode(['mode' => $read ? 'uncommitted-writer/MVCC-reader' : 'blocked-owner-writers', 'connections' => $connections, 'results' => $results], JSON_THROW_ON_ERROR).PHP_EOL);

            return $results;
        } finally {
            foreach ($workers as $worker) {
                if ($worker->isRunning()) {
                    $worker->stop();
                }
            }
            $allowed = realpath(storage_path('framework/testing'));
            $target = realpath($dir);
            if (! $allowed || ! $target || ! str_starts_with($target, $allowed.DIRECTORY_SEPARATOR.'delivery-workers-')) {
                throw new \RuntimeException('Refusing unverified fixture cleanup.');
            }
            foreach (glob($target.'/*') ?: [] as $file) {
                unlink($file);
            }
            rmdir($target);
        }
    }

    public static function writes(): array
    {
        return [['edit'], ['identical-create'], ['conflicting-create'], ['different-contacts'], ['settings-worker']];
    }

    #[DataProvider('writes')]
    public function test_real_owner_writers_have_atomic_versions_replay_and_consistent_lock_order(string $kind): void
    {
        $values = ['name' => 'عامل', 'phone' => '123456', 'is_active' => true];
        $id = $kind === 'edit' || $kind === 'settings-worker' ? app(ManageDeliveryWorker::class)->execute(null, $this->owner, $this->command($values)) : null;
        $a = $this->command($values, $id ? '1' : '0');
        $b = $kind === 'identical-create' || $kind === 'conflicting-create' ? $a : $this->command($values, $id ? '1' : '0');
        if ($kind === 'edit' || $kind === 'conflicting-create') {
            $b['values']['name'] = 'عامل آخر';
        }
        $payloads = [['kind' => 'worker', 'id' => $id, 'input' => $a], ['kind' => 'worker', 'id' => $id, 'input' => $b]];
        if ($kind === 'settings-worker') {
            $payloads[1] = ['kind' => 'zones', 'id' => null, 'input' => $this->command(['name' => 'منطقة', 'governorate' => 'حماة', 'sort_order' => '0'])];
        }
        DB::beginTransaction();
        DB::table('management_users')->where('id', $this->owner->id)->lockForUpdate()->first();
        try {
            $results = $this->overlap($payloads);
            $expected = in_array($kind, ['identical-create', 'different-contacts', 'settings-worker'], true) ? 2 : 1;
            $this->assertCount($expected, array_filter($results, fn ($r) => $r['success'] ?? false));
            foreach ($results as $result) {
                $this->assertSame(1, $result['attempts']);
            }
            $this->assertSame($kind === 'different-contacts' || $kind === 'edit' || $kind === 'settings-worker' ? 2 : 1, DB::table('delivery_worker_changes')->count());
            if ($kind === 'identical-create') {
                $this->assertSame($results[0]['result'], $results[1]['result']);
            }
            if ($kind === 'edit') {
                $this->assertSame('2', (string) DB::table('delivery_workers')->where('id', $id)->value('lock_version'));
            }
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
        }
    }

    public static function readers(): array
    {
        return [['publish'], ['archive'], ['unpublish']];
    }

    public function test_owner_revocation_commits_before_a_waiting_worker_command_and_denies_it(): void
    {
        DB::beginTransaction();
        try {
            DB::table('management_users')->where('id', $this->owner->id)->lockForUpdate()->first();
            DB::table('management_users')->where('id', $this->owner->id)->update(['status' => 'disabled']);
            $result = $this->overlap([['kind' => 'worker', 'id' => null, 'input' => $this->command(['name' => 'لا يحفظ', 'phone' => '123456', 'is_active' => true])]])[0];
            $this->assertSame(403, $result['refused']);
            $this->assertSame(1, $result['attempts']);
            $this->assertSame(0, DB::table('delivery_workers')->count());
            $this->assertSame(0, DB::table('delivery_worker_changes')->count());
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
        }
    }

    #[DataProvider('readers')]
    public function test_public_reads_overlap_uncommitted_zone_changes_without_mixed_revisions(string $action): void
    {
        $settings = app(ManageStoreSettings::class);
        $id = $settings->execute('zones', null, $this->owner, $this->command(['name' => 'قبل التغيير', 'governorate' => 'حماة', 'fee_usd' => '1.00', 'fee_syp' => null, 'sort_order' => '0']));
        $settings->execute('zones', $id, $this->owner, $this->command([], '1', 'publish'));
        DB::beginTransaction();
        try {
            if ($action === 'publish') {
                $settings->execute('zones', $id, $this->owner, $this->command(['name' => 'بعد التغيير', 'fee_usd' => null, 'fee_syp' => '0.00'], '2'));
                $settings->execute('zones', $id, $this->owner, $this->command([], '3', 'publish'));
            } else {
                $settings->execute('zones', $id, $this->owner, $this->command([], '2', $action));
            }
            $read = $this->overlap([['kind' => 'read', 'id' => null, 'input' => []]], true)[0];
            $this->assertGreaterThan(0, DB::transactionLevel());
            $this->assertSame('قبل التغيير', $read['result'][0]['name']);
            $this->assertSame('1.00', $read['result'][0]['fee_usd']);
            $this->assertNull($read['result'][0]['fee_syp']);
            DB::commit();
            $after = app(PublishedDeliveryZones::class)->listing([])->items();
            if ($action === 'publish') {
                $this->assertSame('بعد التغيير', $after[0]['name']);
                $this->assertNull($after[0]['fee_usd']);
                $this->assertSame('0.00', $after[0]['fee_syp']);
            } else {
                $this->assertSame([], $after);
            }
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
        }
    }
}
