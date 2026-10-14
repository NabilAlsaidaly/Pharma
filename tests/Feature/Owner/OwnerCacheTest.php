<?php

namespace Tests\Feature\Owner;

use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class OwnerCacheTest extends TestCase
{
    public static function prefixes(): array
    {
        return ['configured' => ['fixture-cache-portal', 17], 'missing' => ['', 0], 'reserved' => ['admin', 0]];
    }

    #[DataProvider('prefixes')]
    public function test_real_config_and_route_cache_preserve_fail_closed_registration(string $prefix, int $count): void
    {
        $relative = 'storage/framework/testing/owner-cache-'.Str::uuid7();
        $dir = base_path($relative);
        mkdir($dir);
        $env = ['APP_ENV' => 'testing', 'OWNER_PORTAL_PREFIX' => $prefix, 'DB_DATABASE' => 'sumaya_pharma_test',
            'APP_CONFIG_CACHE' => $relative.'/config.php', 'APP_ROUTES_CACHE' => $relative.'/routes.php'];
        try {
            foreach (['config:cache', 'route:cache'] as $command) {
                $process = new Process([PHP_BINARY, 'artisan', $command], base_path(), $env, null, 30);
                $process->mustRun();
            }
            $code = 'require "vendor/autoload.php"; $app=require "bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); echo json_encode(["prefix"=>config("owner_portal.prefix"),"count"=>count(array_filter(Illuminate\Support\Facades\Route::getRoutes()->getRoutes(),fn($r)=>str_starts_with((string)$r->getName(),"owner.")))]);';
            $process = new Process([PHP_BINARY, '-r', $code], base_path(), $env, null, 30);
            $process->mustRun();
            $result = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
            $this->assertSame($count, $result['count']);
            $this->assertSame($prefix, $result['prefix'] ?? '');
            $this->assertFileExists($dir.'/config.php');
            $this->assertFileExists($dir.'/routes.php');
        } finally {
            foreach (glob($dir.'/*') ?: [] as $file) {
                unlink($file);
            }
            rmdir($dir);
        }
    }
}
