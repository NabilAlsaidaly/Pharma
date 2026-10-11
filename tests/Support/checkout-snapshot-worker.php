<?php

use App\Domains\Catalog\Application\Ports\CatalogSelectionReader;
use App\Domains\Orders\Application\EvaluateCheckout;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';
putenv('APP_ENV=testing');
$app = require __DIR__.'/../../bootstrap/app.php';
$app->loadEnvironmentFrom('.env.testing');
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing') || config('database.default') !== 'mysql'
    || config('database.connections.mysql.database') !== 'sumaya_pharma_test'
    || DB::selectOne('SELECT DATABASE() AS name')->name !== 'sumaya_pharma_test') {
    throw new RuntimeException('Unsafe Checkout worker database.');
}
$payload = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
$pause = function () use ($payload): void {
    file_put_contents($payload['ready'], json_encode(['connection' => DB::selectOne('SELECT CONNECTION_ID() AS id')->id,
        'transaction_level' => DB::transactionLevel()], JSON_THROW_ON_ERROR));
    $deadline = microtime(true) + 20;
    while (! file_exists($payload['resume'])) {
        if (microtime(true) > $deadline) {
            throw new RuntimeException('Checkout snapshot coordination timeout.');
        }
        usleep(10000);
    }
};
if ($payload['boundary'] === 'cart') {
    $paused = false;
    DB::listen(function (QueryExecuted $query) use ($pause, &$paused): void {
        if (! $paused && str_contains($query->sql, 'from `carts`')) {
            $paused = true;
            $pause();
        }
    });
} else {
    $delegate = $app->make(CatalogSelectionReader::class);
    $app->instance(CatalogSelectionReader::class, new class($delegate, $pause) implements CatalogSelectionReader
    {
        public function __construct(private CatalogSelectionReader $delegate, private Closure $pause) {}

        public function read(array $productIds, array $variantIds, bool $lock = false): array
        {
            ($this->pause)();

            return $this->delegate->read($productIds, $variantIds, $lock);
        }
    });
}
echo json_encode($app->make(EvaluateCheckout::class)->execute($payload['customer'], 'USD', '1'), JSON_THROW_ON_ERROR);
