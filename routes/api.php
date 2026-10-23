<?php

use App\Http\Controllers\AgentContentTaskController;
use App\Http\Controllers\AgentWebsiteController;
use App\Http\Controllers\AgentWorkController;
use App\Http\Controllers\PixelHeartbeatController;
use App\Http\Controllers\PixelPayloadController;
use App\Http\Controllers\WordPressConnectionController;
use App\Http\Controllers\WordPressCurrentReleaseController;
use App\Http\Controllers\WordPressDisconnectController;
use App\Http\Controllers\WordPressHeartbeatController;
use App\Http\Controllers\WordPressReleaseActivatedController;
use App\Http\Controllers\WordPressReleaseDownloadController;
use App\Http\Middleware\AllowPixelCors;
use App\Http\Middleware\AuthenticateSitewellAgent;
use App\Services\AgentSeoEvidence;
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

Route::get('agent/v1/work', AgentWorkController::class)
    ->middleware(['throttle:10,1,sitewell-agent-work', AuthenticateSitewellAgent::class])
    ->name('agent.work');

Route::middleware(['throttle:60,1,sitewell-agent-read', AuthenticateSitewellAgent::class])->prefix('agent/v1')->name('agent.')->group(function (): void {
    Route::get('websites', [AgentWebsiteController::class, 'index'])->name('websites.index');
    Route::get('websites/{website}', [AgentWebsiteController::class, 'show'])->whereNumber('website')->name('websites.show');
    Route::get('websites/{website}/evidence/{section}', [AgentWebsiteController::class, 'evidence'])
        ->whereNumber('website')->whereIn('section', AgentSeoEvidence::SECTIONS)->name('websites.evidence');
});

Route::middleware(['throttle:10,1,sitewell-agent-tasks', AuthenticateSitewellAgent::class])->prefix('agent/v1/websites/{website}/tasks')->whereNumber('website')->name('agent.tasks.')->group(function (): void {
    Route::post('/', [AgentContentTaskController::class, 'store'])->name('store');
    Route::get('{agentContentTask}', [AgentContentTaskController::class, 'show'])->whereNumber('agentContentTask')->name('show');
    Route::post('{agentContentTask}/draft-pr', [AgentContentTaskController::class, 'update'])->whereNumber('agentContentTask')->name('draft-pr');
    Route::post('{agentContentTask}/blocked', [AgentContentTaskController::class, 'update'])->whereNumber('agentContentTask')->name('blocked');
});
