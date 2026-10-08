<?php

use App\Http\Requests\StoreFreeSiteAuditRequest;
use App\Jobs\GenerateFreeSiteAudit;
use App\Jobs\GenerateWebsiteAudit;
use App\Jobs\GenerateWebsiteAuditFullReport;
use App\Mail\FreeSiteAuditResults;
use App\Mail\WebsiteAuditLeadReceived;
use App\Mail\WebsiteAuditPersonalReview;
use App\Mail\WebsiteAuditReport;
use App\Models\Prospect;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteAudit;
use App\Models\WebsiteDomain;
use App\Notifications\WebsiteAuditClaim;
use App\Services\MarketingAuditResearch;
use App\Services\MarketingAuditScreenshot;
use App\Services\ProspectWebsiteAnalyzer;
use App\Support\MarketingJourney;
use App\Support\MembershipPlan;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

it('starts an anonymous website audit with only a website address', function (): void {
    Queue::fake();

    $response = $this->from(route('marketing.free-site-audit'))
        ->post(route('marketing.free-site-audit.store'), [
            'website_url' => 'northfield.example',
            '_sitewell_check' => '',
        ]);

    $audit = WebsiteAudit::query()->sole();
    $response->assertRedirect(route('marketing.website-audits.show', $audit));

    expect($audit->website_url)->toBe('https://northfield.example')
        ->and($audit->domain)->toBe('northfield.example')
        ->and($audit->status)->toBe(WebsiteAudit::STATUS_PENDING)
        ->and($audit->expires_at->isFuture())->toBeTrue()
        ->and(Prospect::query()->exists())->toBeFalse();

    Queue::assertPushed(GenerateWebsiteAudit::class, fn (GenerateWebsiteAudit $job): bool => $job->audit->is($audit));
});

it('rejects invalid or automated free audit requests', function (): void {
    Queue::fake();
    $this->post(route('marketing.free-site-audit.store'), [
        'website_url' => 'not a website',
        '_sitewell_check' => 'bot content',
    ])->assertSessionHasErrors(['website_url', '_sitewell_check']);

    expect(WebsiteAudit::query()->exists())->toBeFalse();
    Queue::assertNothingPushed();
});

it('shows audit progress and exposes only its processing state', function (): void {
    $audit = WebsiteAudit::factory()->create();

    $this->get(route('marketing.website-audits.show', $audit))
        ->assertSuccessful()
        ->assertSee('<meta name="robots" content="noindex">', false)
        ->assertDontSee('<link rel="canonical"', false)
        ->assertSee('Checking your website.')
        ->assertSee('Your results will appear here automatically.')
        ->assertDontSee('data-audit-actions', false)
        ->assertDontSee('data-audit-call-section', false)
        ->assertDontSee('data-audit-email-open', false)
        ->assertSee(route('marketing.website-audits.status', $audit));

    $this->getJson(route('marketing.website-audits.status', $audit))
        ->assertSuccessful()
        ->assertExactJson(['status' => 'pending', 'completed' => false, 'failed' => false, 'ai_visibility_status' => null, 'full_report_status' => null]);
});

it('contains long website addresses in the audit heading while retaining the full address', function (): void {
    $domain = str_repeat('long-subdomain-', 12).'example.com';
    $audit = WebsiteAudit::factory()->create(['domain' => $domain, 'website_url' => 'https://'.$domain]);

    $response = $this->get(route('marketing.website-audits.show', $audit))->assertSuccessful();
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
    $heading = (new DOMXPath($document))->query('//*[@id="audit-title"]')->item(0);

    expect($heading->textContent)->toBe($domain)
        ->and($heading->getAttribute('title'))->toBe($domain)
        ->and(explode(' ', $heading->getAttribute('class')))->toContain('truncate', 'min-w-0');
});

it('stores an anonymous audit result for the live report', function (): void {
    $audit = WebsiteAudit::factory()->create([
        'website_url' => 'https://northfield.example',
        'created_at' => now()->subSeconds(11),
    ]);
    $analyzer = Mockery::mock(ProspectWebsiteAnalyzer::class);
    $analyzer->shouldReceive('analyze')->once()->with($audit->website_url)->andReturn([
        'score' => 35,
        'findings' => [['category' => 'Security', 'key' => 'https', 'title' => 'HTTPS enabled', 'severity' => 'warning', 'message' => 'HTTPS should be reviewed.']],
        'contacts' => ['emails' => [], 'phones' => [], 'contact_page_url' => null, 'contact_form_url' => null],
    ]);

    $research = Mockery::mock(MarketingAuditResearch::class);
    $research->shouldReceive('forAudit')->once()->with($audit->domain, $audit->website_url, Mockery::type('array'))->andReturn([
        'health_score' => 0,
        'pages_listed' => 7,
        'pages_partial' => false,
        'seo' => null,
        'projection' => null,
    ]);

    $screenshot = Mockery::mock(MarketingAuditScreenshot::class);
    $screenshot->shouldReceive('capture')->once()->with($audit);

    (new GenerateWebsiteAudit($audit))->handle($analyzer, new MarketingJourney, $research, $screenshot);

    expect($audit->refresh()->status)->toBe(WebsiteAudit::STATUS_COMPLETED)
        ->and($audit->opportunity_score)->toBe(35)
        ->and($audit->insights['pages_listed'])->toBe(7)
        ->and($audit->completed_at)->not->toBeNull();

    $response = $this->get(route('marketing.website-audits.show', $audit))
        ->assertSuccessful()
        ->assertSee('Your website audit is ready.')
        ->assertDontSee('data-audit-next-step', false)
        ->assertSee('Website health')
        ->assertSee('Website fixes')
        ->assertSee('0%')
        ->assertDontSee('URLs in sitemap')
        ->assertSee('View my report')
        ->assertDontSee('The improvements we could take care of.')
        ->assertDontSee('data-audit-book-call', false)
        ->assertSee('data-audit-email-gate', false)
        ->assertDontSee('HTTPS should be reviewed.')
        ->assertDontSee('Start preparing my fixes')
        ->assertSee('name="email"', false)
        ->assertDontSee('name="password"', false);

    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
    expect((new DOMXPath($document))->query('//*[@data-audit-fix-count]')->item(0)->textContent)->toBe('1');
});

