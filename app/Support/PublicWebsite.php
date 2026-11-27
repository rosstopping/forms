<?php

namespace App\Support;

use Illuminate\Routing\Route;
use Illuminate\Support\Str;

class PublicWebsite
{
    public static function origin(): ?string
    {
        $url = config('public_website.url');

        return is_string($url) && $url !== '' ? rtrim($url, '/') : null;
    }

    public static function owns(?Route $route): bool
    {
        if ($route === null) {
            return false;
        }

        if (Str::startsWith($route->getName() ?? '', 'marketing.wordpress.')) {
            return false;
        }

        return Str::is([
            'marketing.*', 'service-page-finder.*', 'tools.service-page-finder.*',
            'public-website.sitemap', 'forms.submit', 'forms.submit.preflight', 'forms.submitted', 'prospect-reports.show',
            'prospect-outreach-*', 'prospects.unsubscribe.*', 'onboarding-lifecycle.click',
            'website-growth-plans.report', 'website-health-reports.show',
        ], $route->getName() ?? '') || $route->uri() === 'tools/missing-service-pages'
            || Str::startsWith($route->uri(), 'tools/missing-service-pages/');
    }

    public static function path(string $path, ?Route $route): string
    {
        return (config('public_website.static_routes', [])[$route?->getName() ?? ''] ?? null) ?? $path;
    }
}
