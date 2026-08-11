<?php

use App\Models\Website;
use App\Models\WebsiteHealthReport;
use App\Models\WebsiteHealthReportPage;
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
        ->and(collect($pages)->firstWhere('url', 'https://example.com/about')['status_code'])->toBe(404)
        ->and(collect($pages)->firstWhere('url', 'https://example.com/about')['title'])->toBeNull()
        ->and(collect($pages)->firstWhere('url', 'https://example.com/about')['checks'])->toBeEmpty();

    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'outside.test'));
});

it('preserves trailing slashes from sitemaps and internal links', function (): void {
    config()->set('forms.health_reports.max_pages', 5);
    config()->set('forms.health_reports.max_depth', 2);
    config()->set('forms.health_reports.crawl_delay_ms', 0);

    $website = Website::factory()->create();
    $website->domains()->create(['domain' => 'example.com', 'is_primary' => true]);

    Http::fake([
        'https://example.com/sitemap.xml' => Http::response('<urlset><url><loc>https://example.com/about/</loc></url></urlset>', 200, ['Content-Type' => 'application/xml']),
        'https://example.com/' => Http::response('<html><head><title>Home</title></head><body><h1>Home</h1><a href="/contact/">Contact</a></body></html>', 200, ['Content-Type' => 'text/html']),
        'https://example.com/about/' => Http::response('<html><head><title>About</title></head><body><h1>About</h1><a href="team/">Team</a></body></html>', 200, ['Content-Type' => 'text/html']),
        'https://example.com/about/team/' => Http::response('<html><head><title>Team</title></head><body><h1>Team</h1></body></html>', 200, ['Content-Type' => 'text/html']),
        'https://example.com/contact/' => Http::response('<html><head><title>Contact</title></head><body><h1>Contact</h1></body></html>', 200, ['Content-Type' => 'text/html']),
    ]);

    $pages = app(WebsiteCrawler::class)->crawl($website);

    expect(collect($pages)->pluck('url')->all())->toBe([
        'https://example.com/',
        'https://example.com/about/',
        'https://example.com/contact/',
        'https://example.com/about/team/',
    ])->and(collect($pages)->pluck('status_code')->all())->toBe([200, 200, 200, 200]);

    Http::assertNotSent(fn ($request): bool => in_array($request->url(), [
        'https://example.com/about',
        'https://example.com/contact',
    ], true));
});

it('prioritises pages that were not included in the previous audit', function (): void {
    config()->set('forms.health_reports.max_pages', 3);
    config()->set('forms.health_reports.max_depth', 2);
    config()->set('forms.health_reports.crawl_delay_ms', 0);

    $website = Website::factory()->create();
    $website->domains()->create(['domain' => 'example.com', 'is_primary' => true]);
    $previousReport = WebsiteHealthReport::factory()->for($website)->create(['completed_at' => now()->subWeek()]);

    foreach (['https://example.com/', 'https://example.com/a', 'https://example.com/b'] as $url) {
        WebsiteHealthReportPage::factory()->for($previousReport, 'report')->create([
            'url' => $url,
            'url_hash' => hash('sha256', $url),
        ]);
    }

    Http::fake([
        'https://example.com/sitemap.xml' => Http::response('<urlset><url><loc>https://example.com/a</loc></url><url><loc>https://example.com/b</loc></url><url><loc>https://example.com/c</loc></url><url><loc>https://example.com/d</loc></url></urlset>', 200, ['Content-Type' => 'application/xml']),
        'https://example.com/*' => Http::response('<html><head><title>Page</title></head><body><h1>Page</h1></body></html>', 200, ['Content-Type' => 'text/html']),
    ]);

    $pages = app(WebsiteCrawler::class)->crawl($website);

    expect(collect($pages)->pluck('url')->all())->toBe([
        'https://example.com/',
        'https://example.com/c',
        'https://example.com/d',
    ]);
});

it('gives healthy pages a one audit cooldown while rechecking pages with issues', function (): void {
    config()->set('forms.health_reports.max_pages', 5);
    config()->set('forms.health_reports.max_depth', 2);
    config()->set('forms.health_reports.crawl_delay_ms', 0);

    $website = Website::factory()->create();
    $website->domains()->create(['domain' => 'example.com', 'is_primary' => true]);
    $previousReport = WebsiteHealthReport::factory()->for($website)->create(['completed_at' => now()->subWeek()]);
    WebsiteHealthReportPage::factory()->for($previousReport, 'report')->create([
        'url' => 'https://example.com/healthy',
        'url_hash' => hash('sha256', 'https://example.com/healthy'),
        'checks' => [['key' => 'page_title', 'label' => 'Page title', 'status' => 'passed', 'message' => 'A title is present.']],
    ]);
    WebsiteHealthReportPage::factory()->for($previousReport, 'report')->create([
        'url' => 'https://example.com/warning',
        'url_hash' => hash('sha256', 'https://example.com/warning'),
        'checks' => [['key' => 'meta_description', 'label' => 'Meta description', 'status' => 'warning', 'message' => 'No meta description was found.']],
    ]);

    Http::fake([
        'https://example.com/sitemap.xml' => Http::response('<urlset><url><loc>https://example.com/healthy</loc></url><url><loc>https://example.com/warning</loc></url><url><loc>https://example.com/new-page</loc></url></urlset>', 200, ['Content-Type' => 'application/xml']),
        'https://example.com/*' => Http::response('<html><head><title>Page</title></head><body><h1>Page</h1></body></html>', 200, ['Content-Type' => 'text/html']),
    ]);

    $pages = app(WebsiteCrawler::class)->crawl($website);

    expect(collect($pages)->pluck('url')->all())->toBe([
        'https://example.com/',
        'https://example.com/new-page',
        'https://example.com/warning',
    ]);
    Http::assertNotSent(fn ($request): bool => $request->url() === 'https://example.com/healthy');
});

it('explains why long search metadata is worth improving', function (): void {
    config()->set('forms.health_reports.max_pages', 1);
    config()->set('forms.health_reports.crawl_delay_ms', 0);

    $website = Website::factory()->create();
    $website->domains()->create(['domain' => 'example.com', 'is_primary' => true]);
    $title = str_repeat('Long title ', 8);
    $description = str_repeat('Long description ', 12);

    Http::fake([
        'https://example.com/sitemap.xml' => Http::response('', 404),
        'https://example.com/' => Http::response('<html><head><title>'.$title.'</title><meta name="description" content="'.$description.'"></head><body><h1>Home</h1></body></html>', 200, ['Content-Type' => 'text/html']),
    ]);

    $checks = collect(app(WebsiteCrawler::class)->crawl($website)[0]['checks'])->keyBy('key');

    expect($checks['page_title']['status'])->toBe('warning')
        ->and($checks['page_title']['message'])->toContain('characters long', 'Aim for 65 or fewer', 'truncated in search results')
        ->and($checks['meta_description']['status'])->toBe('warning')
        ->and($checks['meta_description']['message'])->toContain('characters long', 'Aim for 170 or fewer', 'truncated in search results');
});
