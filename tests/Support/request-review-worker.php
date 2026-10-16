<?php

use App\Domains\ManagementIdentity\Infrastructure\Persistence\Models\ManagementUser;
use App\Domains\Orders\Application\Review\BulkReviewRequests;
use App\Domains\Orders\Application\Review\ReviewRequestAction;
use App\Domains\Orders\Application\SubmitPreliminaryCheckoutRequest;
use App\Domains\Orders\Infrastructure\Persistence\Queries\OwnerRequestReviewReader;
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
    throw new RuntimeException('Unsafe review worker target.');
}
$payload = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
$owner = ManagementUser::query()->findOrFail($payload['owner']);
file_put_contents($payload['ready'], (string) DB::selectOne('SELECT CONNECTION_ID() AS id')->id);
$attempts = 0;
Event::listen(TransactionBeginning::class, function () use (&$attempts): void {
    if (DB::transactionLevel() === 1) {
        $attempts++;
    }
});
try {
    if ($payload['action'] === 'read_snapshot') {
        $paused = false;
        DB::listen(function ($query) use ($payload, &$paused): void {
            if (! $paused && str_contains($query->sql, 'from `checkout_intents`') && str_contains($query->sql, 'select')) {
                $paused = true;
                file_put_contents($payload['snapshot_ready'], 'snapshot');
                $deadline = microtime(true) + 25;
                while (! file_exists($payload['release']) && microtime(true) < $deadline) {
                    usleep(10000);
                }
                if (! file_exists($payload['release'])) {
                    throw new RuntimeException('Snapshot release timeout.');
                }
            }
        });
        $data = app(OwnerRequestReviewReader::class)->detail($payload['id']);
        $result = ['version' => $data['review']->lock_version ?? '0', 'events' => $data['events']->total(), 'submitted' => $data['record']->status];
    } elseif ($payload['action'] === 'bulk') {
        $result = ['count' => app(BulkReviewRequests::class)->execute($payload['preview'], $owner, 'real-overlap')];
    } elseif ($payload['action'] === 'submit') {
        $result = app(SubmitPreliminaryCheckoutRequest::class)->execute($payload['customer'], $payload['input']);
        $result = ['id' => $result['intent']->id, 'created' => $result['created']];
    } else {
        $event = app(ReviewRequestAction::class)->execute($payload['id'], $owner, $payload['operation'], $payload['version'], $payload['kind'], $payload['target'] ?? null, $payload['note'] ?? null);
        $result = ['event' => $event->id, 'version' => $event->resulting_version];
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
