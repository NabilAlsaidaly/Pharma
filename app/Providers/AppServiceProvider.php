<?php

namespace App\Providers;

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
        $this->app->bind(GoogleOAuthClient::class, SocialiteGoogleOAuthClient::class);
        $this->app->bind(CustomerIdentityRepository::class, EloquentCustomerIdentityRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Sanctum is session-only: ignore bearer tokens and never query a token table.
        Sanctum::getAccessTokenFromRequestUsing(fn (Request $request): ?string => null);
        RateLimiter::for('google-auth', fn (Request $request) => Limit::perMinute(
            config('identity.google_auth_rate_limit_per_minute')
        )->by('google-auth:'.$request->ip()));
    }
}
