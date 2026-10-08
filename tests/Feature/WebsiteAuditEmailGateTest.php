<?php

use App\Jobs\GenerateWebsiteAuditFullReport;
use App\Mail\WebsiteAuditReport;
use App\Models\WebsiteAudit;
use App\Services\MarketingAuditScreenshot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

beforeEach(function (): void {
    Http::preventStrayRequests();
    Mail::fake();
    Queue::fake();
});

it('unlocks the complete saved report only for the capturing browser and the emailed signed link', function (): void {
    $audit = WebsiteAudit::factory()->create([
        'status' => WebsiteAudit::STATUS_COMPLETED, 'created_at' => now()->subMinute(),
        'findings' => [['severity' => 'warning', 'title' => 'Private technical finding', 'message' => 'Private technical explanation']],
        'insights' => [
            'health_score' => 60, 'pages_listed' => 24, 'full_report' => ['status' => 'completed'],
            'seo' => ['location_code' => 2826, 'organic_keywords' => 1, 'top_3_keywords' => 1, 'top_10_keywords' => 1, 'referring_domains' => 2, 'estimated_monthly_visits' => 10, 'keywords' => [['term' => 'private keyword', 'position' => 3, 'monthly_searches' => 400]]],
            'competitors' => ['domain' => 'private-rival.example', 'shared_terms' => 1],
            'ai_visibility' => ['status' => 'completed', 'questions' => ['Private AI question'], 'results' => [['status' => 'completed', 'website_cited' => true, 'checked_at' => now()->toIso8601String()]]],
        ],
    ]);
    $publicUrl = route('marketing.website-audits.show', $audit);
    $this->get($publicUrl)->assertSuccessful()->assertSee('60%')->assertSee('View my report')
        ->assertDontSee('private keyword')->assertDontSee('private-rival.example')
        ->assertDontSee('Private AI question')->assertDontSee('Private technical explanation');

    $this->post(route('marketing.website-audits.email-report', $audit), ['email' => ' OWNER@EXAMPLE.COM '])
        ->assertRedirect($publicUrl.'#audit-numbers-title')
        ->assertSessionHas('marketing.website_audit_review_ids.'.$audit->public_id, true);
    expect($audit->refresh()->email)->toBe('owner@example.com')
        ->and($audit->marketing_consent_at)->toBeNull()
        ->and($audit->personal_review_requested_at)->toBeNull();
    $this->get($publicUrl)->assertSuccessful()->assertSee('private keyword')->assertSee('private-rival.example')
        ->assertSee('Private AI question')->assertSee('Private technical explanation')
        ->assertSee('data-audit-book-call', false)->assertSee('data-audit-engagement-url', false)
        ->assertDontSee('data-audit-email-gate', false);
    Queue::assertNotPushed(GenerateWebsiteAuditFullReport::class);

    $reportUrl = (new WebsiteAuditReport($audit))->content()->with['reportUrl'];
    expect(URL::hasValidSignature(Request::create($reportUrl)))->toBeTrue();
    Mail::assertQueued(WebsiteAuditReport::class, fn (WebsiteAuditReport $mail): bool => $mail->hasTo('owner@example.com'));

    session()->flush();
    $this->get($publicUrl.'?full=1')->assertSuccessful()->assertSee('View my report')->assertDontSee('private keyword');
    $this->get($reportUrl)->assertSuccessful()->assertSee('private keyword')->assertSee('Private technical explanation');
    $this->get($reportUrl.'&extra=1')->assertForbidden();
    $this->get(route('marketing.website-audits.full', $audit))->assertForbidden();
    $this->travel(15)->days();
    $this->get($reportUrl)->assertForbidden();
    $this->get($publicUrl)->assertNotFound();
    Http::assertNothingSent();
});

it('keeps locked polling private and permits signed full-report progress without re-running research', function (): void {
    $audit = WebsiteAudit::factory()->create([
        'status' => WebsiteAudit::STATUS_COMPLETED, 'created_at' => now()->subMinute(),
        'insights' => ['full_report' => ['status' => 'running'], 'ai_visibility' => ['status' => 'pending']],
    ]);
    $statusUrl = route('marketing.website-audits.status', $audit);
    $this->getJson($statusUrl)->assertSuccessful()->assertJsonPath('full_report_status', null)->assertJsonPath('ai_visibility_status', null);
    $signedUrl = URL::temporarySignedRoute('marketing.website-audits.status', now()->addMinutes(30), $audit);
    $this->getJson($signedUrl)->assertSuccessful()->assertJsonPath('full_report_status', 'running')->assertJsonPath('ai_visibility_status', 'pending');
    $this->getJson($signedUrl.'&tampered=1')->assertSuccessful()->assertJsonPath('full_report_status', null);
    $this->withSession(['marketing.website_audit_review_ids.'.$audit->public_id => true])
        ->getJson($statusUrl)->assertSuccessful()->assertJsonPath('full_report_status', 'running');
    Queue::assertNothingPushed();
    Http::assertNothingSent();
});

