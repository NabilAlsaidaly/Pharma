<?php

namespace Tests\Feature\Owner;

use App\Domains\ManagementIdentity\Interfaces\Http\OwnerPortalRoutes;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OwnerPrefixTest extends TestCase
{
    public static function unsafePrefixes(): array
    {
        return array_map(fn ($v) => [$v], [null, '', 'admin', 'admin/login', 'login', 'register', 'api', 'api/v1', 'up', 'sanctum', 'storage', 'build', '../secret', '/secret', 'secret/', 'a//secret', 'secret/*', '{any}', 'abc?x=1', str_repeat('x', 101)]);
    }

    #[DataProvider('unsafePrefixes')]
    public function test_missing_reserved_and_unsafe_prefixes_fail_closed(mixed $prefix): void
    {
        config(['owner_portal.prefix' => $prefix]);
        OwnerPortalRoutes::register();
        $this->assertFalse(Route::has('owner.login'));
        $this->assertFalse(OwnerPortalRoutes::valid($prefix));
        $this->get('/admin/login')->assertNotFound();
        $this->get('/login')->assertNotFound();
    }

    public function test_valid_segment_paths_and_public_route_collisions(): void
    {
        $this->assertTrue(OwnerPortalRoutes::valid('fixture-path'));
        $this->assertTrue(OwnerPortalRoutes::valid('fixture-path/private-access'));
        Route::get('public-content', fn () => 'Public');
        $this->assertFalse(OwnerPortalRoutes::valid('public-content'));
        $this->assertFalse(OwnerPortalRoutes::valid('public-content/private'));
        $this->assertSame('customer', config('auth.defaults.guard'));
    }
}
