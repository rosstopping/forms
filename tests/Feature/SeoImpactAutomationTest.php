<?php

use App\Jobs\MeasureSeoImpact;
use App\Jobs\VerifySeoImpact;
use App\Models\ContentGeneration;
use App\Models\ContentPlan;
use App\Models\ContentRequest;
use App\Models\RemediationRun;
use App\Models\SearchConsoleConnection;
use App\Models\SeoImpact;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteHealthReport;
use App\Services\OptimisationDeploymentManager;
use App\Services\SearchConsoleClient;
use App\Services\SeoImpactAutomation;
use App\Services\SeoImpactEvaluator;
use App\Services\SeoImpactTracker;
use App\Services\WebsiteCrawler;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    $this->travelTo(Carbon::parse('2026-09-18 12:00:00 UTC'));
    Queue::fake();
    Http::preventStrayRequests();
    config(['forms.pixel_ui_enabled' => false]);
    $this->owner = User::factory()->create();
    $this->website = Website::factory()->for($this->owner, 'owner')->create();
    $this->website->domains()->create(['domain' => 'example.com', 'is_primary' => true]);
});

test('signed merge events activate tracking exactly once using the original brief and merge date', function (): void {
    config(['services.github.webhook_secret' => 'test-secret']);
    $plan = ContentPlan::factory()->for($this->website)->create();
    $generation = ContentGeneration::factory()->for($plan, 'plan')->create(['pull_request_number' => 12]);
    $request = ContentRequest::factory()->for($this->website)->create(['content_generation_id' => $generation->id, 'instructions' => 'Improve the garden office service information.']);
    $payload = json_encode(['action' => 'closed', 'repository' => ['id' => $generation->repository->repository_id], 'pull_request' => [
        'number' => 12, 'state' => 'closed', 'merged' => true, 'merged_at' => '2026-09-18T10:00:00Z',
        'body' => 'Updated https://example.com/services and ignore https://other.com/private',
    ]], JSON_THROW_ON_ERROR);
    foreach ([1, 2] as $attempt) {
        $this->call('POST', route('github.webhook'), [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_GITHUB_EVENT' => 'pull_request', 'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $payload, 'test-secret')], $payload)->assertSuccessful();
    }
    $impact = $request->seoImpact;
    expect($impact->automated)->toBeTrue()->and($impact->status)->toBe('measuring')
        ->and($impact->live_at->toIso8601String())->toBe('2026-09-18T10:00:00+00:00')
        ->and($impact->target_urls)->toBe(['https://example.com/services'])
        ->and($impact->hypothesis)->toBe($request->instructions)
        ->and($impact->verification_status)->toBe('pending')
        ->and(SeoImpact::count())->toBe(1);
    Queue::assertPushed(VerifySeoImpact::class, 1);
});

test('unmerged work does not activate and missing page scope surfaces as an exception', function (): void {
    $generation = ContentGeneration::factory()->for(ContentPlan::factory()->for($this->website), 'plan')->create();
    app(SeoImpactAutomation::class)->generationMerged($generation);
    expect(SeoImpact::count())->toBe(0);
    $generation->update(['merged_at' => now()]);
    app(SeoImpactAutomation::class)->generationMerged($generation);
    $impact = SeoImpact::sole();
    expect($impact->verification_status)->toBe('scope_missing')->and($impact->review_available_at)->not->toBeNull()->and($impact->next_measurement_at)->toBeNull();
    Queue::assertNotPushed(VerifySeoImpact::class);
});

test('audit merges track every affected page in bounded groups', function (): void {
    $report = WebsiteHealthReport::factory()->for($this->website)->create();
    $run = RemediationRun::factory()->for($report, 'report')->create(['merged_at' => now(), 'pull_request_number' => 15,
        'findings' => collect(range(1, 7))->map(fn ($n) => ['url' => 'https://example.com/page-'.$n, 'key' => 'page_title', 'label' => 'Missing title'])->all()]);
    app(SeoImpactAutomation::class)->remediationMerged($run);
    app(SeoImpactAutomation::class)->remediationMerged($run);
    expect(SeoImpact::count())->toBe(2)->and(SeoImpact::all()->pluck('target_urls')->flatten()->unique())->toHaveCount(7);
    Queue::assertPushed(VerifySeoImpact::class, 2);
});

test('live verification repeats original audit checks and limits failed retries', function (): void {
    $impact = SeoImpact::factory()->for($this->website)->create(['automated' => true, 'status' => 'measuring', 'live_at' => now(), 'evidence' => ['checks' => ['https://example.com/services' => ['page_title']]]]);
    Http::fake(['https://example.com/services' => Http::sequence()->push('<html><head><title>Garden offices</title></head><body>Service</body></html>', 200, ['Content-Type' => 'text/html'])->push('', 302, ['Location' => 'http://127.0.0.1/private'])->push('', 302)->push('', 302)]);
    (new VerifySeoImpact($impact))->handle(app(WebsiteCrawler::class), app(SeoImpactTracker::class));
    expect($impact->fresh()->verification_status)->toBe('passed')->and($impact->fresh()->next_verification_at)->toBeNull();
    for ($attempt = 0; $attempt < 3; $attempt++) {
        (new VerifySeoImpact($impact))->handle(app(WebsiteCrawler::class), app(SeoImpactTracker::class));
    }
    expect($impact->fresh()->verification_status)->toBe('attention')->and($impact->fresh()->review_available_at)->not->toBeNull()->and($impact->fresh()->next_verification_at)->toBeNull();
    Http::assertNotSent(fn ($request) => str_contains($request->url(), '127.0.0.1'));
});

