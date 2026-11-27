<?php

use App\Models\WebsiteAudit;
use App\Services\MarketingAuditJourneyReview;
use App\Services\MarketingAuditOpportunity;
use App\Services\MarketingAuditPageCounter;
use App\Services\MarketingAuditResearch;
use App\Services\ProspectWebsiteAnalyzer;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;

it('extracts journey evidence during analysis and retains the existing technical findings', function (): void {
    Http::preventStrayRequests();
    Http::fake(['*' => Http::response('<h1>Plumbing visits</h1><a href="/contact">Contact our team</a>', 200)]);
    $analysis = app(ProspectWebsiteAnalyzer::class)->analyze('https://example.com');

    expect(data_get($analysis, 'journey_review.status'))->toBe('completed')
        ->and(data_get($analysis, 'journey_review.routes.0.label'))->toBe('Contact our team')
        ->and(collect($analysis['findings'])->pluck('key'))->not->toContain('form_requirements', 'enquiry_routes')
        ->and(collect($analysis['findings'])->pluck('key'))->toContain('website_reachable', 'viewport');
});

it('only shows saved journey evidence after the report is unlocked', function (): void {
    Queue::fake();
    Http::preventStrayRequests();
    $review = app(MarketingAuditJourneyReview::class)->inspect('<h1>PRIVATE JOURNEY EVIDENCE</h1><a href="/book">Book a visit</a>', 'https://example.com');
    $audit = WebsiteAudit::factory()->create([
        'status' => WebsiteAudit::STATUS_COMPLETED,
        'created_at' => now()->subMinute(),
        'insights' => ['journey_review' => $review, 'full_report' => ['status' => 'completed']],
        'findings' => [['title' => 'HTTPS enabled', 'severity' => 'passed']],
    ]);

    $this->get(route('marketing.website-audits.show', $audit))
        ->assertSuccessful()->assertDontSee('PRIVATE JOURNEY EVIDENCE')->assertDontSee('data-audit-journey-review', false);
    $this->get(URL::temporarySignedRoute('marketing.website-audits.full', now()->addHour(), $audit))
        ->assertSuccessful()->assertSee('Visitor journey')->assertSee('PRIVATE JOURNEY EVIDENCE')
        ->assertSee('View HTML evidence')->assertSee('Destination not tested')->assertSee($review['sample_sha256']);

    Http::assertNothingSent();
    Queue::assertNothingPushed();
});

it('renders unavailable evidence honestly on legacy reports without running new checks', function (): void {
    Queue::fake();
    Http::preventStrayRequests();
    $audit = WebsiteAudit::factory()->create(['status' => WebsiteAudit::STATUS_COMPLETED, 'created_at' => now()->subMinute(), 'insights' => ['full_report' => ['status' => 'completed']]]);
    $this->get(URL::temporarySignedRoute('marketing.website-audits.full', now()->addHour(), $audit))
        ->assertSuccessful()->assertSee('A visitor journey review wasn’t captured for this audit.');
    Http::assertNothingSent();
    Queue::assertNothingPushed();
});

it('escapes remote page text in the saved evidence view', function (): void {
    $review = app(MarketingAuditJourneyReview::class)->inspect('<h1>&lt;script&gt;REMOTE_PAYLOAD&lt;/script&gt;</h1>', 'https://example.com');
    $audit = WebsiteAudit::factory()->create(['status' => WebsiteAudit::STATUS_COMPLETED, 'created_at' => now()->subMinute(), 'insights' => ['journey_review' => $review, 'full_report' => ['status' => 'completed']]]);
    $this->get(URL::temporarySignedRoute('marketing.website-audits.full', now()->addHour(), $audit))
        ->assertSuccessful()->assertSee('&lt;script&gt;REMOTE_PAYLOAD&lt;/script&gt;', false)->assertDontSee('<script>REMOTE_PAYLOAD</script>', false);
});

it('persists journey evidence with the initial audit insights', function (): void {
    config(['services.dataforseo.login' => null, 'services.dataforseo.password' => null]);
    Http::preventStrayRequests();
    $pages = Mockery::mock(MarketingAuditPageCounter::class);
    $pages->shouldReceive('count')->once()->andReturn(['count' => null, 'partial' => false]);
    app()->instance(MarketingAuditPageCounter::class, $pages);
    $opportunity = Mockery::mock(MarketingAuditOpportunity::class);
    $opportunity->shouldReceive('forSite')->once()->andReturn(['projection' => null]);
    app()->instance(MarketingAuditOpportunity::class, $opportunity);
    $review = app(MarketingAuditJourneyReview::class)->inspect('<h1>Plumbing</h1>', 'https://example.com');
    $insights = app(MarketingAuditResearch::class)->forAudit('example.com', 'https://example.com', ['journey_review' => $review, 'findings' => [['severity' => 'passed']]]);
    $audit = WebsiteAudit::factory()->create(['insights' => $insights]);

    expect($audit->fresh()->insights['journey_review'])->toBe($review)
        ->and($insights['health_score'])->toBe(100);
    Http::assertNothingSent();
});
