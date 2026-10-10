<?php

use App\Domains\Catalog\Interfaces\Http\Controllers\PublicCatalogController;
use App\Domains\Identity\Interfaces\Http\Controllers\CustomerSessionController;
use App\Domains\Identity\Interfaces\Http\Middleware\EnsureActiveCustomer;
use Illuminate\Support\Facades\Route;

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
