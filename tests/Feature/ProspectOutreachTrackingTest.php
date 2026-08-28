<?php

use App\Enums\ProspectEngagementEventType;
use App\Enums\ProspectLifecycleState;
use App\Enums\ProspectOutreachMessageType;
use App\Mail\ProspectOutreach;
use App\Models\Prospect;
use App\Models\ProspectOutreachDelivery;
use App\Models\ProspectOutreachLink;
use App\Models\User;
use App\Notifications\ProspectBecameHot;
use App\Services\ProspectOutreachTracker;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

afterEach(fn () => CarbonImmutable::setTestNow());

it('adds signed open tracking without click links to a live initial email', function (): void {
    Mail::fake();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $prospect = Prospect::factory()->for($admin, 'owner')->create([
        'outreach_subject' => 'Quick one',
        'outreach_body' => 'Hi there.',
        'website_url' => 'https://example.com',
        'analysed_at' => now(),
        'showcase_video_url' => 'https://video.example.com/prospect',
        'approved_at' => now(),
    ]);

    $this->actingAs($admin)->post(route('admin.prospects.send', $prospect))->assertRedirect();

    $delivery = $prospect->outreachDeliveries()->with('links')->sole();
    expect($delivery->sent_at)->not->toBeNull()
        ->and($delivery->recipient_email)->toBe($prospect->email)
        ->and($delivery->links)->toBeEmpty();

    Mail::assertSent(ProspectOutreach::class, function (ProspectOutreach $mail) use ($delivery): bool {
        $mail->assertSeeInHtml('/outreach/open/'.$delivery->uuid)
            ->assertDontSeeInHtml('/outreach/click/')
            ->assertDontSeeInHtml('View your website audit')
            ->assertSeeInHtml('signature=');

        return $mail->delivery->is($delivery);
    });
});

it('records opens as weak bounded intent without overstating temperature', function (): void {
    CarbonImmutable::setTestNow('2026-08-24 09:00:00');
    $prospect = Prospect::factory()->create(['lead_temperature' => 'cold']);
    $delivery = ProspectOutreachDelivery::factory()->for($prospect)->create();
    $trackingUrl = URL::signedRoute('prospect-outreach-opens.show', $delivery);

    $this->get($trackingUrl)
        ->assertSuccessful()
        ->assertHeader('content-type', 'image/gif');
    CarbonImmutable::setTestNow(now()->addMinutes(61));
    $this->get($trackingUrl)->assertSuccessful();

    $delivery->refresh();
    expect($delivery->first_opened_at)->not->toBeNull()
        ->and($delivery->last_opened_at)->not->toBeNull()
        ->and($delivery->open_count)->toBe(2)
        ->and($prospect->refresh()->lead_temperature)->toBe('cold')
        ->and($prospect->outreachState->fresh()->engagement_score)->toBe(2)
        ->and($prospect->engagementEvents()->where('event_type', ProspectEngagementEventType::EmailOpened)->count())->toBe(2)
        ->and($prospect->activities()->where('type', 'email_opened')->count())->toBe(1);
});

it('records each tracked link click and marks the lead as hot', function (): void {
    Notification::fake();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $prospect = Prospect::factory()->for($admin, 'owner')->create(['lead_temperature' => 'cold']);
    $delivery = ProspectOutreachDelivery::factory()->for($prospect)->create();
    $link = ProspectOutreachLink::factory()->for($delivery, 'delivery')->create([
        'label' => 'Website video',
        'kind' => 'showcase_video',
        'destination_url' => 'https://video.example.com/prospect',
    ]);
    $trackingUrl = URL::signedRoute('prospect-outreach-links.show', $link);

    $this->get($trackingUrl)->assertRedirect('https://video.example.com/prospect');
    $this->get($trackingUrl)->assertRedirect('https://video.example.com/prospect');

    expect($link->refresh()->click_count)->toBe(2)
        ->and($link->first_clicked_at)->not->toBeNull()
        ->and($delivery->refresh()->click_count)->toBe(2)
        ->and($delivery->first_clicked_at)->not->toBeNull()
        ->and($prospect->refresh()->lead_temperature)->toBe('hot')
        ->and($prospect->outreachState->fresh()->engagement_score)->toBe(10)
        ->and($prospect->engagementEvents()->where('event_type', ProspectEngagementEventType::PersonalisedVideoClicked)->count())->toBe(2)
        ->and($prospect->activities()->where('type', 'email_clicked')->count())->toBe(1);
    Notification::assertSentTo($admin, ProspectBecameHot::class, function (ProspectBecameHot $notification) use ($admin, $prospect): bool {
        $message = $notification->toMail($admin);

        return $notification->prospect->is($prospect)
            && $notification->clickedLink === 'Website video'
            && $message->subject === 'Hot prospect: '.$prospect->business_name
            && $message->actionUrl === route('admin.prospects.show', $prospect);
    });
    Notification::assertSentToTimes($admin, ProspectBecameHot::class, 1);
});

