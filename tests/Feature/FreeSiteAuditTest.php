<?php

use App\Jobs\GenerateFreeSiteAudit;
use App\Jobs\GenerateWebsiteAudit;
use App\Mail\FreeSiteAuditResults;
use App\Mail\WebsiteAuditLeadReceived;
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
        ->assertSee('Building your audit.')
        ->assertSee('Your results will appear here automatically.')
        ->assertDontSee('data-audit-email-open', false)
        ->assertSee(route('marketing.website-audits.status', $audit));

    $this->getJson(route('marketing.website-audits.status', $audit))
        ->assertSuccessful()
        ->assertExactJson(['status' => 'pending', 'completed' => false, 'failed' => false, 'ai_visibility_status' => null]);
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
        ->assertSee('Your website checks are ready.')
        ->assertSee('data-audit-next-step', false)
        ->assertSee('Website fix flagged')
        ->assertSee('Technical health')
        ->assertSee('URLs in sitemap')
        ->assertSee('Search estimates are unavailable')
        ->assertSeeInOrder(['Fix the website issues.', 'Improve existing content.', 'Create content for missed searches.', 'Strengthen the website and its reputation.', 'Measure and keep improving.'])
        ->assertSee('href="'.route('marketing.ppc.book').'"', false)
        ->assertSee('Book a call with Ross')
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
        ->assertSee('Want a copy by email?')
        ->assertSee('data-audit-email-open', false)
        ->assertSee('aria-controls="audit-email-dialog"', false)
        ->assertSee('fixed inset-0 m-auto max-h-[calc(100dvh-2rem)]', false)
        ->assertSee('30000')
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
        ->assertSeeInHtml('Book a call with Ross')
        ->assertSeeInHtml(route('marketing.website-audits.show', $audit))
        ->assertSeeInHtml(route('marketing.ppc.book'));
    (new WebsiteAuditLeadReceived($audit))
        ->assertSeeInHtml('alex@example.com')
        ->assertSeeInHtml(route('admin.onboarding.index', ['search' => $audit->domain]));

    $this->get(route('marketing.website-audits.show', $audit))
        ->assertSuccessful()
        ->assertSee('Your report is on its way.')
        ->assertDontSee('data-audit-email-open', false)
        ->assertDontSee('Want a copy by email?');
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

    $response = $this->get(route('marketing.website-audits.show', $audit))
        ->assertSuccessful()
        ->assertSee('Website fixes flagged');

    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
    expect((new DOMXPath($document))->query('//*[@data-audit-fix-count]')->item(0)->textContent)->toBe('0');
    $response->assertSee('No fixes flagged');
});

it('shows measured search estimates and a conditional six-month scenario', function (): void {
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

    $this->get(route('marketing.website-audits.show', $audit))
        ->assertSuccessful()
        ->assertSee('50%')
        ->assertSee('URLs in sitemap')
        ->assertSee('Google ranking terms')
        ->assertSee('Referring domains')
        ->assertDontSee('DataForSEO')
        ->assertSee('garden office fitters')
        ->assertSee('1,000')
        ->assertSee('Needs some work')
        ->assertSee('Visible on page one')
        ->assertSee('Today')
        ->assertSee('Possible in six months')
        ->assertSee('128–160')
        ->assertDontSee('128–145')
        ->assertSee('not a guarantee.');
});

it('shows page-one rankings beside striking-distance rankings and falls back to other terms', function (): void {
    $audit = WebsiteAudit::factory()->create([
        'status' => WebsiteAudit::STATUS_COMPLETED,
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

    $response = $this->get(route('marketing.website-audits.show', $audit))
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

    $this->get(route('marketing.website-audits.show', $audit))
        ->assertSuccessful()
        ->assertSee('Rankings found')
        ->assertSee('distant term')
        ->assertDontSee('Within striking distance');
});

it('explains when sitemap URLs point at a different domain', function (): void {
    $audit = WebsiteAudit::factory()->create([
        'domain' => 'vvipeventszante.com',
        'status' => WebsiteAudit::STATUS_COMPLETED,
        'created_at' => now()->subSeconds(11),
        'insights' => [
            'pages_listed' => 81,
            'pages_partial' => false,
            'pages_mismatched_domain' => 81,
            'pages_mismatched_host' => 'vvipeventszante.test',
            'seo' => null,
        ],
    ]);

    $this->get(route('marketing.website-audits.show', $audit))
        ->assertSuccessful()
        ->assertSee('URLs in sitemap')
        ->assertSee('Sitemap issue: 81 URLs point to vvipeventszante.test instead of vvipeventszante.com.')
        ->assertDontSee('0+');
});

it('marks poor technical health and missing page-one visibility as needs attention', function (): void {
    $audit = WebsiteAudit::factory()->create([
        'status' => WebsiteAudit::STATUS_COMPLETED,
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

    $this->get(route('marketing.website-audits.show', $audit))
        ->assertSuccessful()
        ->assertSee('Needs attention')
        ->assertSee('No page-one terms yet')
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
        ->assertSee('Building your audit.')
        ->assertSee('Checking your website and preparing your report.');

    $this->getJson(route('marketing.website-audits.status', $audit))
        ->assertExactJson(['status' => 'completed', 'completed' => false, 'failed' => false, 'ai_visibility_status' => null]);

    $this->travel(11)->seconds();

    $this->getJson(route('marketing.website-audits.status', $audit))
        ->assertExactJson(['status' => 'completed', 'completed' => true, 'failed' => false, 'ai_visibility_status' => null]);
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

it('allows repeated audit submissions and report polling while developing locally', function (): void {
    Queue::fake();
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
