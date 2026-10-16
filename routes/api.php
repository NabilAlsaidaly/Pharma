<?php

use App\Domains\Cart\Interfaces\Http\Controllers\CartController;
use App\Domains\Catalog\Interfaces\Http\Controllers\PublicCatalogController;
use App\Domains\Content\Interfaces\Http\PublicStorefrontController;
use App\Domains\Favorites\Interfaces\Http\Controllers\FavoriteController;
use App\Domains\Gifts\Interfaces\Http\GiftApiController;
use App\Domains\Gifts\Interfaces\Http\GiftHttpBoundary;
use App\Domains\Identity\Interfaces\Http\Controllers\CustomerSessionController;
use App\Domains\Identity\Interfaces\Http\Middleware\EnsureActiveCustomer;
use App\Domains\Orders\Interfaces\Http\Controllers\CheckoutEvaluationController;
use App\Domains\Orders\Interfaces\Http\Controllers\PreliminaryRequestController;
use App\Http\Middleware\LimitPersonalMutationBody;
use Illuminate\Support\Facades\Route;

Route::prefix('gifts')->middleware(GiftHttpBoundary::class)->controller(GiftApiController::class)->group(function (): void {
    Route::get('packages', 'index');
    Route::get('packages/{slug}', 'show')->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*');
    Route::get('occasions', 'occasions');
    Route::get('media/{id}.png', 'media')->whereUuid('id');
});
Route::prefix('gifts')->middleware(['auth:sanctum', EnsureActiveCustomer::class, GiftHttpBoundary::class])->controller(GiftApiController::class)->group(function (): void {
    Route::post('requests', 'store');
    Route::get('requests', 'history')->middleware('throttle:personal-collections');
    Route::get('requests/{id}', 'requestDetail')->whereUuid('id')->middleware('throttle:personal-collections');
});

Route::prefix('storefront')->controller(PublicStorefrontController::class)->group(function (): void {
    Route::get('media/{id}.png', 'media')->whereUuid('id');
    Route::get('configuration', 'configuration');
    Route::get('{type}', 'listing')->whereIn('type', ['banners', 'faqs']);
    Route::get('{type}/{slug}', 'item')->whereIn('type', ['pages', 'policies'])->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*');
});

Route::controller(PublicCatalogController::class)->group(function (): void {
    Route::get('brands', 'brands');
    Route::get('brands/{slug}', 'brand');
    Route::get('brands/{slug}/products', 'brandProducts');
    Route::get('products', 'products');
    Route::get('products/recommended', 'recommended');
    Route::get('products/{slug}', 'product');
    Route::get('concerns', 'concerns');
    Route::get('concerns/{slug}/products', 'concernProducts');
});

Route::prefix('auth')->middleware(['auth:sanctum', EnsureActiveCustomer::class])->group(function (): void {
    Route::get('me', [CustomerSessionController::class, 'me'])->name('auth.me');
    Route::post('logout', [CustomerSessionController::class, 'logout'])->name('auth.logout');
});

Route::middleware(['auth:sanctum', EnsureActiveCustomer::class, LimitPersonalMutationBody::class, 'throttle:personal-collections'])->group(function (): void {
    Route::post('checkout/evaluations', CheckoutEvaluationController::class);
    Route::get('favorites', [FavoriteController::class, 'listing']);
    Route::get('favorites/check', [FavoriteController::class, 'check']);
    Route::post('favorites', [FavoriteController::class, 'add']);
    Route::delete('favorites/{productId}', [FavoriteController::class, 'remove']);
    Route::get('cart', [CartController::class, 'show']);
    Route::post('cart/items', [CartController::class, 'add']);
    Route::patch('cart/items/{itemId}', [CartController::class, 'update']);
    Route::delete('cart/items/{itemId}', [CartController::class, 'remove']);
});

Route::middleware(['auth:sanctum', EnsureActiveCustomer::class, LimitPersonalMutationBody::class])->group(function (): void {
    Route::post('checkout/intents', [PreliminaryRequestController::class, 'store'])->middleware('throttle:preliminary-checkout');
    Route::get('checkout/intents', [PreliminaryRequestController::class, 'index'])->middleware('throttle:personal-collections');
    Route::get('checkout/intents/{id}', [PreliminaryRequestController::class, 'show'])->middleware('throttle:personal-collections');
});