it('does not notify administrators again when an already hot prospect clicks', function (): void {
    Notification::fake();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $prospect = Prospect::factory()->for($admin, 'owner')->create(['lead_temperature' => 'hot']);
    $delivery = ProspectOutreachDelivery::factory()->for($prospect)->create();
    $link = ProspectOutreachLink::factory()->for($delivery, 'delivery')->create();

    $this->get(URL::signedRoute('prospect-outreach-links.show', $link))->assertRedirect();

    Notification::assertNothingSent();
});

it('does not cool a hot lead when another open is recorded', function (): void {
    $prospect = Prospect::factory()->create(['lead_temperature' => 'hot']);
    $delivery = ProspectOutreachDelivery::factory()->for($prospect)->create();

    $this->get(URL::signedRoute('prospect-outreach-opens.show', $delivery))->assertSuccessful();

    expect($prospect->refresh()->lead_temperature)->toBe('hot');
});

it('scores an attributable audit click and only awards a meaningful revisit later', function (): void {
    CarbonImmutable::setTestNow('2026-08-24 09:00:00');
    Notification::fake();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $prospect = Prospect::factory()->for($admin, 'owner')->create([
        'website_url' => 'https://example.com',
        'analysed_at' => now(),
    ]);
    $delivery = app(ProspectOutreachTracker::class)->createDelivery($prospect, ProspectOutreachMessageType::ColdFollowUp);
    $auditLink = $delivery->links->firstWhere('kind', 'website_audit');
    $trackingUrl = URL::signedRoute('prospect-outreach-links.show', $auditLink);

    $this->get($trackingUrl)->assertRedirect($auditLink->destination_url);
    $this->get($auditLink->destination_url)->assertSuccessful();

    expect($prospect->outreachState->fresh()->engagement_score)->toBe(5)
        ->and($prospect->fresh()->lead_temperature)->toBe('warm')
        ->and($prospect->engagementEvents()->pluck('score_delta')->sort()->values()->all())->toBe([0, 5]);
    Notification::assertNothingSent();

    CarbonImmutable::setTestNow(now()->addMinutes(61));
    $this->get($auditLink->destination_url)->assertSuccessful();

    expect($prospect->outreachState->fresh()->engagement_score)->toBe(10)
        ->and($prospect->fresh()->lead_temperature)->toBe('hot')
        ->and($prospect->engagementEvents()->where('event_type', ProspectEngagementEventType::AuditClicked)->count())->toBe(3);
    Notification::assertSentTo($admin, ProspectBecameHot::class, fn (ProspectBecameHot $notification): bool => $notification->clickedLink === 'Website audit revisit');
});

it('records known scanner clicks with zero score and does not block a later genuine click', function (): void {
    Notification::fake();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $prospect = Prospect::factory()->for($admin, 'owner')->create(['lead_temperature' => 'cold']);
    $delivery = ProspectOutreachDelivery::factory()->for($prospect)->create();
    $link = ProspectOutreachLink::factory()->for($delivery, 'delivery')->create();
    $trackingUrl = URL::signedRoute('prospect-outreach-links.show', $link);

    $this->withHeader('User-Agent', 'Proofpoint URL Defense')->get($trackingUrl)->assertRedirect();

    $scannerEvent = $prospect->engagementEvents()->sole();
    expect($scannerEvent->source)->toBe('scanner')
        ->and($scannerEvent->score_delta)->toBe(0)
        ->and($scannerEvent->metadata)->not->toHaveKey('user_agent')
        ->and($prospect->outreachState->fresh()->engagement_score)->toBe(0)
        ->and($prospect->fresh()->lead_temperature)->toBe('cold');
    Notification::assertNothingSent();

    $this->withHeader('User-Agent', 'Mozilla/5.0')->get($trackingUrl)->assertRedirect();

    expect($prospect->engagementEvents()->count())->toBe(2)
        ->and($prospect->outreachState->fresh()->engagement_score)->toBe(20)
        ->and($prospect->fresh()->lead_temperature)->toBe('hot');
    Notification::assertSentToTimes($admin, ProspectBecameHot::class, 1);
});

it('keeps protected lifecycle outcomes unchanged when an old tracked link is visited', function (): void {
    Notification::fake();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $prospect = Prospect::factory()->for($admin, 'owner')->create([
        'status' => 'converted',
        'lead_temperature' => 'cold',
        'converted_at' => now(),
    ]);
    $delivery = ProspectOutreachDelivery::factory()->for($prospect)->create();
    $link = ProspectOutreachLink::factory()->for($delivery, 'delivery')->create();

    $this->get(URL::signedRoute('prospect-outreach-links.show', $link))->assertRedirect();

    expect($prospect->outreachState->fresh()->lifecycle_state)->toBe(ProspectLifecycleState::Customer)
        ->and($prospect->outreachState->engagement_score)->toBe(20)
        ->and($prospect->fresh()->lead_temperature)->toBe('cold');
    Notification::assertNothingSent();
});