it('shows a private website preview beside the report title and expires it with the audit', function (): void {
    Storage::fake('local');
    $audit = WebsiteAudit::factory()->create([
        'status' => WebsiteAudit::STATUS_COMPLETED,
        'created_at' => now()->subSeconds(11),
    ]);
    $path = app(MarketingAuditScreenshot::class)->pathFor($audit);
    Storage::disk('local')->put($path, "\xFF\xD8\xFFpreview");

    $previewUrl = route('marketing.website-audits.preview', $audit);
    $this->get(route('marketing.website-audits.show', $audit))
        ->assertSuccessful()
        ->assertSee('src="'.$previewUrl.'"', false);
    $this->get($previewUrl)
        ->assertSuccessful()
        ->assertHeader('content-type', 'image/jpeg');

    $audit->update(['expires_at' => now()->subMinute()]);
    $this->get($previewUrl)->assertNotFound();
});

it('refuses to capture website previews from private or credential-bearing URLs', function (): void {
    Storage::fake('local');

    foreach (['http://127.0.0.1', 'http://localhost', 'https://user:pass@example.com'] as $url) {
        $audit = WebsiteAudit::factory()->create(['website_url' => $url]);
        $screenshot = app(MarketingAuditScreenshot::class);
        $screenshot->capture($audit);

        Storage::disk('local')->assertMissing($screenshot->pathFor($audit));
    }
});

it('offers an email copy after the results and extends the requested report to fourteen days', function (): void {
    Mail::fake();
    $audit = WebsiteAudit::factory()->create([
        'status' => WebsiteAudit::STATUS_COMPLETED,
        'created_at' => now()->subSeconds(11),
        'expires_at' => now()->addDay(),
    ]);

    $this->get(route('marketing.website-audits.show', $audit))
        ->assertSuccessful()
        ->assertSee('View my report')
        ->assertSee('data-audit-email-gate', false)
        ->assertDontSee('data-audit-review-prompt', false)
        ->assertDontSee('data-audit-book-call', false)
        ->assertSee('name="email"', false);

    $this->post(route('marketing.website-audits.email-report', $audit), [
        'email' => ' ALEX@EXAMPLE.COM ',
        '_sitewell_check' => '',
    ])->assertRedirect()->assertSessionHas('report_email_status');

    $audit->refresh();
    expect($audit->email)->toBe('alex@example.com')
        ->and($audit->report_requested_at)->not->toBeNull()
        ->and($audit->expires_at->between(now()->addDays(13), now()->addDays(14)->addMinute()))->toBeTrue()
        ->and($audit->user_id)->toBeNull();

    Mail::assertQueued(WebsiteAuditReport::class, fn (WebsiteAuditReport $mail): bool => $mail->hasTo('alex@example.com'));
    Mail::assertQueued(WebsiteAuditLeadReceived::class, fn (WebsiteAuditLeadReceived $mail): bool => $mail->hasTo(config('marketing.audit_notification_email')));

    (new WebsiteAuditReport($audit))
        ->assertSeeInHtml('View your full report')
        ->assertSeeInHtml('Prefer to talk it through?')
        ->assertSeeInHtml('Book a call with me')
        ->assertSeeInHtml(route('marketing.website-audits.full', $audit))
        ->assertSeeInHtml('signature=')
        ->assertSeeInHtml(route('marketing.ppc.book'));
    (new WebsiteAuditLeadReceived($audit))
        ->assertSeeInHtml('alex@example.com')
        ->assertSeeInHtml(route('admin.onboarding.index', ['search' => $audit->domain]));

    $this->get(route('marketing.website-audits.show', $audit))
        ->assertSuccessful()
        ->assertSee('Your full report is unlocked. We’ve emailed you a link to return to it.')
        ->assertSee('data-audit-book-call', false)
        ->assertDontSee('data-audit-email-open', false)
        ->assertDontSee('See how we’d get you there.');
});

it('rejects invalid report email requests and requests before completion', function (): void {
    Mail::fake();
    $audit = WebsiteAudit::factory()->create([
        'status' => WebsiteAudit::STATUS_COMPLETED,
        'created_at' => now()->subSeconds(11),
    ]);
    $url = route('marketing.website-audits.email-report', $audit);

    $this->post($url, ['email' => 'not-an-email'])->assertSessionHasErrors('email');
    $this->post($url, ['email' => 'alex@example.com', '_sitewell_check' => 'filled'])->assertSessionHasErrors('_sitewell_check');
    expect($audit->refresh()->report_requested_at)->toBeNull();
    Mail::assertNothingOutgoing();

    $pendingAudit = WebsiteAudit::factory()->create();
    $this->post(route('marketing.website-audits.email-report', $pendingAudit), ['email' => 'alex@example.com'])->assertForbidden();
});