it('keeps failed email submissions locked with accessible inline recovery', function (): void {
    $audit = WebsiteAudit::factory()->create(['status' => WebsiteAudit::STATUS_COMPLETED, 'created_at' => now()->subMinute()]);
    $publicUrl = route('marketing.website-audits.show', $audit);
    $this->from($publicUrl)->post(route('marketing.website-audits.email-report', $audit), ['email' => 'invalid'])
        ->assertRedirect($publicUrl)->assertSessionHasErrors('email')
        ->assertSessionMissing('marketing.website_audit_review_ids.'.$audit->public_id);
    expect(session('errors')->getBag('default')->has('email'))->toBeTrue();
    $this->view('marketing.audit-email-gate', [
        'audit' => $audit, 'healthScore' => null, 'fixCount' => 0, 'findings' => collect(), 'errors' => session('errors'),
    ])->assertSee('aria-invalid="true"', false)
        ->assertSee('id="audit-email-error"', false)->assertSee('View my report');
    expect($audit->refresh()->report_requested_at)->toBeNull();
    Mail::assertNothingOutgoing();
    Queue::assertNothingPushed();
});

it('does not unlock a different report or a new browser by repeating a stored email', function (): void {
    $audit = WebsiteAudit::factory()->create(['status' => WebsiteAudit::STATUS_COMPLETED, 'created_at' => now()->subMinute(), 'email' => 'owner@example.com', 'report_requested_at' => now()]);
    $other = WebsiteAudit::factory()->create(['status' => WebsiteAudit::STATUS_COMPLETED, 'created_at' => now()->subMinute()]);
    $this->withSession(['marketing.website_audit_review_ids.'.$other->public_id => true])
        ->get(route('marketing.website-audits.show', $audit))->assertViewHas('showDetails', false);
    $this->post(route('marketing.website-audits.email-report', $audit), ['email' => 'owner@example.com'])
        ->assertRedirect()->assertSessionMissing('marketing.website_audit_review_ids.'.$audit->public_id);
    $this->get(route('marketing.website-audits.show', $audit))->assertSuccessful()
        ->assertSee('Open the full-report link in your email')->assertSee('View my report');
    Mail::assertNothingOutgoing();
    Queue::assertNothingPushed();
});

it('gives the preview stats an honest status and a measured health meter', function (?int $score, ?string $severity, string $healthStatus, string $fixStatus): void {
    $audit = WebsiteAudit::factory()->create([
        'status' => WebsiteAudit::STATUS_COMPLETED, 'created_at' => now()->subMinute(),
        'insights' => ['health_score' => $score],
        'findings' => $severity === null ? [] : [['severity' => $severity]],
    ]);
    $response = $this->get(route('marketing.website-audits.show', $audit))->assertSuccessful()
        ->assertSee($healthStatus)->assertSee($fixStatus)->assertSee('View my report');
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
    $xpath = new DOMXPath($document);
    $meter = $xpath->query('//*[@data-audit-health-meter]');
    expect($xpath->query('//*[@data-audit-health-score]')->item(0)->textContent)->toBe($score === null ? '—' : $score.'%')
        ->and($xpath->query('//*[@data-audit-fix-count]')->item(0)->textContent)->toBe($severity === null ? '—' : ($severity === 'passed' ? '0' : '1'));
    if ($score === null) {
        expect($meter)->toHaveCount(0);
    } else {
        expect($meter)->toHaveCount(1)
            ->and($meter->item(0)->getAttribute('aria-valuenow'))->toBe((string) $score)
            ->and($meter->item(0)->getAttribute('aria-label'))->toBe('Homepage checks passed');
    }
    Queue::assertNothingPushed();
})->with([
    'attention needed' => [40, 'failed', 'Needs attention', 'Issues found'],
    'some checks to fix' => [75, 'warning', 'Needs work', 'Issues found'],
    'checks passed' => [100, 'passed', 'Looking healthy', 'None flagged'],
    'checks unavailable' => [null, null, 'Not available', 'Not available'],
]);

