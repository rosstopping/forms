<?php

use App\Enums\OnboardingLifecycleStep;
use App\Jobs\GenerateWebsiteAuditFullReport;
use App\Models\OnboardingLifecycleMessage;
use App\Models\SearchConsoleConnection;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteAudit;
use App\Models\WebsiteDomain;
use App\Services\MarketingAuditScreenshot;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

it('gives admins a lead view of users who signed up through Get started', function (): void {
    $this->travelTo('2026-09-07 12:00:00 Europe/London');
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $customer = User::factory()->create([
        'name' => 'Alex Onboarding',
        'email' => 'alex@example.test',
        'onboarding_status' => 'trial_active',
        'onboarding_trial_ends_at' => now()->addDays(10),
        'membership_status' => 'trialing',
    ]);
    $website = Website::factory()->for($customer, 'owner')->create(['name' => 'Alex Website']);
    $website->domains()->create([
        'domain' => 'alex-business.test',
        'is_primary' => true,
        'ownership_status' => WebsiteDomain::OWNERSHIP_PENDING,
    ]);
    WebsiteAudit::factory()->for($customer)->for($website)->create([
        'domain' => 'alex-business.test',
        'claimed_at' => now()->subDays(4),
    ]);
    OnboardingLifecycleMessage::factory()->for($customer)->create([
        'step' => OnboardingLifecycleStep::Welcome,
        'scheduled_for' => now()->subDays(4),
        'queued_at' => now()->subDays(4),
        'sent_at' => now()->subDays(4),
        'clicked_at' => now()->subDays(4),
    ]);
    OnboardingLifecycleMessage::factory()->for($customer)->create([
        'step' => OnboardingLifecycleStep::SearchConsole,
        'scheduled_for' => now()->addDay(),
    ]);
    $manualCustomer = User::factory()->create(['name' => 'Manual Customer', 'onboarding_status' => 'trial_active']);
    Website::factory()->for($manualCustomer, 'owner')->create();
    WebsiteAudit::factory()->create([
        'domain' => 'unclaimed-business.test',
        'email' => 'waiting@example.test',
        'claimed_at' => null,
        'status' => WebsiteAudit::STATUS_COMPLETED,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.onboarding.index'))
        ->assertOk()
        ->assertSee('Onboarding leads')
        ->assertSee('Alex Onboarding')
        ->assertSee('alex@example.test')
        ->assertSee('alex-business.test')
        ->assertSee('Day 5 of 14')
        ->assertSee('10 days remaining')
        ->assertSee('Pending')
        ->assertSee('Search Console not connected')
        ->assertSee('Not booked')
        ->assertSee('Website audits')
        ->assertSee('unclaimed-business.test')
        ->assertSee('waiting@example.test')
        ->assertSee('Email captured')
        ->assertSee('Trial welcome sent')
        ->assertSee('Next: Search Console reminder')
        ->assertSee('1 tracked click')
        ->assertSee('View as user')
        ->assertDontSee('Manual Customer');
});

it('shows emailed audit requests as onboarding leads without creating an account', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $audit = WebsiteAudit::factory()->create([
        'domain' => 'customer-site.example',
        'email' => 'owner@example.com',
        'status' => WebsiteAudit::STATUS_COMPLETED,
        'report_requested_at' => now(),
        'expires_at' => now()->addDays(14),
    ]);

    $this->actingAs($admin)
        ->get(route('admin.onboarding.index'))
        ->assertSuccessful()
        ->assertSee('Report requests')
        ->assertSee('customer-site.example')
        ->assertSee('owner@example.com')
        ->assertSee('Report requested')
        ->assertSee(route('marketing.website-audits.show', $audit));

    expect($audit->user_id)->toBeNull();
});

it('lets admins open the customer report and explicitly request the full report even after expiry', function (): void {
    Queue::fake();
    Http::preventStrayRequests();
    Storage::fake('local');
    $audit = WebsiteAudit::factory()->create([
        'status' => WebsiteAudit::STATUS_COMPLETED,
        'created_at' => now()->subDays(3),
        'expires_at' => now()->subDay(),
        'insights' => ['health_score' => 75, 'full_report' => ['status' => 'deferred']],
    ]);
    Storage::disk('local')->put(app(MarketingAuditScreenshot::class)->pathFor($audit), "\xFF\xD8\xFFpreview");
    $customerUrl = route('marketing.website-audits.show', $audit);
    $previewUrl = route('marketing.website-audits.preview', $audit);
    $fullReportUrl = route('admin.onboarding.audits.generate', $audit);

    $this->get($customerUrl)->assertNotFound();
    $this->get($previewUrl)->assertNotFound();
    $this->actingAs(User::factory()->create())->get($customerUrl)->assertNotFound();
    $this->get($previewUrl)->assertNotFound();
    $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]))
        ->get(route('admin.onboarding.index'))
        ->assertSuccessful()
        ->assertSee('href="'.$customerUrl.'"', false)
        ->assertSee('View customer report')
        ->assertSee('method="POST" action="'.$fullReportUrl.'"', false)
        ->assertSee('View full report');
    $this->get($customerUrl)->assertSuccessful()
        ->assertViewHas('showDetails', false)
        ->assertSee('src="'.$previewUrl.'"', false)
        ->assertDontSee('Load full research');
    $this->get($previewUrl)->assertSuccessful()->assertHeader('Content-Type', 'image/jpeg');
    Queue::assertNothingPushed();
    $this->post($fullReportUrl)->assertRedirect(route('admin.onboarding.audits.show', $audit));
    $this->get(route('admin.onboarding.audits.show', $audit))->assertSuccessful()->assertViewHas('showDetails', true);
    Queue::assertPushed(GenerateWebsiteAuditFullReport::class, 1);
    Http::assertNothingSent();
    expect($audit->refresh()->expires_at->isPast())->toBeTrue();
});

