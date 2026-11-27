<?php

use App\Http\Controllers\ServicePageFinderController;
use Illuminate\Support\Facades\Route;

Route::middleware('web')->prefix('tools/missing-service-pages')->name('tools.service-page-finder.')->group(function (): void {
    Route::get('/', [ServicePageFinderController::class, 'create'])->name('create');
    Route::post('/', [ServicePageFinderController::class, 'store'])->middleware('throttle:service-page-finder')->name('store');
    Route::get('/{id}', [ServicePageFinderController::class, 'show'])->whereUuid('id')->name('show');
    Route::get('/{id}/status', [ServicePageFinderController::class, 'status'])->whereUuid('id')->name('status');
});