it('keeps saved search findings server gated behind the blurred report preview', function (): void {
    $audit = WebsiteAudit::factory()->create([
        'domain' => 'example.com',
        'status' => WebsiteAudit::STATUS_COMPLETED, 'created_at' => now()->subMinute(),
        'insights' => ['opportunity' => ['projection' => ['six_month_low' => 10, 'six_month_high' => 20, 'pages' => [['keywords' => [['term' => 'bespoke business software']]]]]], 'full_report' => ['status' => 'deferred'], 'seo' => [
            'retrieved_at' => now()->subDay()->toIso8601String(), 'estimated_monthly_visits' => 0,
            'keywords' => [
                ['term' => 'bespoke business software', 'position' => 14, 'monthly_searches' => 200],
                ['term' => 'secondary private search', 'position' => 22, 'monthly_searches' => 50],
                ['term' => 'unrelated popular search', 'position' => 15, 'monthly_searches' => 100000],
                ['term' => 'private page one search', 'position' => 3, 'monthly_searches' => 1000],
            ],
        ]],
    ]);
    $this->get(route('marketing.website-audits.show', $audit))->assertSuccessful()
        ->assertSee('Your website audit is ready.')->assertSee('We’ve found your Google rankings')
        ->assertDontSee('bespoke business software')->assertDontSee('#14')
        ->assertSee('data-audit-report-preview', false)
        ->assertDontSee('secondary private search')->assertDontSee('private page one search')->assertDontSee('unrelated popular search')
        ->assertSee('AI visibility')->assertSee('Where should we send it?')->assertSee('View my report');
    $this->get(route('marketing.website-audits.show', $audit))->assertSuccessful();
    Http::assertNothingSent();
    Queue::assertNothingPushed();
});

it('does not invent a search finding when the sample cannot support one', function (string $sample): void {
    $seo = ['estimated_monthly_visits' => 0, 'retrieved_at' => now()->toIso8601String(), 'keywords' => [['term' => 'hidden search', 'position' => 14, 'monthly_searches' => 200]]];
    match ($sample) {
        'missing' => $seo = null,
        'empty' => $seo['keywords'] = [],
        'stale' => $seo['retrieved_at'] = now()->subDays(31)->toIso8601String(),
        'undated' => $seo['retrieved_at'] = null,
        'invalid date' => $seo['retrieved_at'] = 'invalid',
        'page one' => $seo['keywords'][0]['position'] = 3,
        'no demand' => $seo['keywords'][0]['monthly_searches'] = 0,
        'invalid position' => $seo['keywords'][0]['position'] = 'unknown',
        'unsupported term' => null,
    };
    $audit = WebsiteAudit::factory()->create([
        'status' => WebsiteAudit::STATUS_COMPLETED, 'created_at' => now()->subMinute(),
        'insights' => ['seo' => $seo, 'opportunity' => $sample === 'unsupported term' ? null : ['projection' => ['six_month_low' => 10, 'six_month_high' => 20, 'pages' => [['keywords' => [['term' => 'hidden search']]]]]]],
    ]);
    $this->get(route('marketing.website-audits.show', $audit))->assertSuccessful()
        ->assertDontSee('data-audit-search-preview', false)->assertDontSee('hidden search')
        ->assertSee('Your website audit is ready.')->assertSee('View my report');
    Http::assertNothingSent();
    Queue::assertNothingPushed();
})->with(['missing', 'empty', 'stale', 'undated', 'invalid date', 'page one', 'no demand', 'invalid position', 'unsupported term']);

it('only says the website appears in AI when a successful saved observation confirms it', function (string $status, bool $mentioned, bool $cited, bool $appears): void {
    $audit = WebsiteAudit::factory()->create([
        'status' => WebsiteAudit::STATUS_COMPLETED, 'created_at' => now()->subMinute(),
        'insights' => ['ai_visibility' => ['status' => 'completed', 'results' => [[
            'status' => $status, 'website_mentioned' => $mentioned, 'website_cited' => $cited,
            'question' => 'Private AI prompt',
        ]]]],
    ]);
    $response = $this->get(route('marketing.website-audits.show', $audit))->assertSuccessful()
        ->assertSee('Your website audit is ready.')->assertDontSee('Private AI prompt')->assertSee('View my report');
    if ($appears) {
        $response->assertSee('Your site appears in sampled AI answers.');
    } else {
        $response->assertDontSee('Your site appears in sampled AI answers.');
    }
    Http::assertNothingSent();
    Queue::assertNothingPushed();
})->with([
    'observed mention' => ['completed', true, false, true],
    'observed citation' => ['completed', false, true, true],
    'not observed' => ['completed', false, false, false],
    'failed observation' => ['unavailable', true, true, false],
]);

