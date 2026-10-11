<?php

namespace App\Providers;

use App\Domains\Catalog\Application\Ports\CatalogSelectionReader;
use App\Domains\Catalog\Application\Ports\PublicCatalogReader;
use App\Domains\Catalog\Infrastructure\Persistence\Queries\EloquentCatalogSelectionReader;
use App\Domains\Catalog\Infrastructure\Persistence\Queries\EloquentPublicCatalogReader;
use App\Domains\Identity\Application\Ports\CustomerIdentityRepository;
use App\Domains\Identity\Application\Ports\GoogleOAuthClient;
use App\Domains\Identity\Infrastructure\ExternalServices\Google\SocialiteGoogleOAuthClient;
use App\Domains\Identity\Infrastructure\Persistence\EloquentCustomerIdentityRepository;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(CatalogSelectionReader::class, EloquentCatalogSelectionReader::class);
        $this->app->bind(PublicCatalogReader::class, EloquentPublicCatalogReader::class);
        $this->app->bind(GoogleOAuthClient::class, SocialiteGoogleOAuthClient::class);
        $this->app->bind(CustomerIdentityRepository::class, EloquentCustomerIdentityRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('personal-collections', fn (Request $request) => Limit::perMinute(
            config($request->isMethod('GET') || $request->isMethod('HEAD') ? 'customer_collections.reads_per_minute' : 'customer_collections.mutations_per_minute')
        )->by('personal:'.$request->user('customer')->getAuthIdentifier().':'.($request->isMethod('GET') || $request->isMethod('HEAD') ? 'read' : 'write')));
        // Sanctum is session-only: ignore bearer tokens and never query a token table.
        Sanctum::getAccessTokenFromRequestUsing(fn (Request $request): ?string => null);
        RateLimiter::for('google-auth', fn (Request $request) => Limit::perMinute(
            config('identity.google_auth_rate_limit_per_minute')
        )->by('google-auth:'.$request->ip()));
    }
}
