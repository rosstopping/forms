<?php

use App\Models\SeoKeyword;
use App\Models\SeoSnapshot;
use App\Models\WebsiteAudit;
use App\Services\DataForSEO\BacklinksService;
use App\Services\DataForSEO\DomainOverviewService;
use App\Services\DataForSEO\RankedKeywordsService;
use App\Services\MarketingAuditAiVisibility;
use App\Services\MarketingAuditCompetitors;
use App\Services\MarketingAuditOpportunity;
use App\Services\MarketingAuditPageCounter;
use App\Services\MarketingAuditResearch;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

it('builds the public projection and cached page count before deferring private research', function (): void {
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
        '*/ranked_keywords/live' => function ($request) {
            $pageOne = $request->data()[0]['filters'][2][2] === 10;

            return Http::response([
                'status_code' => 20000,
                'tasks' => [[
                    'status_code' => 20000,
                    'cost' => 0.0144,
                    'result' => [['items' => [[
                        'keyword_data' => [
                            'keyword' => $pageOne ? 'garden office design' : 'garden office fitters',
                            'location_code' => 2826,
                            'language_code' => 'en',
                            'keyword_info' => ['search_volume' => $pageOne ? 500 : 1000],
                        ],
                        'ranked_serp_element' => ['serp_item' => ['rank_group' => $pageOne ? 3 : 15, 'url' => 'https://example.com/garden-offices']],
                    ]]]],
                ]],
            ]);
        },
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
    $pages->shouldReceive('count')->once()->with('https://example.com')->andReturn(['count' => 18, 'partial' => false]);
    $research = new MarketingAuditResearch(app(DomainOverviewService::class), app(RankedKeywordsService::class), app(BacklinksService::class), $pages, app(MarketingAuditCompetitors::class), app(MarketingAuditAiVisibility::class), app(MarketingAuditOpportunity::class));
    $analysis = ['findings' => [['severity' => 'passed'], ['severity' => 'warning']]];

    $insights = $research->forAudit('example.com', 'https://example.com', $analysis);
    $again = $research->forAudit('example.com', 'https://example.com', $analysis);

    expect($insights['health_score'])->toBe(50)
        ->and($insights['pages_listed'])->toBe(18)
        ->and(array_key_exists('competitors', $insights))->toBeFalse()
        ->and(array_key_exists('ai_visibility', $insights))->toBeFalse()
        ->and($insights['full_report']['status'])->toBe('deferred')
        ->and($insights['seo']['organic_keywords'])->toBe(42)
        ->and($insights['seo']['top_10_keywords'])->toBe(7)
        ->and($insights['seo']['estimated_monthly_visits'])->toBe(120)
        ->and($insights['seo']['referring_domains'])->toBeNull()
        ->and($insights['seo']['keywords'][0]['position'])->toBe(15)
        ->and($insights['seo']['cost_usd'])->toBe(0.02652)
        ->and($insights['projection'])->toBeNull()
        ->and($insights['opportunity']['status'])->toBe('unavailable')
        ->and($again['seo'])->toBe($insights['seo']);
    Http::assertSentCount(2);
    Http::assertNotSent(fn ($request): bool => str_contains($request->url(), 'backlinks') || str_contains($request->url(), 'competitors')
        || (str_contains($request->url(), 'ranked_keywords/live') && $request->data()[0]['filters'][2][2] === 10));

    $audit = WebsiteAudit::factory()->create(['domain' => 'example.com', 'website_url' => 'https://example.com', 'insights' => $insights]);
    $full = $research->forFullReport($audit);
    expect($full['pages_listed'])->toBe(18)
        ->and($full['seo']['referring_domains'])->toBe(12)
        ->and(array_column($full['seo']['keywords'], 'position'))->toBe([3, 15])
        ->and($full['seo']['cost_usd'])->toBe(0.06496)
        ->and($full['projection'])->toBe($insights['projection']);
    $audit->update(['insights' => $full]);
    expect($research->forFullReport($audit))->toBe($full);
    Http::assertSentCount(5);
    Http::assertSent(fn ($request): bool => str_contains($request->url(), 'ranked_keywords/live') && $request->data()[0]['limit'] === 10 && $request->data()[0]['filters'][2][2] === 10);
    Http::assertSent(fn ($request): bool => str_contains($request->url(), 'ranked_keywords/live') && $request->data()[0]['limit'] === 10 && $request->data()[0]['filters'][0][2] === 11);
});

