<?php

use App\Jobs\CheckMarketingAuditAiVisibility;
use App\Jobs\GenerateWebsiteAudit;
use App\Jobs\GenerateWebsiteAuditFullReport;
use App\Models\User;
use App\Models\WebsiteAudit;
use App\Services\MarketingAuditAiVisibility;
use App\Services\MarketingAuditResearch;
use App\Services\MarketingAuditScreenshot;
use App\Services\ProspectWebsiteAnalyzer;
use App\Support\MarketingJourney;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;

it('does not dispatch private research or AI checks during the initial audit', function (): void {
    Queue::fake();
    $audit = WebsiteAudit::factory()->create();
    $analyzer = Mockery::mock(ProspectWebsiteAnalyzer::class);
    $analyzer->shouldReceive('analyze')->once()->andReturn(['score' => 50, 'findings' => [], 'contacts' => []]);
    $research = Mockery::mock(MarketingAuditResearch::class);
    $research->shouldReceive('forAudit')->once()->andReturn([
        'health_score' => 50,
        'seo' => null,
        'projection' => null,
        'full_report' => ['status' => 'deferred'],
        'ai_visibility' => ['status' => 'pending', 'questions' => ['Which businesses would you recommend for garden offices?']],
    ]);
    $screenshot = Mockery::mock(MarketingAuditScreenshot::class);
    $screenshot->shouldReceive('capture')->once();

    (new GenerateWebsiteAudit($audit))->handle($analyzer, app(MarketingJourney::class), $research, $screenshot);
    expect($audit->refresh()->status)->toBe(WebsiteAudit::STATUS_COMPLETED);
    Queue::assertNothingPushed();
});

it('queues the full research once when an admin chooses to view the full report', function (): void {
    Queue::fake();
    $audit = WebsiteAudit::factory()->create([
        'status' => WebsiteAudit::STATUS_COMPLETED,
        'created_at' => now()->subMinute(),
        'expires_at' => now()->subDay(),
        'insights' => ['health_score' => 75, 'full_report' => ['status' => 'deferred']],
    ]);
    $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));
    $url = route('admin.onboarding.audits.generate', $audit);
    $this->post($url)->assertRedirect(route('admin.onboarding.audits.show', $audit));
    $this->post($url)->assertRedirect();
    expect(data_get($audit->refresh()->insights, 'full_report.status'))->toBe('queued')
        ->and(data_get($audit->insights, 'full_report.requested_at'))->not->toBeNull()
        ->and($audit->insights['health_score'])->toBe(75)
        ->and($audit->status)->toBe(WebsiteAudit::STATUS_COMPLETED);
    Queue::assertPushed(GenerateWebsiteAuditFullReport::class, 1);
    Queue::assertNotPushed(CheckMarketingAuditAiVisibility::class);
    $this->get(route('admin.onboarding.audits.show', $audit))->assertSuccessful()->assertSee('Preparing the rest of this report.');
    $this->get(route('admin.onboarding.audits.status', $audit))->assertSuccessful()
        ->assertExactJson(['full_report_status' => 'queued', 'ai_visibility_status' => null]);
    Queue::assertPushed(GenerateWebsiteAuditFullReport::class, 1);
});

it('queues enrichment once after email capture while keeping every report read free of research', function (): void {
    Queue::fake();
    Mail::fake();
    Http::preventStrayRequests();
    $audit = WebsiteAudit::factory()->create([
        'status' => WebsiteAudit::STATUS_COMPLETED,
        'created_at' => now()->subMinute(),
        'insights' => ['health_score' => 75, 'full_report' => ['status' => 'deferred']],
    ]);
    $publicUrl = route('marketing.website-audits.show', $audit);
    $this->get($publicUrl)->assertSuccessful()->assertSee('View my report');
    $shareUrl = URL::temporarySignedRoute('marketing.website-audits.full', now()->addDay(), $audit);
    $this->get($shareUrl)->assertSuccessful()->assertSee('The extra research hasn’t been run yet.')->assertDontSee('Load full research');
    Queue::assertNothingPushed();
    $this->post(route('marketing.website-audits.email-report', $audit), ['email' => 'owner@example.com'])->assertRedirect();
    $this->post(route('marketing.website-audits.email-report', $audit), ['email' => 'owner@example.com'])->assertRedirect();
    $this->get($publicUrl)->assertSuccessful()->assertSee('Preparing the rest of this report.')->assertDontSee('data-audit-email-gate', false);
    $this->get($shareUrl)->assertSuccessful()->assertSee('Preparing the rest of this report.');
    $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));
    $this->post(route('admin.onboarding.audits.generate', $audit))->assertRedirect();
    Queue::assertPushed(GenerateWebsiteAuditFullReport::class, 1);
    Http::assertNothingSent();

});

