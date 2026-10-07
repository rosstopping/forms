<?php

use App\Mail\WebsiteAuditLeadReceived;
use App\Mail\WebsiteAuditPersonalReview;
use App\Mail\WebsiteAuditReport;
use App\Models\User;
use App\Models\WebsiteAudit;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

it('requests a personal review without enrolling the visitor in marketing', function (): void {
    Mail::fake();
    $this->travelTo('2026-10-09 12:00:00');
    $audit = WebsiteAudit::factory()->create(['status' => WebsiteAudit::STATUS_COMPLETED, 'created_at' => now()->subMinute()]);
    $this->post(route('marketing.website-audits.email-report', $audit), ['email' => ' OWNER@EXAMPLE.COM ', 'personal_review' => '1'])
        ->assertRedirect(route('marketing.website-audits.show', $audit).'#audit-follow-up')
        ->assertSessionHas('report_email_status', 'Thanks. Ross will email your video within one working day.')
        ->assertSessionHas('marketing.website_audit_review_ids.'.$audit->public_id, true);
    expect($audit->refresh()->personal_review_requested_at)->not->toBeNull()
        ->and($audit->personal_review_due_at->format('Y-m-d H:i'))->toBe('2026-10-12 12:00')
        ->and($audit->marketing_consent_at)->toBeNull()
        ->and($audit->marketing_consent_version)->toBeNull()
        ->and($audit->user_id)->toBeNull();
    Mail::assertQueued(WebsiteAuditReport::class);
    Mail::assertQueued(WebsiteAuditLeadReceived::class);
    (new WebsiteAuditReport($audit))->assertSeeInHtml('personal video within one working day');
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

it('saves the email before offering an optional one-click goal', function (string $goal): void {
    Mail::fake();
    $audit = WebsiteAudit::factory()->create(['status' => WebsiteAudit::STATUS_COMPLETED, 'created_at' => now()->subMinute()]);
    $this->post(route('marketing.website-audits.email-report', $audit), [
        'email' => 'owner@example.com',
        'personal_review' => 1,
    ])->assertRedirect(route('marketing.website-audits.show', $audit).'#audit-follow-up');

    expect($audit->refresh()->email)->toBe('owner@example.com')
        ->and($audit->customer_goal)->toBeNull()
        ->and($audit->report_requested_at)->not->toBeNull();
    Mail::assertQueuedCount(2);
    $leadDetails = $audit->only(['email', 'report_requested_at', 'expires_at', 'personal_review_requested_at', 'personal_review_due_at', 'marketing_consent_at', 'marketing_consent_version']);

    $response = $this->get(route('marketing.website-audits.show', $audit))->assertSuccessful()
        ->assertSeeInOrder(['Your video is next.', 'What would you like more of?', 'Enquiries', 'Bookings', 'Sales', 'Prefer to talk it through with Ross?'])
        ->assertDontSee('id="audit-email-dialog"', false)
        ->assertDontSee('Ready to put the plan into action?');
    $goalUrl = $response->viewData('goalUrl');
    expect($goalUrl)->toBeString();

    $this->patch($goalUrl, ['customer_goal' => $goal, 'marketing_consent' => 1, 'email' => 'changed@example.com'])
        ->assertRedirect(route('marketing.website-audits.show', $audit).'#audit-follow-up')
        ->assertSessionHas('audit_goal_status', 'Thanks. Ross will keep that in mind for your review.');
    expect($audit->refresh()->customer_goal)->toBe($goal)
        ->and($audit->only(array_keys($leadDetails)))->toEqual($leadDetails)
        ->and(User::query()->count())->toBe(0);
    Mail::assertQueuedCount(2);

    $response = $this->get(route('marketing.website-audits.show', $audit))->assertSuccessful()
        ->assertSee('Thanks. Ross will keep that in mind for your review.');
    $dom = new DOMDocument;
    @$dom->loadHTML($response->getContent());
    $xpath = new DOMXPath($dom);
    expect($xpath->query('//button[@name="customer_goal" and @value="'.$goal.'" and @aria-pressed="true"]')->length)->toBe(1);

    $this->patch($goalUrl, ['customer_goal' => $goal])->assertRedirect();
    Mail::assertQueuedCount(2);
})->with(['enquiries', 'bookings', 'sales']);

it('keeps a captured lead when the optional goal is invalid', function (mixed $goal): void {
    Mail::fake();
    $audit = WebsiteAudit::factory()->create(['status' => WebsiteAudit::STATUS_COMPLETED, 'created_at' => now()->subMinute()]);
    $this->post(route('marketing.website-audits.email-report', $audit), ['email' => 'owner@example.com', 'personal_review' => 1])->assertRedirect();
    $goalUrl = URL::temporarySignedRoute('marketing.website-audits.goal', now()->addDay(), $audit);

    $this->patch($goalUrl, ['customer_goal' => $goal])->assertRedirect()->assertSessionHasErrors('customer_goal');
    expect($audit->refresh()->email)->toBe('owner@example.com')
        ->and($audit->customer_goal)->toBeNull()
        ->and($audit->personal_review_requested_at)->not->toBeNull();
    Mail::assertQueuedCount(2);
})->with(['missing' => [null], 'empty' => [''], 'unknown' => ['traffic'], 'array' => [['enquiries']]]);

it('requires the capturing session and a valid unexpired signed goal link', function (string $scenario): void {
    Mail::fake();
    $audit = WebsiteAudit::factory()->create([
        'status' => $scenario === 'pending' ? WebsiteAudit::STATUS_PENDING : WebsiteAudit::STATUS_COMPLETED,
        'created_at' => now()->subMinute(),
        'email' => 'owner@example.com',
        'report_requested_at' => $scenario === 'not requested' ? null : now(),
        'expires_at' => $scenario === 'expired audit' ? now()->subMinute() : now()->addDay(),
    ]);
    $url = $scenario === 'unsigned'
        ? route('marketing.website-audits.goal', $audit)
        : URL::temporarySignedRoute('marketing.website-audits.goal', $scenario === 'expired signature' ? now()->subMinute() : now()->addDay(), $audit);
    $session = ['marketing.website_audit_id' => $audit->public_id];
    if ($scenario === 'different audit') {
        $other = WebsiteAudit::factory()->create();
        $session['marketing.website_audit_review_ids.'.$other->public_id] = true;
    } elseif ($scenario !== 'different session') {
        $session['marketing.website_audit_review_ids.'.$audit->public_id] = true;
    }

    $this->withSession($session)->patch($url, ['customer_goal' => 'sales'])->assertForbidden();
    expect($audit->refresh()->customer_goal)->toBeNull();
    Mail::assertNothingQueued();
})->with(['different session', 'different audit', 'unsigned', 'expired signature', 'expired audit', 'not requested', 'pending']);

it('does not grant goal editing by viewing or repeating someone else’s email request', function (): void {
    Mail::fake();
    $audit = WebsiteAudit::factory()->create([
        'status' => WebsiteAudit::STATUS_COMPLETED,
        'created_at' => now()->subMinute(),
        'email' => 'owner@example.com',
        'report_requested_at' => now(),
        'personal_review_requested_at' => now(),
    ]);
    $this->get(route('marketing.website-audits.show', $audit))->assertSuccessful()
        ->assertViewHas('goalUrl', null)->assertDontSee('name="customer_goal"', false);
    $this->post(route('marketing.website-audits.email-report', $audit), ['email' => 'owner@example.com', 'personal_review' => 1])
        ->assertRedirect()->assertSessionMissing('marketing.website_audit_review_ids.'.$audit->public_id);
    $url = URL::temporarySignedRoute('marketing.website-audits.goal', now()->addDay(), $audit);
    $this->patch($url, ['customer_goal' => 'sales'])->assertForbidden();
    Mail::assertNothingQueued();
});

it('keeps the optional goal off the private full report even in the capturing session', function (): void {
    $audit = WebsiteAudit::factory()->create(['status' => WebsiteAudit::STATUS_COMPLETED, 'created_at' => now()->subMinute(), 'report_requested_at' => now()]);
    $url = URL::temporarySignedRoute('marketing.website-audits.full', now()->addDay(), $audit);
    $this->withSession(['marketing.website_audit_review_ids.'.$audit->public_id => true])
        ->get($url)->assertSuccessful()->assertViewHas('goalUrl', null)->assertDontSee('name="customer_goal"', false);
});

it('acknowledges the review personally and invites a useful reply before selling the service', function (): void {
    $audit = WebsiteAudit::factory()->create(['email' => 'owner@example.com', 'personal_review_requested_at' => now()]);
    $mail = new WebsiteAuditReport($audit);
    $envelope = $mail->envelope();
    expect($envelope->from->name)->toBe('Ross at Sitewell')
        ->and($envelope->from->address)->toBe(config('mail.from.address'))
        ->and($envelope->replyTo[0]->address)->toBe(config('marketing.audit_notification_email'))
        ->and($envelope->subject)->toBe('Your growth plan request for '.$audit->domain);
    $mail->assertSeeInHtml($audit->domain)
        ->assertSeeInHtml('personal video within one working day')
        ->assertSeeInHtml('Is there a particular service or product you’d like more customers for?')
        ->assertSeeInHtml('Reply to this email and let me know.')
        ->assertSeeInHtml('Book a call with me')
        ->assertDontSeeInHtml('Want more customers to find you?')
        ->assertDontSeeInHtml('what it costs before deciding')
        ->assertDontSeeInHtml('/full');
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

it('lets an admin send a personal Loom video once and restores report access', function (): void {
    Mail::fake();
    $audit = WebsiteAudit::factory()->create([
        'status' => WebsiteAudit::STATUS_COMPLETED,
        'email' => 'owner@example.com',
        'personal_review_requested_at' => now()->subDays(3),
        'personal_review_due_at' => now()->subDays(2),
        'expires_at' => now()->subDay(),
    ]);
    $url = route('admin.onboarding.audits.review', $audit);
    $videoUrl = 'https://www.loom.com/share/abcdef123456?sid=share';
    $thumbnailUrl = 'https://cdn.loom.com/sessions/thumbnails/review.jpg';
    Http::fake(['www.loom.com/*' => Http::response('<meta property="og:image" content="'.$thumbnailUrl.'">')]);
    $this->post($url, ['loom_url' => $videoUrl])->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create())->post($url, ['loom_url' => $videoUrl])->assertForbidden();
    $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));
    $this->get(route('admin.onboarding.index'))->assertSuccessful()
        ->assertSee('name="loom_url"', false)->assertDontSee('name="priorities[', false);
    foreach (['', 'https://example.com/video', 'http://www.loom.com/share/abcdef', 'https://www.loom.com.evil.test/share/abcdef'] as $invalidUrl) {
        $this->post($url, ['loom_url' => $invalidUrl])->assertSessionHasErrors('loom_url');
    }
    $this->post($url, ['loom_url' => $videoUrl])->assertRedirect();
    $this->post($url, ['loom_url' => $videoUrl])->assertRedirect();
    expect($audit->refresh()->personal_review)->toBe(['loom_url' => $videoUrl, 'thumbnail_url' => $thumbnailUrl])
        ->and($audit->personal_review_queued_at)->not->toBeNull()
        ->and($audit->hasExpired())->toBeFalse();
    Mail::assertQueuedCount(1);
    Mail::assertQueued(WebsiteAuditPersonalReview::class, fn ($mail): bool => $mail->hasTo('owner@example.com'));
    (new WebsiteAuditPersonalReview($audit))->assertSeeInHtml($videoUrl)
        ->assertSeeInHtml('Watch your website review')->assertSeeInHtml('enquiries, bookings or sales')
        ->assertSeeInHtml($thumbnailUrl)->assertSeeInHtml('Want me to take care of this?');
    Http::assertSentCount(1);
    expect((new WebsiteAuditPersonalReview($audit))->envelope()->replyTo[0]->address)->toBe(config('marketing.audit_notification_email'));
});

it('does not send unsolicited personal reviews', function (): void {
    Mail::fake();
    Http::fake();
    $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));
    $audit = WebsiteAudit::factory()->create(['status' => WebsiteAudit::STATUS_COMPLETED, 'email' => 'owner@example.com']);
    $videoUrl = 'https://www.loom.com/share/abcdef';
    $this->post(route('admin.onboarding.audits.review', $audit), ['loom_url' => $videoUrl])->assertUnprocessable();
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
        ->assertSeeInOrder(['Health score today', 'Things to fix', 'Your six-month opportunity', 'First: fix the website.', 'Next: grow the visitors.', 'See how we’d get you there.', 'Get my free growth plan'])
        ->assertSee('name="personal_review" value="1"', false)
        ->assertSee(WebsiteAudit::MARKETING_CONSENT_TEXT)
        ->assertSee('bg-black p-6 text-white', false)
        ->assertSee('src="'.asset('ross-topping.jpg').'"', false)
        ->assertSee('Where should I send your video?')
        ->assertSee('Send me my free growth plan')
        ->assertDontSee('name="customer_goal"', false);
    $dom = new DOMDocument;
    @$dom->loadHTML($response->getContent());
    $xpath = new DOMXPath($dom);
    expect($xpath->query('//*[@data-audit-review-copy]//h2')->length)->toBe(1)
        ->and($xpath->query('//*[@data-audit-review-copy]//p')->length)->toBe(1)
        ->and($xpath->query('//*[@data-audit-review-copy]//button[@data-audit-email-open]')->length)->toBe(1);
    expect(substr_count($response->getContent(), 'data-audit-review-portrait'))->toBe(2);
    expect(substr_count($response->getContent(), 'width="56" height="56"'))->toBe(1);
    expect($xpath->query('//dialog//img[@width="72" and @height="72" and contains(@class, "size-18")]')->length)->toBe(1);
    expect($xpath->query('//dialog//*[@data-audit-review-portrait and contains(@class, "-translate-y-1/2")]')->length)->toBe(1);
    expect($xpath->query('//dialog[contains(@class, "pt-10")]')->length)->toBe(1);
    $response->assertSeeInOrder(['id="audit-email-dialog"', 'data-audit-review-portrait', 'id="audit-email-title"', 'id="audit-email-description"'], false);
    expect($xpath->query('//input[@name="marketing_consent" and @checked]')->length)->toBe(0)
        ->and($xpath->query('//label[@for="audit-marketing-consent"]')->length)->toBe(1);
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
        ->assertSee('Your video is next.')
        ->assertSee('Ross will review '.$audit->domain.' and email your video within one working day.')
        ->assertSee('aria-labelledby="audit-request-received-title"', false)
        ->assertSee('bg-black p-6 text-white', false)
        ->assertDontSee('See how we’d get you there.');
});

it('sends the review with a watch link when Loom cannot supply a thumbnail', function (): void {
    Mail::fake();
    Http::fake(['www.loom.com/*' => Http::response('', 503)]);
    $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));
    $audit = WebsiteAudit::factory()->create([
        'status' => WebsiteAudit::STATUS_COMPLETED,
        'email' => 'owner@example.com',
        'personal_review_requested_at' => now(),
    ]);
    $videoUrl = 'https://www.loom.com/share/abcdef';
    $this->post(route('admin.onboarding.audits.review', $audit), ['loom_url' => $videoUrl])->assertRedirect();
    expect($audit->refresh()->personal_review['thumbnail_url'])->toBeNull();
    Mail::assertQueued(WebsiteAuditPersonalReview::class);
    (new WebsiteAuditPersonalReview($audit))->assertSeeInHtml('Watch your website review')->assertSeeInHtml($videoUrl)
        ->assertDontSeeInHtml('cdn.loom.com');
});