it('queues a report only once for the same audit', function (): void {
    Mail::fake();
    $this->withoutMiddleware(ThrottleRequests::class);
    $audit = WebsiteAudit::factory()->create([
        'status' => WebsiteAudit::STATUS_COMPLETED,
        'created_at' => now()->subSeconds(11),
    ]);
    $url = route('marketing.website-audits.email-report', $audit);

    $this->post($url, ['email' => 'alex@example.com'])->assertRedirect();
    $this->post($url, ['email' => 'alex@example.com'])->assertRedirect();
    $this->post($url, ['email' => 'another@example.com'])->assertSessionHasErrors('email');
    Mail::assertQueuedCount(2);
});

it('shows zero fixes when the initial scan finds no issues', function (): void {
    $audit = WebsiteAudit::factory()->create([
        'status' => WebsiteAudit::STATUS_COMPLETED,
        'findings' => [['category' => 'Search essentials', 'title' => 'Page title', 'severity' => 'passed', 'message' => 'A title was found.']],
        'created_at' => now()->subSeconds(11),
    ]);

    $response = $this->get(URL::temporarySignedRoute('marketing.website-audits.full', now()->addHour(), $audit))
        ->assertSuccessful()
        ->assertSee('Website fixes');

    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
    expect((new DOMXPath($document))->query('//*[@data-audit-fix-count]')->item(0)->textContent)->toBe('0');
    $response->assertSee('None flagged');
});

it('shows measured search estimates and a conditional six-month scenario', function (): void {
    Queue::fake([GenerateWebsiteAuditFullReport::class]);
    Mail::fake();
    $audit = WebsiteAudit::factory()->create([
        'status' => WebsiteAudit::STATUS_COMPLETED,
        'created_at' => now()->subSeconds(11),
        'findings' => [['severity' => 'passed'], ['severity' => 'warning']],
        'insights' => [
            'health_score' => 50,
            'pages_listed' => 18,
            'pages_partial' => false,
            'seo' => [
                'location_code' => 2826,
                'organic_keywords' => 42,
                'top_3_keywords' => 3,
                'top_10_keywords' => 7,
                'estimated_monthly_visits' => 120,
                'referring_domains' => 12,
                'sample_size' => 1,
                'keywords' => [['term' => 'garden office fitters', 'position' => 15, 'monthly_searches' => 1000]],
            ],
            'projection' => [
                'baseline_monthly_visits' => 120,
                'six_month_low' => 128,
                'six_month_high' => 145,
                'method' => 'Illustrative scenario from sampled positions.',
            ],
        ],
    ]);

    $snapshot = $this->get(route('marketing.website-audits.show', $audit))
        ->assertSuccessful()
        ->assertSee('Website health')
        ->assertSee('50%')
        ->assertSee('Website fixes')
        ->assertSee('View my report')
        ->assertDontSee('128–160')
        ->assertDontSee('garden office fitters')
        ->assertDontSee('Google ranking terms')
        ->assertDontSee('Google terms in the top 10')
        ->assertDontSee('Est. monthly organic visits')
        ->assertDontSee('Referring domains')
        ->assertDontSee('Possible in six months')
        ->assertDontSee('data-audit-next-step', false);

    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="UTF-8">'.$snapshot->getContent());
    $xpath = new DOMXPath($document);
    expect($xpath->query('//*[@data-audit-health-score]')->item(0)->textContent)->toBe('50%')
        ->and($xpath->query('//*[@data-audit-fix-count]')->item(0)->textContent)->toBe('1');

    $this->post(route('marketing.website-audits.email-report', $audit), [
        'email' => 'review@example.com',
        'personal_review' => '1',
    ])->assertRedirect();
    expect($audit->refresh()->personal_review_requested_at)->not->toBeNull();

    $this->get(route('marketing.website-audits.show', $audit))->assertSuccessful()
        ->assertSee('128–160')->assertSee('50%')->assertSee('garden office fitters')
        ->assertSee('Google rankings')->assertSee('Est. monthly organic visits')
        ->assertDontSee('data-audit-email-open', false)->assertSee('Talk to Ross')->assertDontSee('Prefer to talk it through with Ross?');

    $response = $this->get(URL::temporarySignedRoute('marketing.website-audits.full', now()->addHour(), $audit))
        ->assertSuccessful()
        ->assertSee('50%')
        ->assertSee('Pages found')
        ->assertSee('Google rankings')
        ->assertSee('Referring domains')
        ->assertDontSee('DataForSEO')
        ->assertSee('garden office fitters')
        ->assertSee('1,000')
        ->assertSee('Needs work')
        ->assertSee('Terms in the top 10')
        ->assertSee('Today')
        ->assertSee('Possible in six months')
        ->assertSee('128–160')
        ->assertDontSee('128–145')
        ->assertSee('not a guarantee.');

    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
    $arrows = (new DOMXPath($document))->query('//section[@aria-labelledby="audit-projection-title"]//svg');

    expect($arrows)->toHaveCount(2)
        ->and($arrows->item(0)->getAttribute('class'))->toBe('size-6 sm:hidden')
        ->and($arrows->item(0)->getElementsByTagName('path')->item(0)->getAttribute('d'))->toBe('M19.5 13.5 12 21m0 0-7.5-7.5M12 21V3')
        ->and($arrows->item(1)->getAttribute('class'))->toBe('hidden size-6 sm:block')
        ->and($arrows->item(1)->getElementsByTagName('path')->item(0)->getAttribute('d'))->toBe('M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3');
});

