<?php

use App\Models\Website;
use App\Services\WebsiteCrawler;
use Illuminate\Support\Facades\Http;

it('analyses sitemap and discovered internal pages without leaving the website', function (): void {
    config()->set('forms.health_reports.max_pages', 5);
    config()->set('forms.health_reports.max_depth', 2);
    config()->set('forms.health_reports.crawl_delay_ms', 0);

    $website = Website::factory()->create();
    $website->domains()->create(['domain' => 'example.com', 'is_primary' => true]);

    Http::fake([
        'https://example.com/sitemap.xml' => Http::response('<urlset><url><loc>https://example.com/services</loc></url><url><loc>https://outside.test/private</loc></url></urlset>', 200, ['Content-Type' => 'application/xml']),
        'https://example.com/' => Http::response('<html lang="en"><head><title>Home</title><meta name="description" content="Home page"><meta name="viewport" content="width=device-width"></head><body><h1>Home</h1><a href="/about">About</a><a href="https://outside.test/trap">External</a></body></html>', 200, ['Content-Type' => 'text/html']),
        'https://example.com/services' => Http::response('<html><head><title>Shared title</title><meta name="robots" content="noindex"></head><body><h1>Services</h1><img src="work.jpg"></body></html>', 200, ['Content-Type' => 'text/html']),
        'https://example.com/about' => Http::response('<html><head><title>Shared title</title></head><body><h1>About</h1></body></html>', 404, ['Content-Type' => 'text/html']),
    ]);

    $pages = app(WebsiteCrawler::class)->crawl($website);

    expect($pages)->toHaveCount(3)
        ->and(collect($pages)->pluck('url')->all())->toBe([
            'https://example.com/',
            'https://example.com/services',
            'https://example.com/about',
        ])
        ->and(collect($pages)->firstWhere('url', 'https://example.com/services')['is_indexable'])->toBeFalse()
        ->and(collect($pages)->firstWhere('url', 'https://example.com/services')['missing_alt_count'])->toBe(1)
        ->and(collect($pages)->firstWhere('url', 'https://example.com/about')['status_code'])->toBe(404);

    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'outside.test'));
});
