<?php

use App\Jobs\AnalyzeSeoProspectCandidate;
use App\Models\SeoProspectCandidate;
use App\Models\SeoProspectSearch;
use App\Services\ProspectWebsiteAnalyzer;
use App\Services\SeoOpportunityScoringService;
use App\Services\SeoProspectCandidateAnalyzer;
use App\Services\WebsiteCrawler;
use App\Services\WebsiteMigrationAssessor;
use Illuminate\Support\Facades\Http;

it('analyses a suitable candidate with the existing crawler audit and contact enrichment', function (): void {
    config()->set('forms.health_reports.crawl_delay_ms', 0);
    config()->set('forms.health_reports.max_depth', 2);
    $candidate = SeoProspectCandidate::factory()->for(
        SeoProspectSearch::factory()->state(['maximum_pages' => 20]),
        'search',
    )->create(['domain' => 'example.com', 'website_url' => 'https://example.com']);
    $prospectAnalyzer = Mockery::mock(ProspectWebsiteAnalyzer::class);
    $prospectAnalyzer->shouldReceive('analyze')->once()->with('https://example.com')->andReturn([
        'score' => 45,
        'findings' => [['key' => 'meta_description', 'severity' => 'warning', 'message' => 'No meta description was found.']],
        'contacts' => ['emails' => [['value' => 'hello@example.com', 'source_url' => 'https://example.com/contact']], 'phones' => [], 'contact_page_url' => 'https://example.com/contact', 'contact_form_url' => null],
    ]);
    Http::fake([
        'https://example.com/sitemap.xml' => Http::response('<urlset><url><loc>https://example.com/services</loc></url></urlset>', 200, ['Content-Type' => 'application/xml']),
        'https://example.com/' => Http::response('<html><head><title>Home</title></head><body><h1>Home</h1><a href="/services">Services</a></body></html>', 200, ['Content-Type' => 'text/html']),
        'https://example.com/services' => Http::response('<html><head><title>Services</title></head><body><h1>Services</h1></body></html>', 200, ['Content-Type' => 'text/html']),
    ]);

    $result = (new SeoProspectCandidateAnalyzer(
        app(WebsiteCrawler::class),
        $prospectAnalyzer,
        app(WebsiteMigrationAssessor::class),
    ))->analyze($candidate);

    expect($result['page_count'])->toBe(2)
        ->and($result['audit_score'])->toBe(45)
        ->and(collect($result['audit_findings'])->pluck('key'))->toContain('meta_description')
        ->and(data_get($result, 'contact_details.emails.0.value'))->toBe('hello@example.com')
        ->and($result['migration_difficulty'])->toBe('easy')
        ->and($result['qualification_status'])->toBe('suitable');
});

it('retains an oversized candidate and skips its expensive audit and contact enrichment', function (): void {
    config()->set('forms.health_reports.crawl_delay_ms', 0);
    config()->set('forms.health_reports.max_depth', 1);
    $candidate = SeoProspectCandidate::factory()->for(
        SeoProspectSearch::factory()->state(['maximum_pages' => 2]),
        'search',
    )->create(['domain' => 'large.example', 'website_url' => 'https://large.example']);
    $prospectAnalyzer = Mockery::mock(ProspectWebsiteAnalyzer::class);
    $prospectAnalyzer->shouldNotReceive('analyze');
    Http::fake([
        'https://large.example/sitemap.xml' => Http::response('<urlset><url><loc>https://large.example/one</loc></url><url><loc>https://large.example/two</loc></url><url><loc>https://large.example/three</loc></url></urlset>', 200, ['Content-Type' => 'application/xml']),
        'https://large.example/*' => Http::response('<html><head><title>Page</title></head><body><h1>Page</h1></body></html>', 200, ['Content-Type' => 'text/html']),
    ]);

    $result = (new SeoProspectCandidateAnalyzer(
        app(WebsiteCrawler::class),
        $prospectAnalyzer,
        app(WebsiteMigrationAssessor::class),
    ))->analyze($candidate);

    expect($result['page_count'])->toBeGreaterThan(2)
        ->and($result['audit_score'])->toBeNull()
        ->and($result['audit_findings'])->toBe([])
        ->and($result['migration_difficulty'])->toBe('medium')
        ->and($result['qualification_status'])->toBe('too_large')
        ->and($result['migration_difficulty_reason'])->toContain('page limit');
});

