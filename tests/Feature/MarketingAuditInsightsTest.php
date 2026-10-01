<?php

use App\Models\SeoKeyword;
use App\Models\SeoSnapshot;
use App\Models\WebsiteAudit;
use App\Services\DataForSEO\BacklinksService;
use App\Services\DataForSEO\DomainOverviewService;
use App\Services\DataForSEO\RankedKeywordsService;
use App\Services\MarketingAuditAiVisibility;
use App\Services\MarketingAuditCompetitors;
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
        '*/competitors_domain/live' => Http::response([
            'status_code' => 20000,
            'tasks' => [['status_code' => 20000, 'cost' => 0.01, 'result' => [['items' => []]]]],
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
    $research = new MarketingAuditResearch(app(DomainOverviewService::class), app(RankedKeywordsService::class), app(BacklinksService::class), $pages, app(MarketingAuditCompetitors::class), app(MarketingAuditAiVisibility::class));
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
        ->and($insights['projection']['six_month_high'])->toBe(160)
        ->and($insights['projection']['method'])->toContain('3–8%')
        ->and($again['seo'])->toBe($insights['seo']);
    Http::assertSentCount(4);
    Http::assertSent(fn ($request): bool => str_contains($request->url(), 'ranked_keywords/live') && $request->data()[0]['limit'] === 20);
});

it('does not invent search data or a forecast when the provider is unavailable', function (): void {
    Cache::flush();
    config(['services.dataforseo.login' => null, 'services.dataforseo.password' => null]);
    Http::preventStrayRequests();

    $pages = Mockery::mock(MarketingAuditPageCounter::class);
    $pages->shouldReceive('count')->once()->andReturn(['count' => null, 'partial' => false]);
    $research = new MarketingAuditResearch(app(DomainOverviewService::class), app(RankedKeywordsService::class), app(BacklinksService::class), $pages, app(MarketingAuditCompetitors::class), app(MarketingAuditAiVisibility::class));
    $insights = $research->forAudit('example.com', 'https://example.com', ['findings' => []]);

    expect($insights['health_score'])->toBeNull()
        ->and($insights['seo'])->toBeNull()
        ->and($insights['projection'])->toBeNull();
    Http::assertNothingSent();
});

it('reuses a recent matching search snapshot when live credentials are unavailable', function (): void {
    Cache::flush();
    config(['services.dataforseo.login' => null, 'services.dataforseo.password' => null]);
    Http::preventStrayRequests();

    $snapshot = SeoSnapshot::factory()->create([
        'domain' => 'saved.example',
        'snapshot_date' => today()->subDays(10),
        'organic_keywords' => 186,
        'estimated_organic_traffic' => 3057.7519,
        'top_3_keywords' => 37,
        'top_10_keywords' => 115,
        'referring_domains' => 88,
        'backlinks' => 168,
    ]);
    SeoKeyword::factory()->create([
        'seo_snapshot_id' => $snapshot->id,
        'website_id' => $snapshot->website_id,
        'keyword' => 'zante events',
        'position' => 15,
        'search_volume' => 1000,
    ]);

    $pages = Mockery::mock(MarketingAuditPageCounter::class);
    $pages->shouldReceive('count')->once()->andReturn([
        'count' => 81,
        'partial' => false,
        'matching_domain' => 0,
        'mismatched_domain' => 81,
        'mismatched_host' => 'saved.test',
    ]);
    $research = new MarketingAuditResearch(app(DomainOverviewService::class), app(RankedKeywordsService::class), app(BacklinksService::class), $pages, app(MarketingAuditCompetitors::class), app(MarketingAuditAiVisibility::class));
    $insights = $research->forAudit('saved.example', 'https://saved.example', ['findings' => []]);

    expect($insights['pages_listed'])->toBe(81)
        ->and($insights['pages_mismatched_domain'])->toBe(81)
        ->and($insights['pages_mismatched_host'])->toBe('saved.test')
        ->and($insights['seo']['organic_keywords'])->toBe(186)
        ->and($insights['seo']['estimated_monthly_visits'])->toBe(3058)
        ->and($insights['seo']['referring_domains'])->toBe(88)
        ->and($insights['seo']['backlinks'])->toBe(168)
        ->and($insights['seo']['keywords'][0]['term'])->toBe('zante events')
        ->and($insights['projection']['baseline_monthly_visits'])->toBe(3058);
    Http::assertNothingSent();
});

it('reuses search estimates from a recent public audit for the same domain', function (): void {
    Cache::flush();
    config(['services.dataforseo.login' => null, 'services.dataforseo.password' => null]);
    Http::preventStrayRequests();

    WebsiteAudit::factory()->create([
        'domain' => 'previous.example',
        'status' => WebsiteAudit::STATUS_COMPLETED,
        'insights' => ['seo' => [
            'location_code' => 2826,
            'language_code' => 'en',
            'retrieved_at' => now()->subDays(5)->toIso8601String(),
            'organic_keywords' => 23,
            'estimated_monthly_visits' => 90,
            'top_3_keywords' => 1,
            'top_10_keywords' => 4,
            'referring_domains' => 12,
            'keywords' => [],
        ]],
    ]);

    $pages = Mockery::mock(MarketingAuditPageCounter::class);
    $pages->shouldReceive('count')->once()->andReturn(['count' => 4, 'partial' => false]);
    $research = new MarketingAuditResearch(app(DomainOverviewService::class), app(RankedKeywordsService::class), app(BacklinksService::class), $pages, app(MarketingAuditCompetitors::class), app(MarketingAuditAiVisibility::class));
    $insights = $research->forAudit('previous.example', 'https://previous.example', ['findings' => []]);

    expect($insights['seo']['organic_keywords'])->toBe(23)
        ->and($insights['seo']['referring_domains'])->toBe(12)
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
    $research = new MarketingAuditResearch(app(DomainOverviewService::class), app(RankedKeywordsService::class), app(BacklinksService::class), $pages, app(MarketingAuditCompetitors::class), app(MarketingAuditAiVisibility::class));
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

    expect($result)->toBe(['count' => 3, 'partial' => true, 'matching_domain' => 3, 'mismatched_domain' => 0, 'mismatched_host' => null]);
    Http::assertSentCount(3);
});

it('counts sitemap URLs on other hosts and identifies the wrong domain', function (): void {
    Http::fake([
        'https://example.com/sitemap.xml' => Http::response('<?xml version="1.0"?><urlset><url><loc>https://example.com/</loc></url><url><loc>https://other.example/about</loc></url></urlset>', 200),
    ]);

    $result = app(MarketingAuditPageCounter::class)->count('https://example.com');

    expect($result)->toBe(['count' => 2, 'partial' => false, 'matching_domain' => 1, 'mismatched_domain' => 1, 'mismatched_host' => 'other.example']);
    Http::assertSentCount(1);
});