test('automatic checkpoints prepare summaries and finish without a manual decision', function (): void {
    SearchConsoleConnection::factory()->for($this->website)->create(['property_url' => 'sc-domain:example.com']);
    $impact = SeoImpact::factory()->for($this->website)->create(['automated' => true, 'status' => 'measuring', 'live_at' => now()->subDays(32)]);
    $sample = fn ($clicks) => ['complete' => true, 'rows' => [], 'totals' => ['clicks' => $clicks, 'impressions' => 1000, 'ctr' => $clicks / 1000, 'position' => 8, 'reported_days' => 28]];
    $client = $this->mock(SearchConsoleClient::class);
    $client->shouldReceive('impactPerformance')->times(3)->andReturn($sample(100), $sample(150), $sample(160));
    (new MeasureSeoImpact($impact))->handle($client, app(SeoImpactEvaluator::class));
    $impact->refresh();
    expect($impact->status)->toBe('measuring')->and($impact->review_after_days)->toBe(56)->and($impact->automatic_summary)->toContain('Week 4', 'improved', 'not statistical significance')
        ->and($impact->next_measurement_at->gt(now()->addDays(20)))->toBeTrue()->and($impact->review_available_at)->not->toBeNull();
    $this->travel(28)->days();
    (new MeasureSeoImpact($impact))->handle($client, app(SeoImpactEvaluator::class));
    expect($impact->fresh()->status)->toBe('completed')->and($impact->fresh()->decision)->toBe('keep')->and($impact->fresh()->automatic_summary)->toContain('Week 8')
        ->and($impact->fresh()->next_measurement_at)->toBeNull()->and($impact->reviews()->count())->toBe(2);
});

test('sparse final results remain inconclusive and follow ups carry evidence without duplicate requests', function (): void {
    $impact = SeoImpact::factory()->for($this->website)->create(['automated' => true, 'status' => 'measuring', 'live_at' => now()->subDays(60), 'review_after_days' => 56]);
    $assessment = app(SeoImpactEvaluator::class)->assess(['complete' => false], ['complete' => false], 'clicks');
    $impact->update(app(SeoImpactAutomation::class)->checkpointUpdates($impact, $assessment, [], []));
    expect($impact->decision)->toBe('inconclusive')->and($impact->automatic_summary)->toContain('insufficient data');
    $this->actingAs($this->owner);
    foreach ([1, 2] as $attempt) {
        $this->post(route('admin.seo-impacts.followup', [$this->website, $impact]))->assertRedirect();
    }
    expect(ContentRequest::count())->toBe(1)->and(ContentRequest::sole()->seoImpact->target_queries)->toBe($impact->target_queries)
        ->and(ContentRequest::sole()->seoImpact->evidence['seo_impact_id'])->toBe($impact->id);
});

test('scheduler adopts existing manual measurements and surfaces existing completed checkpoints', function (): void {
    $impact = SeoImpact::factory()->for($this->website)->create(['status' => 'measuring', 'live_at' => now()->subDays(5)]);
    $this->artisan('seo:measure-impacts')->assertSuccessful();
    expect($impact->fresh()->automated)->toBeTrue()->and($impact->fresh()->next_verification_at)->not->toBeNull();
    Queue::assertPushed(VerifySeoImpact::class);
    Queue::assertPushed(MeasureSeoImpact::class);
});

test('basic page checks do not claim that an untracked content change was verified', function (): void {
    $impact = SeoImpact::factory()->for($this->website)->create(['automated' => true, 'status' => 'measuring', 'live_at' => now()]);
    Http::fake(['https://example.com/services' => Http::response('<html><title>Service</title></html>', 200, ['Content-Type' => 'text/html'])]);
    (new VerifySeoImpact($impact))->handle(app(WebsiteCrawler::class), app(SeoImpactTracker::class));
    expect($impact->fresh()->verification_status)->toBe('checked')->and($impact->fresh()->verification['pages'][0]['note'])->toContain('do not verify the wording');
});

test('a missing search connection surfaces an exception without losing the baseline', function (): void {
    $impact = SeoImpact::factory()->for($this->website)->create(['automated' => true, 'status' => 'measuring', 'live_at' => now()->subDays(32), 'baseline' => ['previous' => 'evidence']]);
    $client = $this->mock(SearchConsoleClient::class);
    $client->shouldNotReceive('impactPerformance');
    (new MeasureSeoImpact($impact))->handle($client, app(SeoImpactEvaluator::class));
    expect($impact->fresh()->measurement_error)->toContain('Connect the original')->and($impact->fresh()->review_available_at)->not->toBeNull()
        ->and($impact->fresh()->baseline)->toBe(['previous' => 'evidence']);
});

test('Pixel changes start the clock only after every linked change is published', function (): void {
    $request = ContentRequest::factory()->for($this->website)->create(['instructions' => 'Improve https://example.com/services']);
    $manager = app(OptimisationDeploymentManager::class);
    $first = $manager->createForUrl($this->website, 'https://example.com/services', ['type' => 'title', 'new_value' => 'Garden Offices', 'content_request_id' => $request->id], $this->owner);
    $second = $manager->createForUrl($this->website, 'https://example.com/services', ['type' => 'meta_description', 'new_value' => 'Explore our garden office services.', 'content_request_id' => $request->id], $this->owner);
    $manager->approve($first);
    $manager->deploy($first->refresh(), $this->owner);
    expect($request->seoImpact()->first()?->live_at)->toBeNull();
    $manager->approve($second);
    $manager->deploy($second->refresh(), $this->owner);
    expect($request->seoImpact()->sole()->status)->toBe('measuring')->and($request->seoImpact()->sole()->deployment_evidence)->toContain('Pixel');
    Queue::assertPushed(VerifySeoImpact::class, 1);
});
