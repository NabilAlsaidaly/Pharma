<?php

namespace App\Domains\Gifts\Interfaces\Http;

use Illuminate\Support\Facades\Route;

final class GiftOwnerRoutes
{
    public static function register(): void
    {
        Route::prefix('gifts')->name('gifts.')->middleware(GiftHttpBoundary::class)->controller(OwnerGiftController::class)->group(function (): void {
            Route::get('packages', 'index')->name('index');
            Route::get('packages/create', 'edit')->name('create');
            Route::get('packages/{id}', 'edit')->whereUuid('id')->name('edit');
            Route::post('packages/{id?}', 'mutate')->whereUuid('id')->name('mutate');
            Route::get('media/{id}', 'media')->whereUuid('id')->name('media');
            Route::get('occasions', 'occasions')->name('occasions');
            Route::get('occasions/create', 'occasionEdit')->name('occasion.create');
            Route::get('occasions/{id}', 'occasionEdit')->whereUuid('id')->name('occasion.edit');
            Route::post('occasions/{id?}', 'occasionMutate')->whereUuid('id')->name('occasion.mutate');
            Route::get('requests', 'requests')->name('requests');
            Route::get('requests/{id}', 'requestDetail')->whereUuid('id')->name('requests.show');
            Route::post('requests/{id}/review', 'review')->whereUuid('id')->name('review');
            Route::get('history', 'history')->name('history');
        });
    }
}
