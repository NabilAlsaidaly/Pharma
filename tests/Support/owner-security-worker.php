<?php

use App\Domains\ManagementIdentity\Application\ProvisionOwner;
use App\Domains\ManagementIdentity\Application\UpdateOwnerAccount;
use App\Domains\ManagementIdentity\Infrastructure\Persistence\Models\ManagementUser;
use App\Domains\ManagementIdentity\Interfaces\Http\Middleware\RequireOwner;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

require __DIR__.'/../../vendor/autoload.php';
putenv('APP_ENV=testing');
$app = require __DIR__.'/../../bootstrap/app.php';
$app->loadEnvironmentFrom('.env.testing');
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing') || config('database.default') !== 'mysql'
    || config('database.connections.mysql.database') !== 'sumaya_pharma_test'
    || DB::selectOne('SELECT DATABASE() AS name')->name !== 'sumaya_pharma_test') {
    throw new RuntimeException('Unsafe owner worker database.');
}
$p = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
// Synthetic credentials stay in test source, never in payload files or process arguments.
$password = 'Fixture-safe-passphrase-2026';
if ($p['action'] === 'access') {
    $request = Request::create('/fixture-owner-portal');
    $session = new Store('fixture', new ArraySessionHandler(120));
    $session->start();
    $request->setLaravelSession($session);
    $session->put(['owner_credential_version' => '1', 'owner_last_activity' => now()->timestamp]);
    Auth::guard('owner')->setUser(ManagementUser::query()->findOrFail($p['id']));
}
file_put_contents($p['ready'], (string) DB::selectOne('SELECT CONNECTION_ID() AS id')->id);
if ($p['action'] === 'access') {
    $deadline = microtime(true) + 20;
    while (! file_exists($p['resume'])) {
        if (microtime(true) > $deadline) {
            throw new RuntimeException('Owner barrier timed out.');
        }
        usleep(10000);
    }
}
try {
    $result = match ($p['action']) {
        'provision' => ['created' => (bool) $app->make(ProvisionOwner::class)->execute('Fixture Owner', 'owner-'.$p['index'].'@example.test', $password, $password)],
        'password' => ['version' => $app->make(UpdateOwnerAccount::class)->password($p['id'], '1', $password, 'Changed-fixture-password-'.$p['index'])->credential_version],
        'access' => ['status' => $app->make(RequireOwner::class)->handle($request, fn () => new Response('safe'))->getStatusCode()],
    };
} catch (ValidationException) {
    $result = ['refused' => true];
} catch (HttpException $exception) {
    $result = ['status' => $exception->getStatusCode()];
}
echo json_encode($result, JSON_THROW_ON_ERROR);