it('shows a measured Google ranking count without treating missing data as zero', function (?int $count): void {
    $audit = WebsiteAudit::factory()->create([
        'status' => WebsiteAudit::STATUS_COMPLETED, 'created_at' => now()->subMinute(),
        'insights' => ['seo' => $count === null ? null : ['organic_keywords' => $count, 'estimated_monthly_visits' => 0, 'keywords' => []]],
    ]);
    $response = $this->get(route('marketing.website-audits.show', $audit))->assertSuccessful();
    if ($count === null) {
        $response->assertDontSee('data-audit-ranking-count', false);
    } else {
        $document = new DOMDocument;
        @$document->loadHTML($response->getContent());
        $value = (new DOMXPath($document))->query('//*[@data-audit-ranking-count]')->item(0)->textContent;
        expect($value)->toBe('~'.number_format($count));
        $response->assertDontSee('>Estimated<', false);
    }
    Http::assertNothingSent();
    Queue::assertNothingPushed();
})->with([null, 0, 1234]);

it('places the domain in the caption beneath each responsive screenshot', function (): void {
    Storage::fake('local');
    $audit = WebsiteAudit::factory()->create(['status' => WebsiteAudit::STATUS_COMPLETED, 'created_at' => now()->subMinute()]);
    $path = app(MarketingAuditScreenshot::class)->pathFor($audit);
    Storage::disk('local')->put($path, 'preview');
    $response = $this->get(route('marketing.website-audits.show', $audit))->assertSuccessful();
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $captions = (new DOMXPath($document))->query('//header//figure[div/img]/figcaption');
    expect($captions)->toHaveCount(2);
    foreach ($captions as $caption) {
        expect($caption->textContent)->toBe($audit->domain)
            ->and($caption->getAttribute('class'))->toContain('text-garden');
    }
});

it('grades separate homepage categories using their own saved checks', function (): void {
    $audit = WebsiteAudit::factory()->create([
        'status' => WebsiteAudit::STATUS_COMPLETED, 'created_at' => now()->subMinute(),
        'findings' => [
            ['category' => 'Search essentials', 'severity' => 'passed'],
            ['category' => 'Structured data', 'severity' => 'warning'],
            ['category' => 'Accessibility', 'severity' => 'passed'],
            ['category' => 'Security', 'severity' => 'failed'],
            ['category' => 'Availability & speed', 'key' => 'website_reachable', 'severity' => 'passed'],
            ['category' => 'Availability & speed', 'key' => 'response_time', 'severity' => 'warning'],
        ],
    ]);
    $response = $this->get(route('marketing.website-audits.show', $audit))->assertSuccessful();
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $scores = [];
    foreach ((new DOMXPath($document))->query('//*[@data-audit-category-score]') as $category) {
        $scores[$category->getAttribute('data-category')] = $category->getAttribute('data-score');
    }
    expect($scores)->toBe(['On-page SEO' => '50', 'Crawl access' => '', 'Usability' => '100', 'Security' => '0', 'Response time' => '0']);
    $xpath = new DOMXPath($document);
    expect($xpath->query('//*[@data-audit-report-surface]/*[1]//*[@data-audit-category-score]')->length)->toBe(5)
        ->and($xpath->query('//*[@data-audit-report-preview]//*[@data-audit-category-score]')->length)->toBe(0)
        ->and($xpath->query('//*[@data-audit-report-surface]/*[2][@data-audit-email-gate]//*[@data-audit-report-preview]')->length)->toBe(1);
    $response->assertSee('Not checked')->assertDontSee('Grades reflect the homepage checks run');
    Http::assertNothingSent();
    Queue::assertNothingPushed();
});

it('shows saved sitemap page counts with an honest partial or unavailable state', function (?int $count, bool $partial, string $display): void {
    $audit = WebsiteAudit::factory()->create([
        'status' => WebsiteAudit::STATUS_COMPLETED, 'created_at' => now()->subMinute(),
        'insights' => ['pages_listed' => $count, 'pages_matching_domain' => $count, 'pages_partial' => $partial],
    ]);
    $response = $this->get(route('marketing.website-audits.show', $audit))->assertSuccessful();
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    expect((new DOMXPath($document))->query('//*[@data-audit-page-count]')->item(0)->textContent)->toBe($display);
    Http::assertNothingSent();
    Queue::assertNothingPushed();
})->with([[18, false, '18'], [18, true, '18+'], [null, false, '—']]);