it('rejects unsigned tracking requests without recording engagement', function (): void {
    $prospect = Prospect::factory()->create(['lead_temperature' => 'cold']);
    $delivery = ProspectOutreachDelivery::factory()->for($prospect)->create();
    $link = ProspectOutreachLink::factory()->for($delivery, 'delivery')->create();

    $this->get(route('prospect-outreach-opens.show', $delivery))->assertForbidden();
    $this->get(route('prospect-outreach-links.show', $link))->assertForbidden();

    expect($delivery->refresh()->open_count)->toBe(0)
        ->and($delivery->click_count)->toBe(0)
        ->and($link->refresh()->click_count)->toBe(0)
        ->and($prospect->refresh()->lead_temperature)->toBe('cold');
});

it('does not add tracking or create a delivery for administrator test emails', function (): void {
    Mail::fake();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $prospect = Prospect::factory()->for($admin, 'owner')->create([
        'outreach_subject' => 'Quick one',
        'outreach_body' => 'Hi there.',
    ]);

    $this->actingAs($admin)->post(route('admin.prospects.test-email', $prospect))->assertRedirect();

    expect($prospect->outreachDeliveries()->count())->toBe(0);
    Mail::assertSent(ProspectOutreach::class, function (ProspectOutreach $mail): bool {
        $mail->assertDontSeeInHtml('/outreach/open/')
            ->assertDontSeeInHtml('/outreach/click/');

        return $mail->delivery === null;
    });
});

it('shows email engagement timing and clicked destinations on the outreach lead', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $prospect = Prospect::factory()->for($admin, 'owner')->create(['lead_temperature' => 'hot']);
    $delivery = ProspectOutreachDelivery::factory()->for($prospect)->create([
        'first_opened_at' => '2026-08-18 09:15:00',
        'last_opened_at' => '2026-08-18 10:30:00',
        'open_count' => 2,
        'first_clicked_at' => '2026-08-18 09:20:00',
        'last_clicked_at' => '2026-08-18 09:20:00',
        'click_count' => 1,
    ]);
    ProspectOutreachLink::factory()->for($delivery, 'delivery')->create([
        'label' => 'Website video',
        'first_clicked_at' => '2026-08-18 09:20:00',
        'last_clicked_at' => '2026-08-18 09:20:00',
        'click_count' => 1,
    ]);

    $this->actingAs($admin)->get(route('admin.prospects.show', $prospect))
        ->assertSuccessful()
        ->assertSee('Hot lead')
        ->assertSee('Email engagement')
        ->assertSee('18 Aug 2026, 09:15')
        ->assertSee('18 Aug 2026, 09:20')
        ->assertSee('Website video');
});

it('shows hot leads first and supports filtering by engagement', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    Prospect::factory()->for($admin, 'owner')->create(['business_name' => 'Cold Prospect', 'lead_temperature' => 'cold']);
    Prospect::factory()->for($admin, 'owner')->create(['business_name' => 'Warm Prospect', 'lead_temperature' => 'warm']);
    Prospect::factory()->for($admin, 'owner')->create(['business_name' => 'Hot Prospect', 'lead_temperature' => 'hot']);

    $this->actingAs($admin)->get(route('admin.prospects.index'))
        ->assertSuccessful()
        ->assertSee('Hot leads')
        ->assertSeeInOrder(['Hot Prospect', 'Warm Prospect', 'Cold Prospect']);

    $this->get(route('admin.prospects.index', ['temperature' => 'hot']))
        ->assertSuccessful()
        ->assertSee('Hot Prospect')
        ->assertDontSee('Warm Prospect')
        ->assertDontSee('Cold Prospect');
});

it('filters outreach leads by whether they have an email address', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    Prospect::factory()->for($admin, 'owner')->create(['business_name' => 'Missing Email Ltd', 'email' => null]);
    Prospect::factory()->for($admin, 'owner')->create(['business_name' => 'Has Email Ltd', 'email' => 'hello@example.com']);

    $this->actingAs($admin)->get(route('admin.prospects.index', ['email_status' => 'missing']))
        ->assertSuccessful()
        ->assertSee('Without email address')
        ->assertSee('Missing Email Ltd')
        ->assertDontSee('Has Email Ltd');

    $this->get(route('admin.prospects.index', ['email_status' => 'present']))
        ->assertSuccessful()
        ->assertSee('Has Email Ltd')
        ->assertDontSee('Missing Email Ltd');
});
