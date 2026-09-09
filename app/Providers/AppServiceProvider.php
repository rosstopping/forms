<?php

namespace App\Providers;

use App\Contracts\SerpProvider;
use App\Services\CachedSerpProvider;
use App\View\Composers\NavigationComposer;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(SerpProvider::class, CachedSerpProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('access-outreach', fn ($user): bool => $user->isAdmin());
        RateLimiter::for('website-audits', function (Request $request): array {
            $websiteUrl = Str::lower(trim((string) $request->input('website_url')));
            $websiteUrl = Str::startsWith($websiteUrl, ['http://', 'https://']) ? $websiteUrl : 'https://'.$websiteUrl;
            $domain = (string) parse_url($websiteUrl, PHP_URL_HOST);

            return [
                Limit::perMinute(3)->by('website-audit-minute:'.$request->ip()),
                Limit::perDay(10)->by('website-audit-day:'.$request->ip()),
                Limit::perDay(3)->by('website-audit-domain:'.$domain),
            ];
        });

        View::composer('layouts.app', NavigationComposer::class);
    }
}
