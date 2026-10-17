<?php

namespace App\Domains\Content\Interfaces\Http\Education;

use Illuminate\Support\Facades\Route;

final class OwnerEducationRoutes
{
    public static function register(): void
    {
        Route::prefix('education')->name('education.')->middleware(EducationBoundary::class)->controller(OwnerEducationController::class)->group(function (): void {
            Route::get('articles', 'index')->name('index');
            Route::get('articles/create', 'edit')->name('create');
            Route::get('articles/{id}', 'edit')->whereUuid('id')->name('edit');
            Route::post('articles/{id?}', 'mutate')->whereUuid('id')->name('mutate');
            Route::get('media/{id}', 'media')->whereUuid('id')->name('media');
            Route::get('categories', 'categories')->name('categories');
            Route::get('categories/create', 'categoryEdit')->name('category.create');
            Route::get('categories/{id}', 'categoryEdit')->whereUuid('id')->name('category.edit');
            Route::post('categories/{id?}', 'categoryMutate')->whereUuid('id')->name('category.mutate');
            Route::get('history', 'history')->name('history');
        });
    }
}
