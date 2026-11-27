<?php

use App\Jobs\CheckAuditServicePages;
use App\Jobs\CheckMarketingAuditAiVisibility;
use App\Jobs\GenerateWebsiteAuditFullReport;
use App\Models\WebsiteAudit;
use App\Services\MarketingAuditAiVisibility;
use App\Services\MarketingAuditResearch;
use App\Services\ServicePageFinder;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;

beforeEach(function (): void {
    Queue::fake();
    Http::preventStrayRequests();
});

function coverageReport(): array
{
    return ['suggestions' => [['service' => 'Boiler servicing', 'title' => 'Boiler servicing page', 'quote' => 'We offer boiler servicing throughout Doncaster.', 'source_url' => 'https://example.com/', 'questions' => ['What is included?']]], 'covered' => [], 'supported' => true, 'partial' => true, 'checked_at' => now()->toIso8601String(), 'pages' => [['title' => 'Home', 'url' => 'https://example.com/']]];
}

it('queues service page coverage once alongside full report research', function (): void {
    $audit = WebsiteAudit::factory()->create(['created_at' => now()->subMinute(), 'status' => WebsiteAudit::STATUS_COMPLETED, 'insights' => ['full_report' => ['status' => 'queued']]]);
    $research = Mockery::mock(MarketingAuditResearch::class);
    $research->shouldReceive('forFullReport')->once()->andReturn(['seo' => null]);
    $job = new GenerateWebsiteAuditFullReport($audit);
    $job->handle($research);
    $job->handle($research);
    expect(data_get($audit->fresh()->insights, 'service_pages.status'))->toBe('queued');
    Queue::assertPushed(CheckAuditServicePages::class, 1);
});

it('does not research before the full report is completed or on a duplicate job', function (): void {
    $audit = WebsiteAudit::factory()->create(['created_at' => now()->subMinute(), 'status' => WebsiteAudit::STATUS_COMPLETED, 'insights' => ['full_report' => ['status' => 'deferred'], 'service_pages' => ['status' => 'queued']]]);
    $finder = Mockery::mock(ServicePageFinder::class);
    $finder->shouldNotReceive('cachedFind');
    (new CheckAuditServicePages($audit))->handle($finder);
    $audit->update(['insights' => ['full_report' => ['status' => 'completed'], 'service_pages' => ['status' => 'completed', 'result' => coverageReport()]]]);
    (new CheckAuditServicePages($audit))->handle($finder);
});

it('stores coverage without overwriting concurrent AI findings and never reruns on reads', function (): void {
    $audit = WebsiteAudit::factory()->create(['created_at' => now()->subMinute(), 'status' => WebsiteAudit::STATUS_COMPLETED, 'insights' => ['full_report' => ['status' => 'completed'], 'service_pages' => ['status' => 'queued']]]);
    $finder = Mockery::mock(ServicePageFinder::class);
    $finder->shouldReceive('cachedFind')->once()->andReturnUsing(function () use ($audit): array {
        $current = $audit->fresh();
        $current->update(['insights' => [...$current->insights, 'ai_visibility' => ['status' => 'completed', 'results' => ['preserved']]]]);

        return coverageReport();
    });
    $job = new CheckAuditServicePages($audit);
    $job->handle($finder);
    $job->handle($finder);
    expect(data_get($audit->fresh()->insights, 'ai_visibility.results'))->toBe(['preserved']);
    $url = URL::temporarySignedRoute('marketing.website-audits.full', now()->addDay(), $audit);
    $this->get($url)->assertOk()->assertSee('Service page opportunities.')->assertSee('Boiler servicing page')->assertSee('What is included?');
    $this->get($url)->assertOk();
    Http::assertNothingSent();
});

it('preserves coverage when an AI visibility check completes concurrently', function (): void {
    $audit = WebsiteAudit::factory()->create(['created_at' => now()->subMinute(), 'status' => WebsiteAudit::STATUS_COMPLETED, 'insights' => ['full_report' => ['status' => 'completed'], 'ai_visibility' => ['status' => 'pending', 'questions' => ['Who offers boiler servicing?']]]]);
    $visibility = Mockery::mock(MarketingAuditAiVisibility::class);
    $visibility->shouldReceive('available')->once()->andReturnTrue();
    $visibility->shouldReceive('check')->once()->andReturnUsing(function () use ($audit): array {
        $current = $audit->fresh();
        $current->update(['insights' => [...$current->insights, 'service_pages' => ['status' => 'completed', 'result' => coverageReport()]]]);

        return ['status' => 'completed', 'website_mentioned' => false, 'website_cited' => false];
    });
    (new CheckMarketingAuditAiVisibility($audit))->handle($visibility);
    expect(data_get($audit->fresh()->insights, 'service_pages.result.suggestions.0.service'))->toBe('Boiler servicing');
});

it('keeps failures isolated and report status private until unlocked', function (): void {
    Exceptions::fake();
    $audit = WebsiteAudit::factory()->create(['created_at' => now()->subMinute(), 'status' => WebsiteAudit::STATUS_COMPLETED, 'insights' => ['health_score' => 80, 'full_report' => ['status' => 'completed'], 'service_pages' => ['status' => 'queued']]]);
    $finder = Mockery::mock(ServicePageFinder::class);
    $finder->shouldReceive('cachedFind')->once()->andThrow(new RuntimeException('Private provider failure'));
    (new CheckAuditServicePages($audit))->handle($finder);
    expect(data_get($audit->fresh()->insights, 'service_pages.status'))->toBe('unavailable')->and(data_get($audit->fresh()->insights, 'health_score'))->toBe(80);
    $status = route('marketing.website-audits.status', $audit);
    $this->getJson($status)->assertOk()->assertJsonPath('service_pages_status', null);
    $signed = URL::temporarySignedRoute('marketing.website-audits.status', now()->addDay(), $audit);
    $this->getJson($signed)->assertOk()->assertJsonPath('service_pages_status', 'unavailable');
    $full = URL::temporarySignedRoute('marketing.website-audits.full', now()->addDay(), $audit);
    $this->get($full)->assertOk()->assertSee('The service page check couldn’t finish.')->assertDontSee('Private provider failure');
    $this->get(route('marketing.website-audits.show', $audit))->assertOk()->assertDontSee('Service page opportunities.');
});
