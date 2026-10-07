<?php

use App\Mail\WebsiteAuditLeadReceived;
use App\Mail\WebsiteAuditPersonalReview;
use App\Mail\WebsiteAuditReport;
use App\Models\User;
use App\Models\WebsiteAudit;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

it('requests a personal review without enrolling the visitor in marketing', function (): void {
    Mail::fake();
    $this->travelTo('2026-10-09 12:00:00');
    $audit = WebsiteAudit::factory()->create(['status' => WebsiteAudit::STATUS_COMPLETED, 'created_at' => now()->subMinute()]);
    $this->post(route('marketing.website-audits.email-report', $audit), ['email' => ' OWNER@EXAMPLE.COM ', 'personal_review' => '1'])
        ->assertRedirect()->assertSessionHas('report_email_status', 'Your report is on its way. Ross will email your recommendations within one working day.');
    expect($audit->refresh()->personal_review_requested_at)->not->toBeNull()
        ->and($audit->personal_review_due_at->format('Y-m-d H:i'))->toBe('2026-10-12 12:00')
        ->and($audit->marketing_consent_at)->toBeNull()
        ->and($audit->marketing_consent_version)->toBeNull()
        ->and($audit->user_id)->toBeNull();
    Mail::assertQueued(WebsiteAuditReport::class);
    Mail::assertQueued(WebsiteAuditLeadReceived::class);
    (new WebsiteAuditReport($audit))->assertSeeInHtml('personally review your results');
    expect((new WebsiteAuditLeadReceived($audit))->envelope()->replyTo[0]->address)->toBe('owner@example.com');
    (new WebsiteAuditLeadReceived($audit))->assertSeeInHtml('Personal review due')->assertSeeInHtml('No marketing opt-in');
});

it('stores explicit optional consent and leaves duplicate requests unchanged', function (): void {
    Mail::fake();
    $audit = WebsiteAudit::factory()->create(['status' => WebsiteAudit::STATUS_COMPLETED, 'created_at' => now()->subMinute()]);
    $url = route('marketing.website-audits.email-report', $audit);
    $this->post($url, ['email' => 'owner@example.com', 'personal_review' => 1, 'marketing_consent' => 1])->assertRedirect();
    $consentAt = $audit->refresh()->marketing_consent_at;
    expect($consentAt)->not->toBeNull()->and($audit->marketing_consent_version)->toBe(WebsiteAudit::MARKETING_CONSENT_VERSION);
    $this->post($url, ['email' => 'owner@example.com', 'personal_review' => 1])->assertRedirect();
    expect($audit->refresh()->marketing_consent_at->equalTo($consentAt))->toBeTrue();
    Mail::assertQueuedCount(2);
    (new WebsiteAuditReport($audit))->assertSeeInHtml('Unsubscribe');
});

it('rejects invalid consent values', function (): void {
    Mail::fake();
    $audit = WebsiteAudit::factory()->create(['status' => WebsiteAudit::STATUS_COMPLETED, 'created_at' => now()->subMinute()]);
    $this->post(route('marketing.website-audits.email-report', $audit), ['email' => 'owner@example.com', 'personal_review' => 1, 'marketing_consent' => 'yes'])
        ->assertSessionHasErrors('marketing_consent');
    Mail::assertNothingQueued();
});

it('requires a signed link and explicit confirmation to withdraw consent even after report expiry', function (): void {
    $audit = WebsiteAudit::factory()->create(['email' => 'owner@example.com', 'marketing_consent_at' => now(), 'expires_at' => now()->subDay()]);
    $other = WebsiteAudit::factory()->create(['email' => $audit->email, 'marketing_consent_at' => now()]);
    $this->get(route('marketing.website-audits.email-preferences', $audit))->assertForbidden();
    $url = URL::signedRoute('marketing.website-audits.email-preferences', $audit);
    $this->get($url)->assertSuccessful()->assertSee('Unsubscribe from website advice')->assertSee('<meta name="robots" content="noindex">', false);
    expect($audit->refresh()->marketing_consent_at)->not->toBeNull();
    $this->post($url)->assertRedirect();
    expect($audit->refresh()->marketing_consent_at)->toBeNull()
        ->and($audit->marketing_consent_withdrawn_at)->not->toBeNull()
        ->and($other->refresh()->marketing_consent_at)->toBeNull();
});

