<?php

use App\Domains\Catalog\Application\Administration\CatalogChange;
use App\Domains\Catalog\Application\Administration\WriteCatalogRecord;
use App\Domains\Content\Application\Education\ManageEducationalArticle;
use App\Domains\Content\Application\Education\ManageEducationalCategory;
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
    throw new RuntimeException('Unsafe educational worker target.');
}
$p = json_decode(file_get_contents($argv[1]), true, 64, JSON_THROW_ON_ERROR);
$root = realpath($p['media_root']);
$allowed = realpath(storage_path('framework/testing'));
if (! $root || ! $allowed || ! str_starts_with($root, $allowed.DIRECTORY_SEPARATOR.'education-race-media-')) {
    throw new RuntimeException('Unsafe educational media root.');
}
config(['filesystems.disks.local.root' => $root]);
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
        'category' => app(ManageEducationalCategory::class)->execute($p['id'], $owner, $p['input']),
        'product', 'concern' => app(WriteCatalogRecord::class)->execute($p['kind'].'s', $p['id'], $p['input'], new CatalogChange($owner, 'education-race'))->id,
        default => app(ManageEducationalArticle::class)->execute($p['id'], $owner, $p['input'], isset($p['file']) ? new UploadedFile($p['file'], 'race.png', 'image/png', null, true) : null),
    };
    echo json_encode(['success' => true, 'id' => $result, 'attempts' => $attempts], JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    if ($e instanceof HttpExceptionInterface && in_array($e->getStatusCode(), [409, 422], true)) {
        echo json_encode(['refused' => $e->getStatusCode(), 'attempts' => $attempts], JSON_THROW_ON_ERROR);
    } else {
        fwrite(STDERR, $e::class);
        exit(1);
    }
}