it('keeps missing audit data distinct from healthy checks without inventing traffic', function (bool $healthy): void {
    $audit = WebsiteAudit::factory()->create([
        'status' => WebsiteAudit::STATUS_COMPLETED, 'created_at' => now()->subMinute(),
        'findings' => $healthy ? [['severity' => 'passed']] : [], 'insights' => ['seo' => null],
    ]);
    $response = $this->get(route('marketing.website-audits.show', $audit))->assertSuccessful()
        ->assertSee('View my report')->assertDontSee('Monthly visitors from Google');
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
    $xpath = new DOMXPath($document);
    expect($xpath->query('//*[@data-audit-health-score]')->item(0)->textContent)->toBe($healthy ? '100%' : '—')
        ->and($xpath->query('//*[@data-audit-fix-count]')->item(0)->textContent)->toBe($healthy ? '0' : '—');
})->with(['healthy checks' => true, 'no checks available' => false]);

it('does not substitute a traffic promise when the ranking sample cannot support an opportunity range', function (): void {
    $audit = WebsiteAudit::factory()->create([
        'status' => WebsiteAudit::STATUS_COMPLETED,
        'created_at' => now()->subMinute(),
        'findings' => [['severity' => 'passed'], ['severity' => 'failed']],
        'insights' => ['seo' => [
            'estimated_monthly_visits' => 7654,
            'keywords' => [['term' => 'private page one term', 'position' => 3, 'monthly_searches' => 10000]],
        ]],
    ]);

    $this->get(route('marketing.website-audits.show', $audit))->assertSuccessful()
        ->assertSee('View my report')
        ->assertSee('50%')->assertSee('Website fixes')
        ->assertDontSee('7,654')->assertDontSee('private page one term')
        ->assertDontSee('Monthly visitors from Google');
});

it('shows page-one rankings beside striking-distance rankings and falls back to other terms', function (): void {
    $audit = WebsiteAudit::factory()->create([
        'status' => WebsiteAudit::STATUS_COMPLETED,
        'report_requested_at' => now(),
        'created_at' => now()->subSeconds(11),
        'insights' => ['seo' => [
            'location_code' => 2826,
            'organic_keywords' => 3,
            'top_3_keywords' => 1,
            'top_10_keywords' => 1,
            'top_20_keywords' => 2,
            'referring_domains' => 0,
            'estimated_monthly_visits' => 10,
            'sample_size' => 3,
            'keywords' => [
                ['term' => 'distant term', 'position' => 65, 'monthly_searches' => 90000],
                ['term' => 'page one term', 'position' => 2, 'monthly_searches' => 100],
                ['term' => 'striking term', 'position' => 15, 'monthly_searches' => 300],
            ],
        ]],
    ]);

    $response = $this->get(URL::temporarySignedRoute('marketing.website-audits.full', now()->addHour(), $audit))
        ->assertSuccessful()
        ->assertSee('Page one rankings')
        ->assertSee('Within striking distance')
        ->assertDontSee('Rankings found');

    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="UTF-8"'.$response->getContent());
    $tables = (new DOMXPath($document))->query('//*[@id="audit-search-title"]/../following-sibling::div//table');
    expect($tables->length)->toBe(2)
        ->and($tables->item(0)->textContent)->toContain('page one term')
        ->and($tables->item(1)->textContent)->toContain('striking term')
        ->and($response->getContent())->not->toContain('distant term');

    $audit->update(['insights' => ['seo' => [
        'location_code' => 2826,
        'organic_keywords' => 1,
        'top_3_keywords' => 0,
        'top_10_keywords' => 0,
        'top_20_keywords' => 0,
        'referring_domains' => 0,
        'estimated_monthly_visits' => 0,
        'sample_size' => 1,
        'keywords' => [['term' => 'distant term', 'position' => 65, 'monthly_searches' => 90000]],
    ]]]);

    $this->get(URL::temporarySignedRoute('marketing.website-audits.full', now()->addHour(), $audit))
        ->assertSuccessful()
        ->assertSee('Rankings found')
        ->assertSee('distant term')
        ->assertDontSee('Within striking distance');
});

it('explains when sitemap URLs point at a different domain', function (): void {
    $audit = WebsiteAudit::factory()->create([
        'domain' => 'vvipeventszante.com',
        'status' => WebsiteAudit::STATUS_COMPLETED,
        'report_requested_at' => now(),
        'created_at' => now()->subSeconds(11),
        'insights' => [
            'pages_listed' => 81,
            'pages_partial' => false,
            'pages_mismatched_domain' => 81,
            'pages_mismatched_host' => 'vvipeventszante.test',
            'seo' => null,
        ],
    ]);

    $this->get(URL::temporarySignedRoute('marketing.website-audits.full', now()->addHour(), $audit))
        ->assertSuccessful()
        ->assertSee('Pages found')
        ->assertSee('Sitemap issue: 81 URLs point to vvipeventszante.test instead of vvipeventszante.com.')
        ->assertDontSee('0+');
});

it('marks poor technical health and missing page-one visibility as needs attention', function (): void {
    $audit = WebsiteAudit::factory()->create([
        'status' => WebsiteAudit::STATUS_COMPLETED,
        'report_requested_at' => now(),
        'created_at' => now()->subSeconds(11),
        'findings' => [['severity' => 'passed'], ['severity' => 'warning'], ['severity' => 'failed'], ['severity' => 'failed']],
        'insights' => [
            'health_score' => 25,
            'pages_listed' => 12,
            'pages_partial' => false,
            'seo' => [
                'location_code' => 2826,
                'organic_keywords' => 4,
                'top_3_keywords' => 0,
                'top_10_keywords' => 0,
                'estimated_monthly_visits' => 2,
                'referring_domains' => 1,
                'sample_size' => 0,
                'keywords' => [],
            ],
        ],
    ]);

    $this->get(URL::temporarySignedRoute('marketing.website-audits.full', now()->addHour(), $audit))
        ->assertSuccessful()
        ->assertSee('Needs attention')
        ->assertSee('Terms in the top 10')
        ->assertSee('There isn’t enough ranking data for a useful estimate yet.');
});

