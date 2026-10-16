<?php

use App\Domains\Catalog\Application\Administration\ArchiveCatalogRecord;
use App\Domains\Catalog\Application\Administration\CatalogChange;
use App\Domains\Gifts\Application\ManageGiftPackage;
use App\Domains\Gifts\Application\ReviewGiftRequest;
use App\Domains\Gifts\Application\SubmitGiftRequest;
use App\Domains\ManagementIdentity\Infrastructure\Persistence\Models\ManagementUser;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Events\TransactionBeginning;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

require __DIR__.'/../../vendor/autoload.php';
putenv('APP_ENV=testing');
$app = require __DIR__.'/../../bootstrap/app.php';
$app->loadEnvironmentFrom('.env.testing');
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing') || config('database.default') !== 'mysql' || config('database.connections.mysql.database') !== 'sumaya_pharma_test' || DB::selectOne('SELECT DATABASE() AS name')->name !== 'sumaya_pharma_test') {
    throw new RuntimeException('Unsafe Gift worker target.');
}
$p = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
config(['filesystems.disks.local.root' => $p['media_root']]);
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
        'request' => app(SubmitGiftRequest::class)->execute($p['customer'], $p['input']),
        'review' => ['id' => app(ReviewGiftRequest::class)->execute($p['id'], $owner, $p['input'])],
        'archive_product' => (function () use ($p, $owner): array {
            app(ArchiveCatalogRecord::class)->execute('products', $p['id'], '1', false, new CatalogChange($owner, 'gift-race'));

            return ['archived' => true];
        })(),
        default => ['id' => app(ManageGiftPackage::class)->execute($p['id'], $owner, $p['input'], isset($p['file']) ? new UploadedFile($p['file'], 'race.png', 'image/png', null, true) : null)],
    };
    echo json_encode(['success' => true, 'result' => $result, 'attempts' => $attempts], JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    if ($e instanceof HttpExceptionInterface && in_array($e->getStatusCode(), [409, 422], true)) {
        echo json_encode(['refused' => $e->getStatusCode(), 'attempts' => $attempts], JSON_THROW_ON_ERROR);
    } else {
        fwrite(STDERR, $e::class.': '.$e->getMessage());
        exit(1);
    }
}
