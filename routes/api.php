<?php

use App\Domains\Identity\Interfaces\Http\Controllers\CustomerSessionController;
use App\Domains\Identity\Interfaces\Http\Middleware\EnsureActiveCustomer;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->middleware(['auth:sanctum', EnsureActiveCustomer::class])->group(function (): void {
    Route::get('me', [CustomerSessionController::class, 'me'])->name('auth.me');
    Route::post('logout', [CustomerSessionController::class, 'logout'])->name('auth.logout');
});