it('filters onboarding leads by search verification and call progress', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $verifiedCustomer = User::factory()->create([
        'name' => 'Verified Customer',
        'onboarding_status' => 'trial_active',
        'onboarding_trial_ends_at' => now()->addWeek(),
        'onboarding_call_booked_at' => now(),
    ]);
    $verifiedWebsite = Website::factory()->for($verifiedCustomer, 'owner')->create();
    $verifiedWebsite->domains()->create([
        'domain' => 'verified.test',
        'is_primary' => true,
        'ownership_status' => WebsiteDomain::OWNERSHIP_VERIFIED,
        'verification_method' => 'search_console',
    ]);
    SearchConsoleConnection::factory()->for($verifiedWebsite)->for($verifiedCustomer, 'connector')->create([
        'property_url' => 'sc-domain:verified.test',
    ]);
    WebsiteAudit::factory()->for($verifiedCustomer)->for($verifiedWebsite)->create([
        'domain' => 'verified.test',
        'claimed_at' => now(),
    ]);

    $pendingCustomer = User::factory()->create([
        'name' => 'Pending Customer',
        'onboarding_status' => 'trial_active',
        'onboarding_trial_ends_at' => now()->addWeek(),
    ]);
    $pendingWebsite = Website::factory()->for($pendingCustomer, 'owner')->create();
    $pendingWebsite->domains()->create([
        'domain' => 'pending.test',
        'is_primary' => true,
        'ownership_status' => WebsiteDomain::OWNERSHIP_PENDING,
    ]);
    WebsiteAudit::factory()->for($pendingCustomer)->for($pendingWebsite)->create([
        'domain' => 'pending.test',
        'claimed_at' => now(),
    ]);

    $this->actingAs($admin)
        ->get(route('admin.onboarding.index', [
            'search' => 'verified.test',
            'verification' => 'verified',
            'call' => 'booked',
        ]))
        ->assertOk()
        ->assertSee('Verified Customer')
        ->assertSee('Via Search Console')
        ->assertSee('Booked')
        ->assertDontSee('Pending Customer');
});

it('keeps onboarding lead management admin only', function (): void {
    $customer = User::factory()->create();
    Website::factory()->for($customer, 'owner')->create();

    $this->actingAs($customer)
        ->get(route('admin.onboarding.index'))
        ->assertForbidden();

    $this->get(route('admin.dashboard'))
        ->assertOk()
        ->assertDontSee('href="'.route('admin.onboarding.index').'"', false);
});
