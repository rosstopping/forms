<?php

use App\Models\MarketingConversion;
use App\Models\User;
use App\Models\WebsiteAudit;
use App\Models\WebsiteAuditVisit;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

it('records bounded idempotent engagement separately from successful lead actions', function (): void {
    $audit = WebsiteAudit::factory()->create(['status' => WebsiteAudit::STATUS_COMPLETED, 'created_at' => now()->subMinute()]);
    $url = URL::temporarySignedRoute('marketing.website-audits.engagement', now()->addHour(), $audit);
    $visitId = (string) Str::uuid();
    $this->withSession(['marketing.website_audit_id' => $audit->public_id])->postJson($url, [
        'visit_id' => $visitId, 'events' => ['viewed', 'booking_clicked', 'email_started'], 'active_seconds' => 7200, 'scroll_percent' => 40,
    ])->assertNoContent();
    $visit = $audit->visits()->sole();
    expect($visit->active_seconds)->toBeLessThanOrEqual(5)
        ->and($visit->email_started_at)->not->toBeNull()
        ->and($visit->email_submitted_at)->toBeNull()
        ->and($audit->fresh()->email)->toBeNull()
        ->and($audit->fresh()->lead_call_booked_at)->toBeNull();
    $clickedAt = $visit->booking_clicked_at;
    $this->travel(60)->seconds();
    $this->postJson($url, ['visit_id' => $visitId, 'events' => ['booking_clicked', 'calendar_opened'], 'active_seconds' => 30, 'scroll_percent' => 80])->assertNoContent();
    $this->postJson($url, ['visit_id' => $visitId, 'events' => ['heartbeat'], 'active_seconds' => 10, 'scroll_percent' => 20])->assertNoContent();
    expect($visit->refresh()->active_seconds)->toBe(30)
        ->and($visit->scroll_percent)->toBe(80)
        ->and($visit->booking_clicked_at->equalTo($clickedAt))->toBeTrue()
        ->and($audit->visits()->count())->toBe(1)
        ->and(MarketingConversion::count())->toBe(0);
});

it('rejects forged expired cross-session and out-of-range engagement', function (): void {
    $audit = WebsiteAudit::factory()->create(['status' => WebsiteAudit::STATUS_COMPLETED, 'created_at' => now()->subMinute()]);
    $data = ['visit_id' => (string) Str::uuid(), 'events' => ['viewed'], 'active_seconds' => 0, 'scroll_percent' => 0];
    $url = URL::temporarySignedRoute('marketing.website-audits.engagement', now()->addHour(), $audit);
    $this->postJson($url, $data)->assertForbidden();
    $this->withSession(['marketing.website_audit_id' => $audit->public_id])->postJson(route('marketing.website-audits.engagement', $audit), $data)->assertForbidden();
    $expiredUrl = URL::temporarySignedRoute('marketing.website-audits.engagement', now()->subMinute(), $audit);
    $this->postJson($expiredUrl, $data)->assertForbidden();
    $this->postJson($url, [...$data, 'events' => ['call_booked']])->assertRedirect()->assertSessionHasErrors('events.0');
    $this->postJson($url, [...$data, 'active_seconds' => 7201])->assertRedirect()->assertSessionHasErrors('active_seconds');
    $this->postJson($url, [...$data, 'scroll_percent' => 101])->assertRedirect()->assertSessionHasErrors('scroll_percent');
    $audit->update(['expires_at' => now()->subMinute()]);
    $this->postJson($url, $data)->assertForbidden();
    expect(WebsiteAuditVisit::count())->toBe(0);
});

