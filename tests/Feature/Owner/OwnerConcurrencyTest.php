<?php

namespace Tests\Feature\Owner;

use App\Domains\ManagementIdentity\Application\ProvisionOwner;
use App\Domains\ManagementIdentity\Application\UpdateOwnerAccount;
use App\Domains\ManagementIdentity\Infrastructure\Persistence\Models\ManagementUser;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class OwnerConcurrencyTest extends TestCase
{
    use DatabaseMigrations {runDatabaseMigrations as private migrateIsolatedDatabase; }

    public function runDatabaseMigrations(): void
    {
        if (! app()->environment('testing') || config('database.default') !== 'mysql'
            || config('database.connections.mysql.database') !== 'sumaya_pharma_test'
            || DB::selectOne('SELECT DATABASE() AS name')->name !== 'sumaya_pharma_test') {
            throw new \RuntimeException('Owner concurrency requires isolated MySQL.');
        }
        $this->migrateIsolatedDatabase();
    }

    private function overlap(string $action, int $count, ?ManagementUser $owner = null, ?callable $mutation = null): array
    {
        $dir = storage_path('framework/testing/owner-race-'.Str::uuid7());
        mkdir($dir);
        $workers = [];
        $connections = [];
        if ($action !== 'access') {
            DB::beginTransaction();
            // Empty unique slot gap blocks both inserts; existing row blocks both reauthentication reads.
            DB::table('management_users')->where('owner_slot', 1)->lockForUpdate()->first();
        }
        try {
            for ($i = 0; $i < $count; $i++) {
                $ready = $dir.'/ready-'.$i;
                $payload = $dir.'/payload-'.$i.'.json';
                file_put_contents($payload, json_encode(['action' => $action, 'index' => $i, 'id' => $owner?->id,
                    'ready' => $ready, 'resume' => $dir.'/resume'], JSON_THROW_ON_ERROR));
                $worker = new Process([PHP_BINARY, base_path('tests/Support/owner-security-worker.php'), $payload], base_path(), null, null, 30);
                $workers[] = $worker;
                $worker->start();
                $deadline = microtime(true) + 20;
                while (! file_exists($ready)) {
                    if (microtime(true) > $deadline || ! $worker->isRunning()) {
                        $this->fail($worker->getErrorOutput().$worker->getOutput());
                    }
                    usleep(10000);
                }
                $connection = (int) file_get_contents($ready);
                $connections[] = $connection;
                $this->assertNotSame((int) DB::selectOne('SELECT CONNECTION_ID() AS id')->id, $connection);
                if ($action !== 'access') {
                    do {
                        $active = collect(DB::select('SHOW FULL PROCESSLIST'))->first(fn ($row) => (int) $row->Id === $connection);
                        $blocked = $active !== null && str_contains((string) $active->Info, '`management_users`')
                            && str_contains((string) $active->Info, $action === 'provision' ? 'insert into' : 'for update');
                        if (! $blocked) {
                            usleep(10000);
                        }
                    } while (! $blocked && microtime(true) < $deadline);
                    $this->assertTrue($blocked, 'Observe actual blocked SQL on the independent connection.');
                }
            }
            $this->assertCount($count, array_unique($connections));
            if ($mutation !== null) {
                $mutation();
            }
            if ($action === 'access') {
                touch($dir.'/resume');
            } else {
                DB::commit();
            }
            $results = [];
            foreach ($workers as $worker) {
                $worker->wait();
                $this->assertTrue($worker->isSuccessful(), $worker->getErrorOutput());
                $results[] = json_decode($worker->getOutput(), true, 512, JSON_THROW_ON_ERROR);
            }
            fwrite(STDOUT, 'OWNER_REAL_OVERLAP '.json_encode(compact('action', 'connections', 'results')).PHP_EOL);

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

    private function owner(): ManagementUser
    {
        return app(ProvisionOwner::class)->execute('Fixture Owner', 'owner@example.test', 'Fixture-safe-passphrase-2026', 'Fixture-safe-passphrase-2026');
    }

    public function test_two_simultaneous_provisioners_create_exactly_one_complete_owner(): void
    {
        $results = $this->overlap('provision', 2);
        $this->assertSame(1, count(array_filter($results, fn ($r) => $r['created'] ?? false)));
        $this->assertSame(1, count(array_filter($results, fn ($r) => $r['refused'] ?? false)));
        $this->assertDatabaseCount('management_users', 1);
        $owner = ManagementUser::query()->sole();
        $this->assertSame('active', $owner->status);
        $this->assertSame('owner', $owner->role);
        $this->assertTrue(Hash::check('Fixture-safe-passphrase-2026', $owner->password));
        $this->assertDatabaseCount('customers', 0);
    }

    public function test_competing_password_updates_serialize_and_cannot_restore_old_version(): void
    {
        $owner = $this->owner();
        $results = $this->overlap('password', 2, $owner);
        $this->assertSame(1, count(array_filter($results, fn ($r) => ($r['version'] ?? null) === '2')));
        $this->assertSame(1, count(array_filter($results, fn ($r) => ($r['status'] ?? null) === 403)));
        $this->assertSame('2', $owner->fresh()->credential_version);
        $this->assertFalse(Hash::check('Fixture-safe-passphrase-2026', $owner->fresh()->password));
    }

    public static function revocations(): array
    {
        return ['password-change' => ['password'], 'disable' => ['disable']];
    }

    #[DataProvider('revocations')]
    public function test_existing_cached_session_on_another_connection_is_rejected_after_committed_revocation(string $change): void
    {
        $owner = $this->owner();
        $results = $this->overlap('access', 1, $owner, function () use ($owner, $change): void {
            if ($change === 'password') {
                app(UpdateOwnerAccount::class)->password($owner->id, '1', 'Fixture-safe-passphrase-2026', 'Changed-fixture-password');
            } else {
                $owner->forceFill(['status' => 'disabled'])->save();
            }
        });
        $this->assertSame(403, $results[0]['status']);
        $this->assertDatabaseCount('customers', 0);
    }
}
