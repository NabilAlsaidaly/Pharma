<?php

use App\Domains\Content\Application\ManageStoreContent;
use App\Domains\ManagementIdentity\Infrastructure\Persistence\Models\ManagementUser;
use App\Domains\Orders\Application\SubmitPreliminaryCheckoutRequest;
use App\Domains\Settings\Application\ManageStoreSettings;
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
    throw new RuntimeException('Unsafe store race worker target.');
}
$payload = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
if (isset($payload['media_root'])) {
    config(['filesystems.disks.local.root' => $payload['media_root']]);
}
$owner = ManagementUser::query()->findOrFail($payload['owner']);
$attempts = 0;
Event::listen(TransactionBeginning::class, function () use (&$attempts): void {
    if (DB::transactionLevel() === 1) {
        $attempts++;
    }
});
file_put_contents($payload['ready'], (string) DB::selectOne('SELECT CONNECTION_ID() AS id')->id);
try {
    if ($payload['domain'] === 'submit') {
        $submission = app(SubmitPreliminaryCheckoutRequest::class)->execute($payload['customer'], $payload['input']);
        $result = ['id' => $submission['intent']->id, 'created' => $submission['created']];
    } else {
        $file = isset($payload['file']) ? new UploadedFile($payload['file'], 'race.png', 'image/png', null, true) : null;
        $service = $payload['domain'] === 'settings' ? ManageStoreSettings::class : ManageStoreContent::class;
        $result = ['id' => app($service)->execute($payload['type'], $payload['id'], $owner, $payload['command'], $file)];
    }
    echo json_encode(['success' => true, 'result' => $result, 'attempts' => $attempts], JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    if ($exception instanceof HttpExceptionInterface && in_array($exception->getStatusCode(), [409, 422], true)) {
        echo json_encode(['refused' => $exception->getStatusCode(), 'attempts' => $attempts], JSON_THROW_ON_ERROR);
    } else {
        fwrite(STDERR, $exception::class.': '.$exception->getMessage());
        exit(1);
    }
}
