<?php

use App\Domains\Cart\Application\CartOperations;
use App\Domains\Cart\Domain\Exceptions\CartConflict;
use App\Domains\Catalog\Application\Administration\ArchiveCatalogRecord;
use App\Domains\Catalog\Application\Administration\CatalogChange;
use App\Domains\Catalog\Application\Administration\ExecuteCatalogBulkAction;
use App\Domains\Catalog\Application\Administration\ManageCatalogMedia;
use App\Domains\Catalog\Application\Administration\WriteCatalogRecord;
use App\Domains\Catalog\Domain\Exceptions\ConflictingPriceOwnership;
use App\Domains\ManagementIdentity\Infrastructure\Persistence\Models\ManagementUser;
use App\Domains\Orders\Application\SubmitPreliminaryCheckoutRequest;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Events\TransactionBeginning;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

require __DIR__.'/../../vendor/autoload.php';
putenv('APP_ENV=testing');
$app = require __DIR__.'/../../bootstrap/app.php';
$app->loadEnvironmentFrom('.env.testing');
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing') || config('database.default') !== 'mysql' || config('database.connections.mysql.database') !== 'sumaya_pharma_test' || DB::selectOne('SELECT DATABASE() AS name')->name !== 'sumaya_pharma_test') {
    throw new RuntimeException('Unsafe Catalog worker target.');
}
$payload = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
$change = new CatalogChange(ManagementUser::query()->findOrFail($payload['owner']), 'catalog-race', $payload['bulk_id'] ?? null);
file_put_contents($payload['ready'], (string) DB::selectOne('SELECT CONNECTION_ID() AS id')->id);
$outerAttempts = 0;
Event::listen(TransactionBeginning::class, function () use (&$outerAttempts): void {
    if (DB::transactionLevel() === 1) {
        $outerAttempts++;
    }
});
try {
    match ($payload['action']) {
        'write' => app(WriteCatalogRecord::class)->execute($payload['type'], $payload['id'] ?? null, $payload['input'], $change, $payload['parent'] ?? null),
        'archive', 'restore' => app(ArchiveCatalogRecord::class)->execute($payload['type'], $payload['id'], $payload['version'], $payload['action'] === 'restore', $change),
        'primary' => app(ManageCatalogMedia::class)->update($payload['product'], $payload['image'], '1', 0, true, $change),
        'bulk' => app(ExecuteCatalogBulkAction::class)->execute($payload['preview'], $change),
        'add' => app(CartOperations::class)->add($payload['customer'], $payload['product'], $payload['variant'] ?? null, 1),
        'submit' => app(SubmitPreliminaryCheckoutRequest::class)->execute($payload['customer'], $payload['input']),
        'cart_update' => app(CartOperations::class)->update($payload['customer'], $payload['item'], $payload['input']),
    };
    echo json_encode(['success' => true, 'outer_transaction_attempts' => $outerAttempts], JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    $status = $exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : null;
    if ($exception instanceof ConflictingPriceOwnership || $exception instanceof CartConflict || $exception instanceof ModelNotFoundException || $exception instanceof UniqueConstraintViolationException || $exception instanceof ValidationException || in_array($status, [403, 404, 409, 422], true)) {
        echo json_encode(['refused' => $exception::class, 'status' => $status, 'outer_transaction_attempts' => $outerAttempts], JSON_THROW_ON_ERROR);
    } else {
        fwrite(STDERR, $exception::class.': '.$exception->getMessage());
        exit(1);
    }
}