it('counts canonical pages once and ignores noindex pagination and tag archives', function (): void {
    config()->set('forms.health_reports.crawl_delay_ms', 0);
    config()->set('forms.health_reports.max_depth', 1);
    $candidate = SeoProspectCandidate::factory()->for(
        SeoProspectSearch::factory()->state(['maximum_pages' => 20]),
        'search',
    )->create(['domain' => 'example.com', 'website_url' => 'https://example.com']);
    $prospectAnalyzer = Mockery::mock(ProspectWebsiteAnalyzer::class);
    $prospectAnalyzer->shouldReceive('analyze')->once()->andReturn(['score' => 20, 'findings' => [], 'contacts' => ['emails' => [], 'phones' => [], 'contact_page_url' => null, 'contact_form_url' => null]]);
    Http::fake([
        'https://example.com/sitemap.xml' => Http::response('<urlset><url><loc>https://example.com/service-a</loc></url><url><loc>https://example.com/service-b</loc></url><url><loc>https://example.com/hidden</loc></url><url><loc>https://example.com/page/2</loc></url><url><loc>https://example.com/tag/roofing</loc></url></urlset>', 200, ['Content-Type' => 'application/xml']),
        'https://example.com/' => Http::response('<html><head><title>Home</title></head><body><h1>Home</h1></body></html>', 200, ['Content-Type' => 'text/html']),
        'https://example.com/service-a' => Http::response('<html><head><title>Service A</title><link rel="canonical" href="/shared-service"></head><body><h1>Service A</h1></body></html>', 200, ['Content-Type' => 'text/html']),
        'https://example.com/service-b' => Http::response('<html><head><title>Service B</title><link rel="canonical" href="https://example.com/shared-service"></head><body><h1>Service B</h1></body></html>', 200, ['Content-Type' => 'text/html']),
        'https://example.com/hidden' => Http::response('<html><head><title>Hidden</title><meta name="robots" content="noindex"></head><body><h1>Hidden</h1></body></html>', 200, ['Content-Type' => 'text/html']),
    ]);

    $result = (new SeoProspectCandidateAnalyzer(
        app(WebsiteCrawler::class),
        $prospectAnalyzer,
        app(WebsiteMigrationAssessor::class),
    ))->analyze($candidate);

    expect($result['page_count'])->toBe(2);
    Http::assertNotSent(fn ($request): bool => in_array($request->url(), ['https://example.com/page/2', 'https://example.com/tag/roofing'], true));
});

it('marks only the failed candidate when an isolated analysis job exhausts retries', function (): void {
    $search = SeoProspectSearch::factory()->create(['candidate_count' => 2, 'status' => 'analyzing']);
    $failedCandidate = SeoProspectCandidate::factory()->for($search, 'search')->create();
    $pendingCandidate = SeoProspectCandidate::factory()->for($search, 'search')->create();
    $exception = new RuntimeException('Candidate website timed out.');

    (new AnalyzeSeoProspectCandidate($failedCandidate))->failed($exception);

    expect($failedCandidate->refresh()->qualification_status)->toBe('analysis_failed')
        ->and($failedCandidate->analysis_error)->toBe('Candidate website timed out.')
        ->and($pendingCandidate->refresh()->qualification_status)->toBe('pending_analysis')
        ->and($search->refresh()->status)->toBe('analyzing');
});

it('persists successful job analysis and completes search progress idempotently', function (): void {
    $search = SeoProspectSearch::factory()->create(['candidate_count' => 1, 'status' => 'analyzing']);
    $candidate = SeoProspectCandidate::factory()->for($search, 'search')->create();
    $analyzer = Mockery::mock(SeoProspectCandidateAnalyzer::class);
    $analyzer->shouldReceive('analyze')->twice()->with(Mockery::on(fn (SeoProspectCandidate $model): bool => $model->is($candidate)))->andReturn([
        'page_count' => 8,
        'audit_score' => 45,
        'audit_findings' => [['key' => 'meta_description', 'severity' => 'warning']],
        'contact_details' => ['emails' => [['value' => 'hello@example.com']]],
        'migration_difficulty' => 'easy',
        'migration_difficulty_reason' => 'Small brochure website.',
        'observations' => ['page_count_band' => 'excellent'],
        'qualification_status' => 'suitable',
    ]);
    $job = new AnalyzeSeoProspectCandidate($candidate);

    $scoringService = app(SeoOpportunityScoringService::class);
    $job->handle($analyzer, $scoringService);
    $job->handle($analyzer, $scoringService);

    expect($job->uniqueId())->toBe((string) $candidate->id)
        ->and($candidate->refresh()->qualification_status)->toBe('suitable')
        ->and($candidate->page_count)->toBe(8)
        ->and(data_get($candidate->contact_details, 'emails.0.value'))->toBe('hello@example.com')
        ->and($candidate->analysis_error)->toBeNull()
        ->and($candidate->analyzed_at)->not->toBeNull()
        ->and($candidate->opportunity_score)->toBe(49)
        ->and(data_get($candidate->score_breakdown, 'ranking.maximum'))->toBe(40)
        ->and(data_get($candidate->observations, 'outreach.0.type'))->toBe('crawl')
        ->and($search->refresh()->status)->toBe('analyzed')
        ->and($search->suitable_count)->toBe(1);
});
