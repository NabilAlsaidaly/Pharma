<?php

use App\Domains\Identity\Interfaces\Http\Controllers\GoogleAuthenticationController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1/auth/google')->middleware('throttle:google-auth')->group(function (): void {
    Route::get('redirect', [GoogleAuthenticationController::class, 'redirect'])->name('auth.google.redirect');
    Route::get('callback', [GoogleAuthenticationController::class, 'callback'])->name('auth.google.callback');
});

Route::get('/', function () {
    return view('welcome');
});