it('keeps the progress experience visible for at least ten seconds', function (): void {
    $audit = WebsiteAudit::factory()->create([
        'status' => WebsiteAudit::STATUS_COMPLETED,
        'findings' => [],
        'completed_at' => now(),
    ]);

    $this->get(route('marketing.website-audits.show', $audit))
        ->assertSuccessful()
        ->assertSee('Checking your website.')
        ->assertSee('Checking your website and preparing your report.');

    $this->getJson(route('marketing.website-audits.status', $audit))
        ->assertExactJson(['status' => 'completed', 'completed' => false, 'failed' => false, 'ai_visibility_status' => null, 'full_report_status' => null]);

    $this->travel(11)->seconds();

    $this->getJson(route('marketing.website-audits.status', $audit))
        ->assertExactJson(['status' => 'completed', 'completed' => true, 'failed' => false, 'ai_visibility_status' => null, 'full_report_status' => null]);
});

it('emails a secure continuation link after the website review', function (): void {
    Notification::fake();
    $audit = WebsiteAudit::factory()->create([
        'status' => WebsiteAudit::STATUS_COMPLETED,
        'completed_at' => now(),
        'created_at' => now()->subSeconds(11),
    ]);

    $this->post(route('marketing.website-audits.claim', $audit), [
        'email' => ' ALEX@EXAMPLE.COM ',
    ])->assertRedirect()->assertSessionHas('claim_status');

    expect($audit->refresh()->email)->toBe('alex@example.com')
        ->and($audit->claim_email_sent_at)->not->toBeNull();

    Notification::assertSentOnDemand(
        WebsiteAuditClaim::class,
        fn (WebsiteAuditClaim $notification, array $channels, object $notifiable): bool => $notifiable->routes['mail'] === 'alex@example.com',
    );
});

it('allows repeated email continuation attempts without an early rate limit', function (): void {
    Notification::fake();
    $audit = WebsiteAudit::factory()->create([
        'status' => WebsiteAudit::STATUS_COMPLETED,
        'completed_at' => now(),
        'created_at' => now()->subSeconds(11),
    ]);

    foreach (range(1, 20) as $attempt) {
        $this->post(route('marketing.website-audits.claim', $audit), [
            'email' => 'alex@example.com',
        ])->assertRedirect()->assertSessionHas('claim_status');
    }

    Notification::assertSentOnDemandTimes(WebsiteAuditClaim::class, 20);
});

it('confirms email and creates the trial profile and website', function (): void {
    $audit = WebsiteAudit::factory()->create([
        'status' => WebsiteAudit::STATUS_COMPLETED,
        'email' => 'alex@example.com',
        'website_url' => 'https://example.test',
        'domain' => 'example.test',
        'completed_at' => now(),
        'created_at' => now()->subSeconds(11),
    ]);
    $url = URL::temporarySignedRoute(
        'marketing.website-audits.onboarding',
        now()->addHour(),
        ['websiteAudit' => $audit],
    );

    $this->get($url)
        ->assertSuccessful()
        ->assertSee('Complete your profile')
        ->assertSee('Start my 14-day Growth trial');

    $this->post($url, [
        'name' => 'Alex Morgan',
        'password' => 'secure-password',
        'password_confirmation' => 'secure-password',
    ])->assertRedirect();

    $audit->refresh();
    $user = $audit->user;

    $this->assertAuthenticatedAs($user);
    expect($user->name)->toBe('Alex Morgan')
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->membership_tier)->toBe(MembershipPlan::GROWTH)
        ->and($user->hasMembershipFeature(MembershipPlan::FEATURE_GROWTH))->toBeTrue()
        ->and($user->membership_status)->toBe('trialing')
        ->and($user->membership_current_period_end->isAfter(now()->addDays(13)))->toBeTrue()
        ->and($user->onboarding_status)->toBe('trial_active')
        ->and($audit->website->health_reports_enabled)->toBeTrue()
        ->and($audit->website->seo_weekly_snapshots_enabled)->toBeFalse()
        ->and($audit->website->domains()->where('domain', 'example.test')->exists())->toBeTrue()
        ->and($audit->website->primaryDomain()?->ownership_status)->toBe(WebsiteDomain::OWNERSHIP_PENDING)
        ->and($audit->claimed_at)->not->toBeNull();
});

it('allows onboarding to continue when another workspace already uses the domain', function (): void {
    $existingWebsite = Website::factory()->create();
    $existingDomain = $existingWebsite->domains()->create([
        'domain' => 'example.test',
        'is_primary' => true,
    ]);
    $audit = WebsiteAudit::factory()->create([
        'status' => WebsiteAudit::STATUS_COMPLETED,
        'email' => 'new-owner@example.com',
        'website_url' => 'https://example.test',
        'domain' => 'example.test',
        'completed_at' => now(),
        'created_at' => now()->subSeconds(11),
    ]);
    $url = URL::temporarySignedRoute(
        'marketing.website-audits.onboarding',
        now()->addHour(),
        ['websiteAudit' => $audit],
    );

    $this->post($url, [
        'name' => 'New Owner',
        'password' => 'secure-password',
        'password_confirmation' => 'secure-password',
    ])->assertRedirect();

    $claimedDomain = $audit->refresh()->website->primaryDomain();

    expect($claimedDomain?->domain)->toBe('example.test')
        ->and($claimedDomain?->ownership_status)->toBe(WebsiteDomain::OWNERSHIP_PENDING)
        ->and($claimedDomain?->verified_domain)->toBeNull()
        ->and($existingDomain->fresh()->ownership_status)->toBe(WebsiteDomain::OWNERSHIP_VERIFIED)
        ->and(WebsiteDomain::query()->where('domain', 'example.test')->count())->toBe(2);
});

