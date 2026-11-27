<?php

use App\Http\Controllers\MarketingController;
use App\Http\Middleware\AddStrictTransportSecurity;
use App\Http\Middleware\ConfigurePublicWebsiteSession;
use App\Http\Middleware\EnsureMembershipFeature;
use App\Http\Middleware\ServePublicWebsite;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            Route::middleware('web')->get('/tools-sitemap.xml', [MarketingController::class, 'sitemap'])
                ->name('public-website.sitemap');
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(ConfigurePublicWebsiteSession::class);
        $middleware->web(prepend: [ServePublicWebsite::class]);
        $middleware->api(prepend: [ServePublicWebsite::class]);
        $middleware->web(append: [AddStrictTransportSecurity::class]);
        $middleware->alias(['membership' => EnsureMembershipFeature::class]);
        $middleware->validateCsrfTokens(except: [
            'cal/webhook',
            'github/webhook',
            'stripe/webhook',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*')
                || ($request->routeIs('marketing.free-site-audit.store') && $request->expectsJson()),
        );
    })->create();