it('prioritises page-one and striking-distance terms, falling back to other rankings', function (): void {
    $research = app(MarketingAuditResearch::class);
    $keywords = [
        ['term' => 'distant high volume', 'position' => 65, 'monthly_searches' => 90000],
        ['term' => 'striking term', 'position' => 14, 'monthly_searches' => 100],
        ['term' => 'page-one term', 'position' => 2, 'monthly_searches' => 50],
        ['term' => 'strongest term', 'position' => 1, 'monthly_searches' => 10],
    ];

    $highlights = $research->rankingHighlights(['keywords' => $keywords]);

    expect(array_column($highlights['page_one'], 'term'))->toBe(['strongest term', 'page-one term'])
        ->and(array_column($highlights['striking_distance'], 'term'))->toBe(['striking term'])
        ->and($highlights['other'])->toBe([])
        ->and(array_column($research->rankingHighlights(['keywords' => array_slice($keywords, 0, 1)])['other'], 'term'))->toBe(['distant high volume']);
});

it('fetches other rankings only when page one and striking distance are empty', function (): void {
    Cache::flush();
    config(['services.dataforseo.login' => 'test', 'services.dataforseo.password' => 'test']);
    Http::fake([
        '*/domain_rank_overview/live' => Http::response([
            'status_code' => 20000,
            'tasks' => [['status_code' => 20000, 'cost' => 0.01, 'result' => [['items' => [['metrics' => ['organic' => ['count' => 1, 'etv' => 1]]]]]]]],
        ]),
        '*/ranked_keywords/live' => function ($request) {
            $fallback = $request->data()[0]['limit'] === 20;

            return Http::response([
                'status_code' => 20000,
                'tasks' => [['status_code' => 20000, 'cost' => 0.01, 'result' => [['items' => $fallback ? [[
                    'keyword_data' => ['keyword' => 'distant search', 'location_code' => 2826, 'language_code' => 'en', 'keyword_info' => ['search_volume' => 200]],
                    'ranked_serp_element' => ['serp_item' => ['rank_group' => 65, 'url' => 'https://far.example/page']],
                ]] : []]]]],
            ]);
        },
        '*/backlinks/summary/live' => Http::response([
            'status_code' => 20000,
            'tasks' => [['status_code' => 20000, 'cost' => 0.01, 'result' => [['backlinks' => 0, 'referring_domains' => 0]]]],
        ]),
        '*/competitors_domain/live' => Http::response([
            'status_code' => 20000,
            'tasks' => [['status_code' => 20000, 'cost' => 0.01, 'result' => [['items' => []]]]],
        ]),
    ]);

    $pages = Mockery::mock(MarketingAuditPageCounter::class);
    $pages->shouldReceive('count')->once()->andReturn(['count' => 1, 'partial' => false]);
    $research = new MarketingAuditResearch(app(DomainOverviewService::class), app(RankedKeywordsService::class), app(BacklinksService::class), $pages, app(MarketingAuditCompetitors::class), app(MarketingAuditAiVisibility::class), app(MarketingAuditOpportunity::class));
    $insights = $research->forAudit('far.example', 'https://far.example', ['findings' => []]);
    expect($insights['seo']['keywords'])->toBe([])->and($insights['projection'])->toBeNull();
    Http::assertSentCount(2);
    $audit = WebsiteAudit::factory()->create(['domain' => 'far.example', 'website_url' => 'https://far.example', 'insights' => $insights]);
    $insights = $research->forFullReport($audit);

    expect($insights['seo']['keywords'][0]['term'])->toBe('distant search')
        ->and($insights['seo']['sample_size'])->toBe(1);
    Http::assertSent(fn ($request): bool => str_contains($request->url(), 'ranked_keywords/live') && $request->data()[0]['limit'] === 20);
    Http::assertSentCount(6);
});

