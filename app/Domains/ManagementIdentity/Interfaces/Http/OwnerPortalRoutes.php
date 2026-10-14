<?php

namespace App\Domains\ManagementIdentity\Interfaces\Http;

use App\Domains\ManagementDashboard\Interfaces\Http\Controllers\OwnerDashboardController;
use App\Domains\ManagementIdentity\Interfaces\Http\Controllers\OwnerAccountController;
use App\Domains\ManagementIdentity\Interfaces\Http\Controllers\OwnerAuthenticationController;
use App\Domains\ManagementIdentity\Interfaces\Http\Middleware\OwnerLoginAttempts;
use App\Domains\ManagementIdentity\Interfaces\Http\Middleware\OwnerPortalResponse;
use App\Domains\ManagementIdentity\Interfaces\Http\Middleware\RequireOwner;
use App\Http\Middleware\LimitPersonalMutationBody;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

class OwnerPortalRoutes
{
    public static function valid(mixed $prefix): bool
    {
        if (! is_string($prefix) || strlen($prefix) > 100 || ! preg_match('/^[a-z][a-z0-9-]{2,39}(\/[a-z][a-z0-9-]{2,39})*$/D', $prefix)) {
            return false;
        }
        $reserved = ['admin', 'login', 'register', 'api', 'auth', 'up', 'health', 'sanctum', 'storage', 'build', 'assets', 'livewire', 'password', 'reset-password', 'forgot-password'];
        if (array_intersect(explode('/', $prefix), $reserved) !== []) {
            return false;
        }
        foreach (Route::getRoutes()->getRoutes() as $route) {
            $uri = trim($route->uri(), '/');
            if ($uri !== '' && ($prefix === $uri || str_starts_with($prefix, $uri.'/') || str_starts_with($uri, $prefix.'/'))) {
                return false;
            }
        }

        return true;
    }

    public static function register(): void
    {
        $prefix = config('owner_portal.prefix');
        if (! self::valid($prefix)) {
            Log::notice('owner_portal_routes_disabled', ['reason' => 'missing_or_invalid_prefix']);

            return;
        }
        Route::prefix($prefix)->name('owner.')->middleware([OwnerPortalResponse::class, LimitPersonalMutationBody::class])->group(function (): void {
            Route::get('login', [OwnerAuthenticationController::class, 'form'])->name('login');
            Route::post('login', [OwnerAuthenticationController::class, 'login'])->middleware(OwnerLoginAttempts::class)->name('login.submit');
            Route::middleware(RequireOwner::class)->group(function (): void {
                Route::get('/', [OwnerAccountController::class, 'home'])->name('home');
                Route::get('profile', [OwnerAccountController::class, 'profile'])->name('profile');
                Route::patch('profile', [OwnerAccountController::class, 'update'])->name('profile.update');
                Route::put('password', [OwnerAccountController::class, 'password'])->name('password.update');
                Route::post('logout', [OwnerAuthenticationController::class, 'logout'])->name('logout');
                Route::get('overview', [OwnerDashboardController::class, 'overview'])->name('dashboard');
                Route::get('search', [OwnerDashboardController::class, 'search'])->name('search');
                foreach (['products', 'brands', 'concerns', 'requests'] as $resource) {
                    Route::get($resource, [OwnerDashboardController::class, 'index'])->defaults('resource', $resource)->name($resource.'.index');
                    Route::get($resource.'/{id}', [OwnerDashboardController::class, 'show'])->defaults('resource', $resource)
                        ->whereUuid('id')->name($resource.'.show');
                }
            });
        });
    }
}