it('does not let visitors or ordinary users trigger private research or read its status', function (bool $signedIn): void {
    Queue::fake();
    $audit = WebsiteAudit::factory()->create(['status' => WebsiteAudit::STATUS_COMPLETED, 'created_at' => now()->subMinute()]);
    if ($signedIn) {
        $this->actingAs(User::factory()->create());
    }
    foreach (['generate', 'status'] as $action) {
        $response = $action === 'generate'
            ? $this->post(route('admin.onboarding.audits.'.$action, $audit))
            : $this->get(route('admin.onboarding.audits.'.$action, $audit));
        if ($signedIn) {
            $response->assertForbidden();
        } else {
            $response->assertRedirect(route('login'));
        }
    }
    Queue::assertNothingPushed();
})->with([false, true]);

it('does not request full research for an unfinished or failed initial audit', function (string $status): void {
    Queue::fake();
    $audit = WebsiteAudit::factory()->create(['status' => $status, 'created_at' => now()->subMinute()]);
    $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]))
        ->post(route('admin.onboarding.audits.generate', $audit))->assertConflict();
    Queue::assertNothingPushed();
})->with([WebsiteAudit::STATUS_PENDING, WebsiteAudit::STATUS_RUNNING, WebsiteAudit::STATUS_FAILED]);

it('saves private research then runs the sampled AI checks only once', function (): void {
    Queue::fake();
    $audit = WebsiteAudit::factory()->create([
        'status' => WebsiteAudit::STATUS_COMPLETED,
        'email' => 'owner@example.com',
        'insights' => ['health_score' => 75, 'projection' => ['six_month_low' => 100], 'full_report' => ['status' => 'queued', 'requested_at' => now()->toIso8601String()]],
    ]);
    $questions = ['Which businesses would you recommend for garden offices?', 'Which businesses would you recommend for office builders?'];
    $research = Mockery::mock(MarketingAuditResearch::class);
    $research->shouldReceive('forFullReport')->once()->andReturn([
        'pages_listed' => 12,
        'competitors' => ['domain' => 'rival.example'],
        'ai_visibility' => ['status' => 'pending', 'questions' => $questions],
    ]);
    $job = new GenerateWebsiteAuditFullReport($audit);
    $job->handle($research);
    $job->handle($research);
    expect(data_get($audit->refresh()->insights, 'full_report.status'))->toBe('completed')
        ->and($audit->insights['pages_listed'])->toBe(12)
        ->and($audit->insights['health_score'])->toBe(75)
        ->and($audit->insights['projection'])->toBe(['six_month_low' => 100])
        ->and($audit->email)->toBe('owner@example.com')
        ->and($audit->status)->toBe(WebsiteAudit::STATUS_COMPLETED);
    Queue::assertPushed(CheckMarketingAuditAiVisibility::class, 1);

    $visibility = Mockery::mock(MarketingAuditAiVisibility::class);
    $visibility->shouldReceive('available')->once()->andReturn(true);
    $visibility->shouldReceive('check')->twice()->andReturn(['status' => 'completed', 'website_mentioned' => false, 'website_cited' => false]);
    $aiJob = new CheckMarketingAuditAiVisibility($audit);
    $aiJob->handle($visibility);
    $aiJob->handle($visibility);
    expect(data_get($audit->refresh()->insights, 'ai_visibility.status'))->toBe('completed');
});