it('captures the chosen business goal and marks email submission only after acceptance', function (): void {
    Mail::fake();
    $audit = WebsiteAudit::factory()->create(['status' => WebsiteAudit::STATUS_COMPLETED, 'created_at' => now()->subMinute()]);
    $visit = WebsiteAuditVisit::factory()->for($audit, 'audit')->create(['email_started_at' => now()]);
    $this->post(route('marketing.website-audits.email-report', $audit), ['email' => 'bad', 'engagement_visit_id' => $visit->visit_id])->assertSessionHasErrors('email');
    expect($visit->fresh()->email_submitted_at)->toBeNull();
    $this->post(route('marketing.website-audits.email-report', $audit), ['email' => 'owner@example.com', 'personal_review' => 1, 'customer_goal' => 'enquiries', 'engagement_visit_id' => $visit->visit_id])->assertRedirect();
    expect($audit->fresh()->customer_goal)->toBe('enquiries')
        ->and($audit->fresh()->marketing_consent_at)->toBeNull()
        ->and($visit->fresh()->email_submitted_at)->not->toBeNull();
    $this->get(route('marketing.website-audits.show', $audit))->assertViewHas('showDetails', true);
});

it('requires a capturing session or signed link rather than a stored email to reveal rankings', function (): void {
    $audit = WebsiteAudit::factory()->create([
        'status' => WebsiteAudit::STATUS_COMPLETED, 'created_at' => now()->subMinute(), 'report_requested_at' => now(),
        'insights' => ['seo' => ['location_code' => 2826, 'organic_keywords' => 1, 'top_3_keywords' => 1, 'top_10_keywords' => 1, 'referring_domains' => 0, 'estimated_monthly_visits' => 10, 'sample_size' => 1, 'keywords' => [['term' => 'private ranking term', 'position' => 2, 'monthly_searches' => 100]]]],
    ]);
    $this->get(route('marketing.website-audits.show', ['websiteAudit' => $audit, 'full' => 1]))->assertSuccessful()->assertDontSee('private ranking term')->assertDontSee('marketing.website-audits.full');
    $this->get(route('marketing.website-audits.full', $audit))->assertForbidden();
    $url = URL::temporarySignedRoute('marketing.website-audits.full', now()->addHour(), $audit);
    $this->get($url)->assertSuccessful()->assertSee('private ranking term')->assertSee('name="robots" content="noindex"', false)
        ->assertSee('marketing-events')->assertSee('data-audit-engagement-url', false);
    $this->get($url.'&extra=1')->assertForbidden();
    $this->travel(61)->minutes();
    $this->get($url)->assertForbidden();
    $this->actingAs(User::factory()->create())->get(route('admin.onboarding.audits.show', $audit))->assertForbidden();
    $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]))->get(route('admin.onboarding.audits.show', $audit))->assertSuccessful()
        ->assertSee('private ranking term')->assertSee('marketing-events')->assertDontSee('data-audit-engagement-url', false);
});

it('shows interest signals and share controls in Admin Onboarding without counting clicks as bookings', function (): void {
    $audit = WebsiteAudit::factory()->create(['status' => WebsiteAudit::STATUS_COMPLETED, 'created_at' => now()->subMinute(), 'customer_goal' => 'bookings']);
    WebsiteAuditVisit::factory()->for($audit, 'audit')->create(['active_seconds' => 75, 'scroll_percent' => 80, 'review_opened_at' => now(), 'email_started_at' => now(), 'booking_clicked_at' => now(), 'calendar_opened_at' => now()]);
    $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]))->get(route('admin.onboarding.index'))->assertSuccessful()
        ->assertSee('1m 15s')->assertSee('80%')->assertSee('Started; not submitted')->assertSee('Clicked; no confirmed booking yet')->assertSee('Copy full report link')->assertSee('View full report');
    expect($audit->fresh()->lead_call_booked_at)->toBeNull();
});

it('shows server-observed booking clicks even when browser tracking is unavailable', function (): void {
    $audit = WebsiteAudit::factory()->create(['status' => WebsiteAudit::STATUS_COMPLETED, 'created_at' => now()->subMinute()]);
    $this->withSession(['marketing.website_audit_id' => $audit->public_id])->getJson(route('marketing.ppc.book'))->assertSuccessful();
    expect($audit->visits()->count())->toBe(0);
    $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]))->get(route('admin.onboarding.index'))->assertSuccessful()->assertSee('Clicked; no confirmed booking yet');
});
