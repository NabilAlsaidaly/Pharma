<?php

use App\Domains\Delivery\Application\ManageDeliveryWorker;
use App\Domains\ManagementIdentity\Infrastructure\Persistence\Models\ManagementUser;
use App\Domains\Settings\Application\ManageStoreSettings;
use App\Domains\Settings\Infrastructure\PublishedDeliveryZones;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Events\TransactionBeginning;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

require __DIR__.'/../../vendor/autoload.php';
putenv('APP_ENV=testing');
$app = require __DIR__.'/../../bootstrap/app.php';
$app->loadEnvironmentFrom('.env.testing');
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing') || config('database.default') !== 'mysql' || config('database.connections.mysql.database') !== 'sumaya_pharma_test' || DB::selectOne('SELECT DATABASE() AS name')->name !== 'sumaya_pharma_test') {
    throw new RuntimeException('Unsafe delivery worker database.');
}
$path = realpath($argv[1]);
$allowed = realpath(storage_path('framework/testing'));
if (! $path || ! $allowed || ! str_starts_with($path, $allowed.DIRECTORY_SEPARATOR.'delivery-workers-')) {
    throw new RuntimeException('Unsafe delivery fixture path.');
}
$p = json_decode(file_get_contents($path), true, 32, JSON_THROW_ON_ERROR);
if (realpath(dirname($p['ready'])) !== dirname($path) || ! preg_match('/^ready-[0-9]+$/D', basename($p['ready']))) {
    throw new RuntimeException('Unsafe readiness path.');
}
$owner = ManagementUser::query()->findOrFail($p['owner']);
$attempts = 0;
Event::listen(TransactionBeginning::class, function () use (&$attempts): void {
    if (DB::transactionLevel() === 1) {
        $attempts++;
    }
});
file_put_contents($p['ready'], (string) DB::selectOne('SELECT CONNECTION_ID() AS id')->id);
try {
    $result = match ($p['kind']) {
        'zones' => app(ManageStoreSettings::class)->execute('zones', $p['id'], $owner, $p['input']),
        'read' => app(PublishedDeliveryZones::class)->listing([])->items(),
        default => app(ManageDeliveryWorker::class)->execute($p['id'], $owner, $p['input']),
    };
    echo json_encode(['success' => true, 'result' => $result, 'attempts' => $attempts], JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    if ($e instanceof HttpExceptionInterface && in_array($e->getStatusCode(), [403, 409, 422], true)) {
        echo json_encode(['refused' => $e->getStatusCode(), 'attempts' => $attempts], JSON_THROW_ON_ERROR);
    } else {
        fwrite(STDERR, $e::class);
        exit(1);
    }
}