it('rejects an invalid marketing Turnstile response', function (): void {
    Queue::fake();
    config([
        'services.turnstile.marketing.enabled' => true,
        'services.turnstile.marketing.site_key' => 'site-key',
        'services.turnstile.marketing.secret_key' => 'secret-key',
        'services.turnstile.marketing.hostname' => 'localhost',
    ]);
    Http::fake([
        'https://challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response(['success' => false]),
    ]);

    $this->post(route('marketing.free-site-audit.store'), [
        'website_url' => 'northfield.example',
        'cf-turnstile-response' => 'invalid-token',
    ])->assertSessionHasErrors('cf-turnstile-response');

    expect(WebsiteAudit::query()->exists())->toBeFalse();
    Queue::assertNothingPushed();
});

it('requires a valid Turnstile token before starting the website audit', function (): void {
    Queue::fake();
    config([
        'services.turnstile.marketing.enabled' => true,
        'services.turnstile.marketing.site_key' => 'site-key',
        'services.turnstile.marketing.secret_key' => 'secret-key',
        'services.turnstile.marketing.hostname' => 'localhost',
    ]);
    Http::fake([
        'https://challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response(['success' => true, 'hostname' => 'localhost']),
    ]);

    $this->get(route('marketing.free-site-audit'))
        ->assertSuccessful()
        ->assertSee('data-sitekey="site-key"', false)
        ->assertSee('https://challenges.cloudflare.com/turnstile/v0/api.js', false);

    $this->from(route('marketing.free-site-audit'))
        ->post(route('marketing.free-site-audit.store'), ['website_url' => 'northfield.example'])
        ->assertSessionHasErrors('cf-turnstile-response');

    $response = $this->from(route('marketing.free-site-audit'))
        ->post(route('marketing.free-site-audit.store'), [
            'website_url' => 'northfield.example',
            'cf-turnstile-response' => 'valid-token',
        ]);

    $response->assertRedirect(route('marketing.website-audits.show', WebsiteAudit::query()->sole()));
    Queue::assertPushed(GenerateWebsiteAudit::class);
});

it('throttles repeated website audit submissions', function (): void {
    Queue::fake();
    $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.42']);

    foreach (['one.example', 'two.example', 'three.example'] as $domain) {
        $this->post(route('marketing.free-site-audit.store'), ['website_url' => $domain])->assertRedirect();
    }

    $this->post(route('marketing.free-site-audit.store'), ['website_url' => 'four.example'])->assertTooManyRequests();
    expect(WebsiteAudit::query()->count())->toBe(3);
});

it('sends repeat submissions to the existing report without another audit', function (): void {
    Queue::fake();

    $this->post(route('marketing.free-site-audit.store'), ['website_url' => 'repeat-audit.example'])
        ->assertRedirect();

    $audit = WebsiteAudit::query()->sole();

    foreach (range(1, 4) as $attempt) {
        $this->post(route('marketing.free-site-audit.store'), ['website_url' => 'repeat-audit.example'])
            ->assertRedirect(route('marketing.website-audits.show', $audit));
    }

    $audit->update(['status' => WebsiteAudit::STATUS_COMPLETED]);

    $this->post(route('marketing.free-site-audit.store'), ['website_url' => 'repeat-audit.example'])
        ->assertRedirect(route('marketing.website-audits.show', $audit));

    expect(WebsiteAudit::query()->count())->toBe(1);
    Queue::assertPushed(GenerateWebsiteAudit::class, 1);
});

it('reuses a report opened from an existing private link', function (): void {
    Queue::fake();
    $audit = WebsiteAudit::factory()->create([
        'website_url' => 'https://existing-audit.example',
        'domain' => 'existing-audit.example',
        'status' => WebsiteAudit::STATUS_COMPLETED,
    ]);

    $this->get(route('marketing.website-audits.show', $audit))->assertSuccessful();

    $this->post(route('marketing.free-site-audit.store'), ['website_url' => 'existing-audit.example'])
        ->assertRedirect(route('marketing.website-audits.show', $audit));

    expect(WebsiteAudit::query()->count())->toBe(1);
    Queue::assertNothingPushed();
});

it('keeps the per-domain daily cap for visitors without the report link', function (): void {
    Queue::fake();

    foreach (range(1, 3) as $attempt) {
        $this->flushSession();
        $this->withServerVariables(['REMOTE_ADDR' => "198.51.100.{$attempt}"])
            ->post(route('marketing.free-site-audit.store'), ['website_url' => 'shared-audit.example'])
            ->assertRedirect();
    }

    $this->flushSession();
    $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.4'])
        ->post(route('marketing.free-site-audit.store'), ['website_url' => 'shared-audit.example'])
        ->assertTooManyRequests();

    expect(WebsiteAudit::query()->count())->toBe(3);
});

it('does not rate limit audit submissions for signed-in admins', function (): void {
    Queue::fake();
    $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));

    foreach (range(1, 5) as $attempt) {
        $this->post(route('marketing.free-site-audit.store'), [
            'website_url' => "admin-audit-{$attempt}.example",
        ])->assertRedirect();
    }

    expect(WebsiteAudit::query()->count())->toBe(5);
    Queue::assertPushed(GenerateWebsiteAudit::class, 5);
});

