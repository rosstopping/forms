<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class ServicePageFinderServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        RateLimiter::for('service-page-finder', fn (Request $request): array => [
            Limit::perHour(5)->by('finder-hour:'.$request->ip()),
            Limit::perDay(10)->by('finder-day:'.$request->ip()),
            Limit::perDay(100)->by('finder-global'),
        ]);
        $this->loadRoutesFrom(base_path('routes/service-page-finder.php'));
    }
}