it('does not spend on an old queued AI check before the full report is requested', function (): void {
    $audit = WebsiteAudit::factory()->create([
        'status' => WebsiteAudit::STATUS_COMPLETED,
        'insights' => ['ai_visibility' => ['status' => 'pending', 'questions' => ['Which businesses would you recommend for garden offices?']]],
    ]);
    $visibility = Mockery::mock(MarketingAuditAiVisibility::class);
    $visibility->shouldNotReceive('available');
    $visibility->shouldNotReceive('check');
    (new CheckMarketingAuditAiVisibility($audit))->handle($visibility);
    expect(data_get($audit->refresh()->insights, 'ai_visibility.status'))->toBe('pending');
});

it('preserves the public audit if private research fails and allows an explicit retry', function (): void {
    Queue::fake();
    $audit = WebsiteAudit::factory()->create([
        'status' => WebsiteAudit::STATUS_COMPLETED,
        'created_at' => now()->subMinute(),
        'insights' => ['health_score' => 75, 'full_report' => ['status' => 'queued']],
    ]);
    $research = Mockery::mock(MarketingAuditResearch::class);
    $research->shouldReceive('forFullReport')->once()->andThrow(new RuntimeException('Provider unavailable'));
    $job = new GenerateWebsiteAuditFullReport($audit);
    expect(fn () => $job->handle($research))->toThrow(RuntimeException::class);
    $job->failed(new RuntimeException('Provider unavailable'));
    expect(data_get($audit->refresh()->insights, 'full_report.status'))->toBe('failed')
        ->and($audit->status)->toBe(WebsiteAudit::STATUS_COMPLETED)
        ->and($audit->insights['health_score'])->toBe(75);
    $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));
    $this->get(route('admin.onboarding.audits.show', $audit))->assertSuccessful()->assertSee('Retry full research');
    $this->post(route('admin.onboarding.audits.generate', $audit))->assertRedirect();
    Queue::assertPushed(GenerateWebsiteAuditFullReport::class, 1);
});

it('reuses legacy full reports without running their research again', function (): void {
    Queue::fake();
    $audit = WebsiteAudit::factory()->create([
        'status' => WebsiteAudit::STATUS_COMPLETED,
        'created_at' => now()->subMinute(),
        'insights' => ['pages_listed' => 12, 'competitors' => [], 'ai_visibility' => ['status' => 'completed', 'results' => []]],
    ]);
    $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]))
        ->post(route('admin.onboarding.audits.generate', $audit))->assertRedirect();
    Queue::assertNothingPushed();
    expect($audit->refresh()->insights['pages_listed'])->toBe(12);
});

it('shows the shared report header and grades with only the floating booking action', function (): void {
    Http::preventStrayRequests();
    Queue::fake();
    $audit = WebsiteAudit::factory()->create([
        'status' => WebsiteAudit::STATUS_COMPLETED,
        'created_at' => now()->subMinute(),
        'findings' => [['category' => 'Search essentials', 'severity' => 'warning', 'title' => 'Missing page title', 'message' => 'Add a clear title.']],
        'insights' => ['health_score' => 75, 'pages_listed' => 12],
    ]);
    $response = $this->get(URL::temporarySignedRoute('marketing.website-audits.full', now()->addHour(), $audit))
        ->assertSuccessful()
        ->assertSee('Your full website audit.')
        ->assertSee('Missing page title')
        ->assertSee('Add a clear title.')
        ->assertDontSee('You don’t have to fix this yourself.')
        ->assertDontSee('Prefer to talk it through with Ross?')
        ->assertDontSee('Want us to take care of this?')
        ->assertDontSee('data-audit-email-gate', false)
        ->assertDontSee('data-audit-report-preview', false);
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    expect($xpath->query('//*[@data-audit-report-surface]//*[@data-audit-category-score]')->length)->toBe(5)
        ->and($xpath->query('//*[@data-audit-book-call]')->length)->toBe(1)
        ->and($xpath->query('//*[@data-audit-actions]//*[@data-audit-book-call]')->length)->toBe(1)
        ->and($xpath->query('//header//*[@data-audit-health-score]')->item(0)->textContent)->toBe('75%');
    Http::assertNothingSent();
    Queue::assertNothingPushed();
});
