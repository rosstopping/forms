<?php

use App\Services\DataForSEO\BacklinksService;
use App\Services\DataForSEO\DomainOverviewService;
use App\Services\DataForSEO\RankedKeywordsService;
use App\Services\MarketingAuditPageCounter;
use App\Services\MarketingAuditResearch;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

it('builds a bounded search snapshot and caches paid provider results', function (): void {
    Cache::flush();
    config([
        'services.dataforseo.login' => 'test',
        'services.dataforseo.password' => 'test',
        'services.dataforseo.location_code' => 2826,
        'services.dataforseo.language_code' => 'en',
    ]);
    Http::fake([
        '*/domain_rank_overview/live' => Http::response([
            'status_code' => 20000,
            'tasks' => [[
                'status_code' => 20000,
                'cost' => 0.01212,
                'result' => [['items' => [['metrics' => ['organic' => [
                    'count' => 42,
                    'etv' => 120,
                    'pos_1' => 1,
                    'pos_2_3' => 2,
                    'pos_4_10' => 4,
                ]]]]]],
            ]],
        ]),
        '*/ranked_keywords/live' => Http::response([
            'status_code' => 20000,
            'tasks' => [[
                'status_code' => 20000,
                'cost' => 0.0144,
                'result' => [['items' => [[
                    'keyword_data' => [
                        'keyword' => 'garden office fitters',
                        'location_code' => 2826,
                        'language_code' => 'en',
                        'keyword_info' => ['search_volume' => 1000],
                    ],
                    'ranked_serp_element' => ['serp_item' => ['rank_group' => 15, 'url' => 'https://example.com/garden-offices']],
                ]]]],
            ]],
        ]),
        '*/backlinks/summary/live' => Http::response([
            'status_code' => 20000,
            'tasks' => [[
                'status_code' => 20000,
                'cost' => 0.02404,
                'result' => [['backlinks' => 110, 'referring_domains' => 12]],
            ]],
        ]),
    ]);

    $pages = Mockery::mock(MarketingAuditPageCounter::class);
    $pages->shouldReceive('count')->twice()->with('https://example.com')->andReturn(['count' => 18, 'partial' => false]);
    $research = new MarketingAuditResearch(app(DomainOverviewService::class), app(RankedKeywordsService::class), app(BacklinksService::class), $pages);
    $analysis = ['findings' => [['severity' => 'passed'], ['severity' => 'warning']]];

    $insights = $research->forAudit('example.com', 'https://example.com', $analysis);
    $again = $research->forAudit('example.com', 'https://example.com', $analysis);

    expect($insights['health_score'])->toBe(50)
        ->and($insights['pages_listed'])->toBe(18)
        ->and($insights['seo']['organic_keywords'])->toBe(42)
        ->and($insights['seo']['top_10_keywords'])->toBe(7)
        ->and($insights['seo']['estimated_monthly_visits'])->toBe(120)
        ->and($insights['seo']['referring_domains'])->toBe(12)
        ->and($insights['seo']['keywords'][0]['monthly_searches'])->toBe(1000)
        ->and($insights['seo']['cost_usd'])->toBe(0.05056)
        ->and($insights['projection']['six_month_low'])->toBe(128)
        ->and($insights['projection']['six_month_high'])->toBe(145)
        ->and($again['seo'])->toBe($insights['seo']);
    Http::assertSentCount(3);
    Http::assertSent(fn ($request): bool => str_contains($request->url(), 'ranked_keywords/live') && $request->data()[0]['limit'] === 20);
});

it('does not invent search data or a forecast when the provider is unavailable', function (): void {
    config(['services.dataforseo.login' => null, 'services.dataforseo.password' => null]);
    Http::preventStrayRequests();

    $pages = Mockery::mock(MarketingAuditPageCounter::class);
    $pages->shouldReceive('count')->once()->andReturn(['count' => null, 'partial' => false]);
    $research = new MarketingAuditResearch(app(DomainOverviewService::class), app(RankedKeywordsService::class), app(BacklinksService::class), $pages);
    $insights = $research->forAudit('example.com', 'https://example.com', ['findings' => []]);

    expect($insights['health_score'])->toBeNull()
        ->and($insights['seo'])->toBeNull()
        ->and($insights['projection'])->toBeNull();
    Http::assertNothingSent();
});

it('skips the keyword request when no Google rankings are found', function (): void {
    Cache::flush();
    config(['services.dataforseo.login' => 'test', 'services.dataforseo.password' => 'test']);
    Http::fake([
        '*/domain_rank_overview/live' => Http::response([
            'status_code' => 20000,
            'tasks' => [['status_code' => 20000, 'cost' => 0.012, 'result' => [['items' => [['metrics' => ['organic' => ['count' => 0, 'etv' => 0]]]]]]]],
        ]),
        '*/backlinks/summary/live' => Http::response([
            'status_code' => 20000,
            'tasks' => [['status_code' => 20000, 'cost' => 0.024, 'result' => [['backlinks' => 0, 'referring_domains' => 0]]]],
        ]),
    ]);

    $pages = Mockery::mock(MarketingAuditPageCounter::class);
    $pages->shouldReceive('count')->once()->andReturn(['count' => 1, 'partial' => false]);
    $research = new MarketingAuditResearch(app(DomainOverviewService::class), app(RankedKeywordsService::class), app(BacklinksService::class), $pages);
    $insights = $research->forAudit('new-domain.example', 'https://new-domain.example', ['findings' => []]);

    expect($insights['seo']['organic_keywords'])->toBe(0)
        ->and($insights['seo']['keywords'])->toBe([])
        ->and($insights['projection'])->toBeNull();
    Http::assertSentCount(2);
});

it('counts sitemap pages within a small same-host request budget', function (): void {
    Http::fake([
        'https://example.com/sitemap.xml' => Http::response('<?xml version="1.0"?><sitemapindex><sitemap><loc>https://example.com/pages.xml</loc></sitemap><sitemap><loc>https://example.com/posts.xml</loc></sitemap><sitemap><loc>https://other.example/foreign.xml</loc></sitemap></sitemapindex>', 200),
        'https://example.com/pages.xml' => Http::response('<?xml version="1.0"?><urlset><url><loc>https://example.com/</loc></url><url><loc>https://example.com/about</loc></url></urlset>', 200),
        'https://example.com/posts.xml' => Http::response('<?xml version="1.0"?><urlset><url><loc>https://example.com/blog</loc></url></urlset>', 200),
    ]);

    $result = app(MarketingAuditPageCounter::class)->count('https://example.com');

    expect($result)->toBe(['count' => 3, 'partial' => true]);
    Http::assertSentCount(3);
});

it('excludes pages on other hosts from the sitemap count', function (): void {
    Http::fake([
        'https://example.com/sitemap.xml' => Http::response('<?xml version="1.0"?><urlset><url><loc>https://example.com/</loc></url><url><loc>https://other.example/about</loc></url></urlset>', 200),
    ]);

    $result = app(MarketingAuditPageCounter::class)->count('https://example.com');

    expect($result)->toBe(['count' => 1, 'partial' => true]);
    Http::assertSentCount(1);
});