it('does not invent search data or a forecast when the provider is unavailable', function (): void {
    Cache::flush();
    config(['services.dataforseo.login' => null, 'services.dataforseo.password' => null]);
    Http::preventStrayRequests();

    $pages = Mockery::mock(MarketingAuditPageCounter::class);
    $pages->shouldReceive('count')->once()->andReturn(['count' => null, 'partial' => false]);
    $research = new MarketingAuditResearch(app(DomainOverviewService::class), app(RankedKeywordsService::class), app(BacklinksService::class), $pages, app(MarketingAuditCompetitors::class), app(MarketingAuditAiVisibility::class), app(MarketingAuditOpportunity::class));
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
    SeoKeyword::factory()->create([
        'seo_snapshot_id' => $snapshot->id,
        'website_id' => $snapshot->website_id,
        'keyword' => 'zante nightlife',
        'position' => 2,
        'search_volume' => 100,
    ]);
    SeoKeyword::factory()->create([
        'seo_snapshot_id' => $snapshot->id,
        'website_id' => $snapshot->website_id,
        'keyword' => 'distant search',
        'position' => 65,
        'search_volume' => 90000,
    ]);

    $pages = Mockery::mock(MarketingAuditPageCounter::class);
    $pages->shouldReceive('count')->once()->andReturn([
        'count' => 81,
        'partial' => false,
        'matching_domain' => 0,
        'mismatched_domain' => 81,
        'mismatched_host' => 'saved.test',
    ]);
    $research = new MarketingAuditResearch(app(DomainOverviewService::class), app(RankedKeywordsService::class), app(BacklinksService::class), $pages, app(MarketingAuditCompetitors::class), app(MarketingAuditAiVisibility::class), app(MarketingAuditOpportunity::class));
    $insights = $research->forAudit('saved.example', 'https://saved.example', ['findings' => []]);
    Http::assertNothingSent();
    $audit = WebsiteAudit::factory()->create(['domain' => 'saved.example', 'website_url' => 'https://saved.example', 'insights' => $insights]);
    $insights = $research->forFullReport($audit);

    expect($insights['pages_listed'])->toBe(81)
        ->and($insights['pages_mismatched_domain'])->toBe(81)
        ->and($insights['pages_mismatched_host'])->toBe('saved.test')
        ->and($insights['seo']['organic_keywords'])->toBe(186)
        ->and($insights['seo']['estimated_monthly_visits'])->toBe(3058)
        ->and($insights['seo']['referring_domains'])->toBe(88)
        ->and($insights['seo']['backlinks'])->toBe(168)
        ->and(array_column($insights['seo']['keywords'], 'term'))->toBe(['zante nightlife', 'zante events'])
        ->and($insights['projection'])->toBeNull();
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
    $pages->shouldReceive('count')->once()->andReturn(['count' => null, 'partial' => false]);
    $research = new MarketingAuditResearch(app(DomainOverviewService::class), app(RankedKeywordsService::class), app(BacklinksService::class), $pages, app(MarketingAuditCompetitors::class), app(MarketingAuditAiVisibility::class), app(MarketingAuditOpportunity::class));
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
    $pages->shouldReceive('count')->once()->andReturn(['count' => null, 'partial' => false]);
    $research = new MarketingAuditResearch(app(DomainOverviewService::class), app(RankedKeywordsService::class), app(BacklinksService::class), $pages, app(MarketingAuditCompetitors::class), app(MarketingAuditAiVisibility::class), app(MarketingAuditOpportunity::class));
    $insights = $research->forAudit('new-domain.example', 'https://new-domain.example', ['findings' => []]);

    expect($insights['seo']['organic_keywords'])->toBe(0)
        ->and($insights['seo']['keywords'])->toBe([])
        ->and($insights['projection'])->toBeNull();
    Http::assertSentCount(1);

    Cache::flush();
    $audit = WebsiteAudit::factory()->create([
        'domain' => 'new-domain.example',
        'insights' => ['seo' => null, 'pages_listed' => 1, 'competitors' => null, 'ai_visibility' => null],
    ]);
    $full = $research->forFullReport($audit);
    expect($full['seo']['organic_keywords'])->toBe(0)
        ->and($full['seo']['backlinks'])->toBe(0);
    Http::assertSentCount(3);
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