it('does not rate limit report views and status checks for signed-in admins', function (): void {
    $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));
    $audit = WebsiteAudit::factory()->create();

    foreach (range(1, 61) as $attempt) {
        $this->get(route('marketing.website-audits.show', $audit))->assertSuccessful();
    }

    foreach (range(1, 121) as $attempt) {
        $this->getJson(route('marketing.website-audits.status', $audit))->assertSuccessful();
    }
});

it('allows repeated audit submissions and report polling while developing locally', function (): void {
    Queue::fake();
    app()->bind(StoreFreeSiteAuditRequest::class, fn () => new class extends StoreFreeSiteAuditRequest
    {
        protected function domainResolvesToPublicAddress(string $host): bool
        {
            return true;
        }
    });
    $this->withoutMiddleware(PreventRequestForgery::class);
    $this->app->detectEnvironment(fn (): string => 'local');

    try {
        foreach (range(1, 4) as $attempt) {
            $this->post(route('marketing.free-site-audit.store'), [
                'website_url' => "local-test-{$attempt}.example",
            ])->assertRedirect();
        }

        $audit = WebsiteAudit::query()->firstOrFail();

        foreach (range(1, 61) as $attempt) {
            $this->get(route('marketing.website-audits.show', $audit))->assertSuccessful();
        }

        foreach (range(1, 121) as $attempt) {
            $this->getJson(route('marketing.website-audits.status', $audit))->assertSuccessful();
        }

        expect(WebsiteAudit::query()->count())->toBe(4);
    } finally {
        $this->app->detectEnvironment(fn (): string => 'testing');
    }
});

it('expires private website audit links', function (): void {
    $audit = WebsiteAudit::factory()->create(['expires_at' => now()->subMinute()]);

    $this->get(route('marketing.website-audits.show', $audit))->assertNotFound();
    $this->getJson(route('marketing.website-audits.status', $audit))->assertNotFound();
});

it('stores audit results and sends the customer results email', function (): void {
    Mail::fake();
    $prospect = Prospect::factory()->create([
        'email' => 'alex@example.com',
        'analysis_status' => 'pending',
    ]);
    $analyzer = Mockery::mock(ProspectWebsiteAnalyzer::class);
    $analyzer->shouldReceive('analyze')->once()->with($prospect->website_url)->andReturn([
        'score' => 35,
        'findings' => [['category' => 'Security', 'key' => 'https', 'title' => 'HTTPS enabled', 'severity' => 'warning', 'message' => 'HTTPS should be reviewed.']],
        'contacts' => ['emails' => [], 'phones' => [], 'contact_page_url' => null, 'contact_form_url' => null],
    ]);

    (new GenerateFreeSiteAudit($prospect))->handle($analyzer);

    $prospect->refresh();
    expect($prospect->analysis_status)->toBe('completed')
        ->and($prospect->status)->toBe('researched')
        ->and($prospect->opportunity_score)->toBe(35)
        ->and($prospect->activities()->where('type', 'free_audit_completed')->exists())->toBeTrue()
        ->and($prospect->activities()->where('type', 'free_audit_email_sent')->exists())->toBeTrue();

    Mail::assertSent(FreeSiteAuditResults::class, fn (FreeSiteAuditResults $mail): bool => $mail->hasTo('alex@example.com'));
});

it('retries a results email without repeating a completed audit', function (): void {
    Mail::fake();
    $prospect = Prospect::factory()->create([
        'email' => 'alex@example.com',
        'analysis_status' => 'completed',
        'analysed_at' => now(),
    ]);
    $analyzer = Mockery::mock(ProspectWebsiteAnalyzer::class);
    $analyzer->shouldNotReceive('analyze');

    (new GenerateFreeSiteAudit($prospect))->handle($analyzer);

    Mail::assertSent(FreeSiteAuditResults::class, fn (FreeSiteAuditResults $mail): bool => $mail->hasTo('alex@example.com'));
});

it('automatically redispatches completed audit emails that have no delivery result', function (): void {
    Queue::fake();
    $prospect = Prospect::factory()->create([
        'email' => 'alex@example.com',
        'analysis_status' => 'completed',
    ]);
    $prospect->recordActivity('free_audit_requested', 'Free site audit requested from the marketing website.');

    $this->artisan('free-site-audits:dispatch-pending-emails')
        ->expectsOutput('Dispatched 1 pending free site audit email(s).')
        ->assertSuccessful();

    Queue::assertPushed(GenerateFreeSiteAudit::class, fn (GenerateFreeSiteAudit $job): bool => $job->prospect->is($prospect));
});

it('shows automatic delivery instead of outreach approval for free audits', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $prospect = Prospect::factory()->for($admin, 'owner')->create([
        'email' => 'alex@example.com',
        'analysis_status' => 'completed',
        'status' => 'researched',
    ]);
    $prospect->recordActivity('free_audit_requested', 'Free site audit requested from the marketing website.');

    $this->actingAs($admin)
        ->get(route('admin.prospects.show', $prospect))
        ->assertSuccessful()
        ->assertSee('queued for automatic delivery')
        ->assertDontSee('Outreach draft')
        ->assertDontSee('Waiting for research');
});

