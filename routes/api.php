<?php

use App\Http\Controllers\PixelHeartbeatController;
use App\Http\Controllers\PixelPayloadController;
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
