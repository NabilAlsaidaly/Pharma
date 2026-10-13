<?php

use App\Domains\Cart\Application\CartOperations;
use App\Domains\Cart\Domain\Exceptions\CartConflict;
use App\Domains\Orders\Application\SubmitPreliminaryCheckoutRequest;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';
putenv('APP_ENV=testing');
$app = require __DIR__.'/../../bootstrap/app.php';
$app->loadEnvironmentFrom('.env.testing');
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing') || config('database.default') !== 'mysql'
    || config('database.connections.mysql.database') !== 'sumaya_pharma_test'
    || DB::selectOne('SELECT DATABASE() AS name')->name !== 'sumaya_pharma_test') {
    throw new RuntimeException('Unsafe preliminary request worker database.');
}
$p = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
file_put_contents($p['ready'], (string) DB::selectOne('SELECT CONNECTION_ID() AS id')->id);
$headerInserted = false;
if (($p['action'] ?? '') === 'failure') {
    DB::listen(function (QueryExecuted $query) use (&$headerInserted): void {
        if (str_starts_with($query->sql, 'insert into `checkout_intents`')) {
            $headerInserted = true;
            // Real SQL failure after header insertion, before the normal child bulk insert.
            DB::statement('INSERT INTO checkout_intent_items (quantity) VALUES (0)');
        }
    });
}
try {
    if (($p['action'] ?? '') === 'cart') {
        $result = $app->make(CartOperations::class)->add($p['customer'], $p['product'], null, 1);
        echo json_encode(['cart_version' => $result['cart_version']], JSON_THROW_ON_ERROR);
    } else {
        $result = $app->make(SubmitPreliminaryCheckoutRequest::class)->execute($p['customer'], $p['input']);
        echo json_encode(['id' => $result['intent']->id, 'created' => $result['created']], JSON_THROW_ON_ERROR);
    }
} catch (CartConflict $e) {
    echo json_encode(['conflict' => $e->getMessage()], JSON_THROW_ON_ERROR);
} catch (QueryException $e) {
    if (($p['action'] ?? '') !== 'failure') {
        throw $e;
    }
    echo json_encode(['injected_database_failure' => true, 'header_inserted_before_failure' => $headerInserted,
        'mysql_code' => $e->errorInfo[1], 'transaction_level' => DB::transactionLevel()], JSON_THROW_ON_ERROR);
}