it('includes report and contact calls to action in the results email and landing page', function (): void {
    $prospect = Prospect::factory()->create([
        'business_name' => 'Northfield Studio',
        'contact_name' => 'Alex',
        'analysis_status' => 'completed',
        'analysed_at' => now(),
        'findings' => [['category' => 'Security', 'key' => 'https', 'title' => 'HTTPS enabled', 'severity' => 'passed', 'message' => 'HTTPS is enabled.']],
    ]);
    $mail = new FreeSiteAuditResults($prospect);

    $mail->assertSeeInHtml('View your audit results')
        ->assertSeeInHtml('Get in touch')
        ->assertSeeInHtml('Book a call with Ross');

    $reportUrl = URL::temporarySignedRoute('prospect-reports.show', now()->addHour(), ['prospect' => $prospect]);
    $this->get($reportUrl)
        ->assertSuccessful()
        ->assertSee('Your website audit')
        ->assertSee('Get in touch')
        ->assertSee('Book a call with Ross');
});

it('keeps the personal video review email connected to work done for the customer', function (): void {
    $audit = WebsiteAudit::factory()->create([
        'status' => WebsiteAudit::STATUS_COMPLETED,
        'expires_at' => now()->addDays(14),
        'personal_review' => ['loom_url' => 'https://www.loom.com/share/real-review'],
    ]);

    (new WebsiteAuditPersonalReview($audit))
        ->assertSeeInHtml('Watch your website review')
        ->assertSeeInHtml('https://www.loom.com/share/real-review')
        ->assertSeeInHtml('If you’d like us to handle these improvements')
        ->assertSeeInHtml('at the level of support you choose.')
        ->assertSeeInHtml(route('marketing.ppc.book'))
        ->assertSeeInHtml(route('marketing.website-audits.show', $audit));
});

it('requires email access even when this report was requested in another browser', function (bool $requested, bool $hasFixes): void {
    $audit = WebsiteAudit::factory()->create([
        'status' => WebsiteAudit::STATUS_COMPLETED, 'created_at' => now()->subMinute(),
        'report_requested_at' => $requested ? now() : null,
        'findings' => $hasFixes ? [['severity' => 'warning', 'title' => 'Check required']] : [],
    ]);
    $this->get(route('marketing.website-audits.show', $audit))->assertSuccessful()
        ->assertSee('View my report')->assertSee('data-audit-email-gate', false)
        ->assertDontSee('data-audit-book-call', false)->assertDontSee('Check required')
        ->assertDontSee('48 hours')->assertDontSee('The improvements we could take care of.');
})->with([[false, false], [false, true], [true, false], [true, true]]);

it('defers DNS validation to the guarded worker for local macOS audit submissions', function (): void {
    $this->withoutMiddleware(PreventRequestForgery::class);
    Queue::fake();
    config()->set('services.turnstile.marketing.enabled', false);
    app()->instance('env', 'local');

    $response = $this->post(route('marketing.free-site-audit.store'), [
        'website_url' => 'local-dns-check.invalid',
    ]);

    $audit = WebsiteAudit::query()->sole();
    $response->assertRedirect(route('marketing.website-audits.show', $audit));
    Queue::assertPushed(GenerateWebsiteAudit::class, fn (GenerateWebsiteAudit $job): bool => $job->audit->is($audit));

    $this->post(route('marketing.free-site-audit.store'), ['website_url' => 'https://user:pass@example.com'])
        ->assertSessionHasErrors('website_url');
    $this->post(route('marketing.free-site-audit.store'), ['website_url' => 'http://127.0.0.1'])
        ->assertSessionHasErrors('website_url');
})->skip(PHP_OS_FAMILY !== 'Darwin', 'The DNS workaround only applies to local macOS.');

it('keeps saved findings and ranking details out of the locked page source', function (): void {
    $audit = WebsiteAudit::factory()->create([
        'status' => WebsiteAudit::STATUS_COMPLETED,
        'created_at' => now()->subMinute(),
        'findings' => [
            ['key' => 'frame_protection', 'title' => 'Frame protection', 'message' => 'No frame protection was found.', 'severity' => 'warning'],
            ['key' => 'meta_description', 'title' => 'Meta description', 'message' => 'The homepage has no meta description.', 'severity' => 'warning'],
            ['key' => 'page_title', 'title' => 'Page title', 'message' => 'The homepage has no title.', 'severity' => 'failed'],
            ['key' => 'viewport', 'title' => 'Mobile viewport', 'message' => 'A mobile viewport is configured.', 'severity' => 'passed'],
        ],
        'insights' => ['seo' => ['estimated_monthly_visits' => 100, 'keywords' => [['term' => 'private ranking term', 'position' => 15, 'monthly_searches' => 500]]]],
    ]);

    $this->get(route('marketing.website-audits.show', $audit))->assertSuccessful()
        ->assertSee('View my report')->assertSee('25%')
        ->assertDontSee('The homepage has no title.')
        ->assertDontSee('The homepage has no meta description.')
        ->assertDontSee('No frame protection was found.')
        ->assertDontSee('private ranking term')->assertDontSee('Google ranking terms');

});

it('shows the measured fix count without exposing the finding', function (): void {
    $audit = WebsiteAudit::factory()->create([
        'status' => WebsiteAudit::STATUS_COMPLETED,
        'created_at' => now()->subMinute(),
        'findings' => [['key' => 'response_time', 'title' => 'Page response time', 'message' => 'The page responded in 2500 ms.', 'severity' => 'warning']],
    ]);

    $response = $this->get(route('marketing.website-audits.show', $audit))->assertSuccessful()
        ->assertSee('View my report')->assertDontSee('The page responded in 2500 ms.');
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
    expect((new DOMXPath($document))->query('//*[@data-audit-fix-count]')->item(0)->textContent)->toBe('1');

});
