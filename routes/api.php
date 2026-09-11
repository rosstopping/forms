<?php

use App\Http\Controllers\PixelHeartbeatController;
use App\Http\Controllers\PixelPayloadController;
use App\Http\Controllers\WordPressConnectionController;
use App\Http\Controllers\WordPressCurrentReleaseController;
use App\Http\Controllers\WordPressDisconnectController;
use App\Http\Controllers\WordPressHeartbeatController;
use App\Http\Controllers\WordPressReleaseActivatedController;
use App\Http\Controllers\WordPressReleaseDownloadController;
use App\Http\Middleware\AllowPixelCors;
use Illuminate\Support\Facades\Route;

Route::get('pixel/{siteKey}', PixelPayloadController::class)
    ->middleware([AllowPixelCors::class, 'throttle:120,1'])
    ->name('pixel.payload');

Route::post('pixel/{siteKey}/heartbeat', PixelHeartbeatController::class)
    ->middleware([AllowPixelCors::class, 'throttle:30,1'])
    ->name('pixel.heartbeat');

Route::options('pixel/{siteKey}/heartbeat', fn () => response()->noContent())
    ->middleware([AllowPixelCors::class, 'throttle:30,1']);

Route::post('wordpress/connections', WordPressConnectionController::class)
    ->middleware('throttle:6,1')
    ->name('wordpress-connections.store');

Route::post('wordpress/connections/{connectionId}/heartbeat', WordPressHeartbeatController::class)
    ->middleware('throttle:30,1')
    ->name('wordpress-connections.heartbeat');

Route::delete('wordpress/connections/{connectionId}', WordPressDisconnectController::class)
    ->middleware('throttle:6,1')
    ->name('wordpress-connections.disconnect');

Route::get('wordpress/connections/{connectionId}/releases/current', WordPressCurrentReleaseController::class)
    ->middleware('throttle:30,1')
    ->name('wordpress-connections.releases.current');

Route::get('wordpress/connections/{connectionId}/releases/{releaseId}/download', WordPressReleaseDownloadController::class)
    ->middleware('throttle:30,1')
    ->name('wordpress-connections.releases.download');

Route::post('wordpress/connections/{connectionId}/releases/{releaseId}/activated', WordPressReleaseActivatedController::class)
    ->middleware('throttle:30,1')
    ->name('wordpress-connections.releases.activated');
