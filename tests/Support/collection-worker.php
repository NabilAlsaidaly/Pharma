<?php

use App\Domains\Cart\Application\CartOperations;
use App\Domains\Cart\Application\CartSnapshot;
use App\Domains\Cart\Domain\Exceptions\CartConflict;
use App\Domains\Catalog\Application\Ports\CatalogSelectionReader;
use App\Domains\Favorites\Application\FavoriteOperations;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

// Independent integration-test worker. Never permit a development target.
require __DIR__.'/../../vendor/autoload.php';
putenv('APP_ENV=testing');
$app = require __DIR__.'/../../bootstrap/app.php';
$app->loadEnvironmentFrom('.env.testing');
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing') || config('database.default') !== 'mysql' || config('database.connections.mysql.database') !== 'sumaya_pharma_test') {
    fwrite(STDERR, 'Unsafe worker database target.');
    exit(2);
}
$payload = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
$id = DB::selectOne('SELECT CONNECTION_ID() AS id')->id;
file_put_contents($payload['ready'], (string) $id);
try {
    $ops = $app->make(CartOperations::class);
    $fav = $app->make(FavoriteOperations::class);
    if ($payload['operation'] === 'snapshot') {
        $delegate = $app->make(CatalogSelectionReader::class);
        $app->instance(CatalogSelectionReader::class, new class($delegate, $payload) implements CatalogSelectionReader
        {
            public function __construct(private $delegate, private array $payload) {}

            public function read(array $p, array $v, bool $lock = false): array
            {
                file_put_contents($this->payload['snapshot_ready'], 'snapshot');
                $deadline = microtime(true) + 20;
                while (! file_exists($this->payload['resume'])) {
                    if (microtime(true) > $deadline) {
                        throw new RuntimeException('Snapshot coordination timeout.');
                    }usleep(10000);
                }

                return $this->delegate->read($p, $v, $lock);
            }
        });
        $result = $app->make(CartSnapshot::class)->read($payload['customer'], 1, 12);
    } else {
        $result = match ($payload['operation']) {
            'add' => $ops->add($payload['customer'], $payload['product'], $payload['variant'] ?? null, $payload['quantity']),
            'update' => $ops->update($payload['customer'], $payload['item'], $payload['input']),
            'remove' => $ops->remove($payload['customer'], $payload['item']),
            'favorite_add' => $fav->add($payload['customer'], $payload['product']),
            'favorite_remove' => $fav->remove($payload['customer'], $payload['product']),
            'raw_insert' => DB::table('cart_items')->insert($payload['row']),
            default => throw new RuntimeException('Unknown test operation.'),
        };
    }
    echo json_encode(['outcome' => 'success', 'result' => $result], JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    echo json_encode(['outcome' => 'rejected', 'type' => $e::class,
        'mysql_code' => $e instanceof QueryException ? ($e->errorInfo[1] ?? null) : null,
        'reason' => $e instanceof CartConflict ? $e->reason : null], JSON_THROW_ON_ERROR);
}
