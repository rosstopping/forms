<?php

use App\Jobs\GenerateFreeSiteAudit;
use App\Jobs\GenerateWebsiteAudit;
use App\Mail\FreeSiteAuditResults;
use App\Models\Prospect;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteAudit;
use App\Models\WebsiteDomain;
use App\Notifications\WebsiteAuditClaim;
use App\Services\ProspectWebsiteAnalyzer;
use App\Support\MembershipPlan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
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
        ->assertSee('Reviewing your website')
        ->assertSee(route('marketing.website-audits.status', $audit));

    $this->getJson(route('marketing.website-audits.status', $audit))
        ->assertSuccessful()
        ->assertExactJson(['status' => 'pending', 'completed' => false, 'failed' => false]);
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

    (new GenerateWebsiteAudit($audit))->handle($analyzer);

    expect($audit->refresh()->status)->toBe(WebsiteAudit::STATUS_COMPLETED)
        ->and($audit->opportunity_score)->toBe(35)
        ->and($audit->completed_at)->not->toBeNull();

    $this->get(route('marketing.website-audits.show', $audit))
        ->assertSuccessful()
        ->assertSee('We reviewed publicly visible signals')
        ->assertSee('data-audit-next-step', false)
        ->assertSeeInOrder(['Turn these findings into a fix plan', 'Checks completed', 'HTTPS should be reviewed.'])
        ->assertSee('Start preparing my fixes')
        ->assertSee('HTTPS should be reviewed.');
});

it('keeps the progress experience visible for at least ten seconds', function (): void {
    $audit = WebsiteAudit::factory()->create([
        'status' => WebsiteAudit::STATUS_COMPLETED,
        'findings' => [],
        'completed_at' => now(),
    ]);

    $this->get(route('marketing.website-audits.show', $audit))
        ->assertSuccessful()
        ->assertSee('Reviewing your website')
        ->assertSee('We are reviewing publicly visible signals');

    $this->getJson(route('marketing.website-audits.status', $audit))
        ->assertExactJson(['status' => 'completed', 'completed' => false, 'failed' => false]);

    $this->travel(11)->seconds();

    $this->getJson(route('marketing.website-audits.status', $audit))
        ->assertExactJson(['status' => 'completed', 'completed' => true, 'failed' => false]);
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