it('lets an admin send exactly three personal priorities once and restores report access', function (): void {
    Mail::fake();
    $audit = WebsiteAudit::factory()->create([
        'status' => WebsiteAudit::STATUS_COMPLETED,
        'email' => 'owner@example.com',
        'personal_review_requested_at' => now()->subDays(3),
        'personal_review_due_at' => now()->subDays(2),
        'expires_at' => now()->subDay(),
    ]);
    $url = route('admin.onboarding.audits.review', $audit);
    $priorities = array_fill(0, 3, ['title' => 'Improve your enquiry page', 'impact' => 'Help visitors understand the service', 'next_step' => 'Add a clear enquiry action']);
    $this->post($url, ['priorities' => $priorities])->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create())->post($url, ['priorities' => $priorities])->assertForbidden();
    $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));
    $this->post($url, ['priorities' => array_slice($priorities, 0, 2)])->assertSessionHasErrors('priorities');
    $this->post($url, ['priorities' => $priorities])->assertRedirect();
    $this->post($url, ['priorities' => $priorities])->assertRedirect();
    expect($audit->refresh()->personal_review)->toBe($priorities)
        ->and($audit->personal_review_queued_at)->not->toBeNull()
        ->and($audit->hasExpired())->toBeFalse();
    Mail::assertQueuedCount(1);
    Mail::assertQueued(WebsiteAuditPersonalReview::class, fn ($mail): bool => $mail->hasTo('owner@example.com'));
    (new WebsiteAuditPersonalReview($audit))->assertSeeInHtml('Improve your enquiry page')
        ->assertSeeInHtml('Why it matters')->assertSeeInHtml('Next step')->assertSeeInHtml('enquiries, bookings or sales');
    expect((new WebsiteAuditPersonalReview($audit))->envelope()->replyTo[0]->address)->toBe(config('marketing.audit_notification_email'));
});

it('does not send unsolicited personal reviews', function (): void {
    Mail::fake();
    $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));
    $audit = WebsiteAudit::factory()->create(['status' => WebsiteAudit::STATUS_COMPLETED, 'email' => 'owner@example.com']);
    $priorities = array_fill(0, 3, ['title' => 'Title', 'impact' => 'Impact', 'next_step' => 'Step']);
    $this->post(route('admin.onboarding.audits.review', $audit), ['priorities' => $priorities])->assertUnprocessable();
    Mail::assertNothingQueued();
});

it('tracks replies calls and paying clients without counting a calendar click as a booking', function (): void {
    $audit = WebsiteAudit::factory()->create(['email' => 'owner@example.com', 'personal_review_requested_at' => now(), 'personal_review_due_at' => now()->addDay()]);
    $url = route('admin.onboarding.audits.lead', $audit);
    $this->actingAs(User::factory()->create())->patch($url, ['stage' => 'converted'])->assertForbidden();
    $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));
    $this->patch($url, ['stage' => 'invalid'])->assertSessionHasErrors('stage');
    foreach (['replied', 'call_booked', 'converted'] as $stage) {
        $this->patch($url, ['stage' => $stage])->assertRedirect();
    }
    expect($audit->refresh()->lead_replied_at)->not->toBeNull()
        ->and($audit->lead_call_booked_at)->not->toBeNull()
        ->and($audit->lead_converted_at)->not->toBeNull();
    $this->get(route('admin.onboarding.index'))->assertSuccessful()->assertSee('Paying clients')->assertSee('Reply received')->assertSee('Call booked');
});

it('shows an unselected marketing choice and the review offer immediately after the summary', function (): void {
    $audit = WebsiteAudit::factory()->create(['status' => WebsiteAudit::STATUS_COMPLETED, 'created_at' => now()->subMinute()]);
    $response = $this->get(route('marketing.website-audits.show', $audit))->assertSuccessful()
        ->assertSeeInOrder(['What we found.', 'Want to know what to fix first?', 'Get Ross’s recommendations'])
        ->assertSee('name="personal_review" value="1"', false)
        ->assertSee(WebsiteAudit::MARKETING_CONSENT_TEXT)
        ->assertSee('bg-black p-6 text-white', false)
        ->assertSee('src="'.asset('ross-topping.jpg').'"', false)
        ->assertSee('<span class="max-sm:hidden">Book a call with Ross</span>', false);
    expect(substr_count($response->getContent(), 'data-audit-review-portrait'))->toBe(2);
    expect(substr_count($response->getContent(), 'width="56" height="56"'))->toBe(2);
    $response->assertSeeInOrder(['id="audit-email-title"', 'data-audit-review-portrait', 'id="audit-email-description"'], false);
    expect($response->getContent())->toMatch('/name="marketing_consent" value="1"\s+class=/');
});

it('clearly confirms a pending personal review after the email request', function (): void {
    $audit = WebsiteAudit::factory()->create([
        'status' => WebsiteAudit::STATUS_COMPLETED,
        'created_at' => now()->subMinute(),
        'email' => 'owner@example.com',
        'report_requested_at' => now(),
        'personal_review_requested_at' => now(),
    ]);

    $this->get(route('marketing.website-audits.show', $audit))
        ->assertSuccessful()
        ->assertSee('Request received')
        ->assertSee('Ross has your request and will be in touch via email shortly.')
        ->assertSee('aria-labelledby="audit-request-received-title"', false)
        ->assertSee('bg-black p-6 text-white', false)
        ->assertDontSee('Want to know what to fix first?');
});
