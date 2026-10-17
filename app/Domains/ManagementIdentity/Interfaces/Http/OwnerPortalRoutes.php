<?php

namespace App\Domains\ManagementIdentity\Interfaces\Http;

use App\Domains\Catalog\Interfaces\Http\Controllers\OwnerCatalogController;
use App\Domains\Catalog\Interfaces\Http\LimitOwnerCatalogBody;
use App\Domains\Content\Interfaces\Http\Education\OwnerEducationRoutes;
use App\Domains\Content\Interfaces\Http\OwnerStoreContentController;
use App\Domains\Delivery\Interfaces\Http\DeliveryBoundary;
use App\Domains\Delivery\Interfaces\Http\OwnerDeliveryController;
use App\Domains\Gifts\Interfaces\Http\GiftOwnerRoutes;
use App\Domains\ManagementDashboard\Interfaces\Http\Controllers\OwnerDashboardController;
use App\Domains\ManagementIdentity\Interfaces\Http\Controllers\OwnerAccountController;
use App\Domains\ManagementIdentity\Interfaces\Http\Controllers\OwnerAuthenticationController;
use App\Domains\ManagementIdentity\Interfaces\Http\Middleware\OwnerLoginAttempts;
use App\Domains\ManagementIdentity\Interfaces\Http\Middleware\OwnerPortalResponse;
use App\Domains\ManagementIdentity\Interfaces\Http\Middleware\RequireOwner;
use App\Domains\Orders\Interfaces\Http\Owner\OwnerRequestReviewController;
use App\Domains\Orders\Interfaces\Http\Owner\ReviewWriteThrottle;
use App\Domains\Settings\Interfaces\Http\OwnerStoreSettingsController;
use App\Domains\Settings\Interfaces\Http\StoreWriteThrottle;
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
        Route::prefix($prefix)->name('owner.')->middleware([OwnerPortalResponse::class, LimitOwnerCatalogBody::class])->group(function (): void {
            Route::get('login', [OwnerAuthenticationController::class, 'form'])->name('login');
            Route::post('login', [OwnerAuthenticationController::class, 'login'])->middleware(OwnerLoginAttempts::class)->name('login.submit');
            Route::middleware(RequireOwner::class)->group(function (): void {
                GiftOwnerRoutes::register();
                OwnerEducationRoutes::register();
                Route::prefix('delivery')->name('delivery.')->middleware(DeliveryBoundary::class)->controller(OwnerDeliveryController::class)->group(function (): void {
                    Route::get('/', 'index')->name('index');
                    Route::get('history', 'history')->name('history');
                    Route::get('workers/create', 'edit')->name('create');
                    Route::get('workers/{id}', 'edit')->whereUuid('id')->name('edit');
                    Route::post('workers/{id?}', 'mutate')->whereUuid('id')->name('mutate');
                });
                Route::get('/', [OwnerAccountController::class, 'home'])->name('home');
                Route::get('profile', [OwnerAccountController::class, 'profile'])->name('profile');
                Route::patch('profile', [OwnerAccountController::class, 'update'])->name('profile.update');
                Route::put('password', [OwnerAccountController::class, 'password'])->name('password.update');
                Route::post('logout', [OwnerAuthenticationController::class, 'logout'])->name('logout');
                Route::get('overview', [OwnerDashboardController::class, 'overview'])->name('dashboard');
                Route::get('search', [OwnerDashboardController::class, 'search'])->name('search');
                Route::get('store-settings', [OwnerStoreSettingsController::class, 'index'])->name('settings.index');
                Route::get('store-media/{id}', [OwnerStoreSettingsController::class, 'media'])->whereUuid('id')->name('settings.media');
                Route::get('store-settings/history', [OwnerStoreSettingsController::class, 'history'])->name('settings.history');
                Route::get('store-settings/{group}', [OwnerStoreSettingsController::class, 'group'])->whereIn('group', ['profile', 'currency', 'payment', 'zones'])->name('settings.group');
                Route::get('store-settings/{group}/create', [OwnerStoreSettingsController::class, 'edit'])->where('group', 'zones')->name('settings.create');
                Route::get('store-settings/{group}/{id}', [OwnerStoreSettingsController::class, 'edit'])->where('group', 'zones')->whereUuid('id')->name('settings.edit');
                Route::post('store-settings/{group}/{id?}', [OwnerStoreSettingsController::class, 'mutate'])->whereIn('group', ['profile', 'currency', 'payment', 'zones'])->whereUuid('id')->middleware(StoreWriteThrottle::class)->name('settings.mutate');
                Route::get('store-content/{type}', [OwnerStoreContentController::class, 'index'])->whereIn('type', ['banners', 'pages', 'faqs', 'policies'])->name('content.index');
                Route::get('store-content/{type}/create', [OwnerStoreContentController::class, 'edit'])->whereIn('type', ['banners', 'pages', 'faqs', 'policies'])->name('content.create');
                Route::get('store-content/{type}/{id}', [OwnerStoreContentController::class, 'edit'])->whereIn('type', ['banners', 'pages', 'faqs', 'policies'])->whereUuid('id')->name('content.edit');
                Route::post('store-content/{type}/{id?}', [OwnerStoreContentController::class, 'mutate'])->whereIn('type', ['banners', 'pages', 'faqs', 'policies'])->whereUuid('id')->middleware(StoreWriteThrottle::class)->name('content.mutate');
                Route::post('catalog/confirm', [OwnerCatalogController::class, 'execute'])->name('catalog.execute');
                Route::get('catalog/{type}/create', [OwnerCatalogController::class, 'form'])->whereIn('type', ['products', 'brands', 'concerns', 'variants'])->name('catalog.create');
                Route::post('catalog/{type}', [OwnerCatalogController::class, 'save'])->whereIn('type', ['products', 'brands', 'concerns', 'variants'])->name('catalog.store');
                Route::get('catalog/{type}/recycle', [OwnerCatalogController::class, 'recycle'])->whereIn('type', ['products', 'brands', 'concerns', 'variants', 'images'])->name('catalog.recycle');
                Route::post('catalog/{type}/preview', [OwnerCatalogController::class, 'preview'])->whereIn('type', ['products', 'brands', 'concerns', 'variants', 'images'])->name('catalog.preview');
                Route::get('catalog/{type}/{id}/edit', [OwnerCatalogController::class, 'form'])->whereIn('type', ['products', 'brands', 'concerns', 'variants'])->whereUuid('id')->name('catalog.edit');
                Route::patch('catalog/{type}/{id}', [OwnerCatalogController::class, 'save'])->whereIn('type', ['products', 'brands', 'concerns', 'variants'])->whereUuid('id')->name('catalog.update');
                Route::post('catalog/{type}/{id}/media', [OwnerCatalogController::class, 'upload'])->whereIn('type', ['products', 'brands'])->whereUuid('id')->name('catalog.upload');
                Route::patch('catalog/products/{id}/images/{image}', [OwnerCatalogController::class, 'image'])->whereUuid(['id', 'image'])->name('catalog.image');
                Route::get('requests', [OwnerRequestReviewController::class, 'index'])->name('requests.index');
                Route::get('requests/{id}', [OwnerRequestReviewController::class, 'show'])->whereUuid('id')->name('requests.show');
                Route::middleware(ReviewWriteThrottle::class)->group(function (): void {
                    Route::post('requests/preview', [OwnerRequestReviewController::class, 'preview'])->name('requests.preview');
                    Route::post('requests/confirm', [OwnerRequestReviewController::class, 'confirm'])->name('requests.confirm');
                    Route::post('requests/{id}/review', [OwnerRequestReviewController::class, 'mutate'])->whereUuid('id')->name('requests.review');
                });
                foreach (['products', 'brands', 'concerns'] as $resource) {
                    Route::get($resource, [OwnerDashboardController::class, 'index'])->defaults('resource', $resource)->name($resource.'.index');
                    Route::get($resource.'/{id}', [OwnerDashboardController::class, 'show'])->defaults('resource', $resource)
                        ->whereUuid('id')->name($resource.'.show');
                }
            });
        });
    }
}
