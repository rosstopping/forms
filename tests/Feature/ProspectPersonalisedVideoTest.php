<?php

use App\Enums\ProspectAutomationStatus;
use App\Enums\ProspectEngagementEventType;
use App\Enums\ProspectLifecycleState;
use App\Enums\ProspectOutreachMessageType;
use App\Enums\ProspectSequenceStep;
use App\Jobs\SendScheduledProspectPersonalisedVideo;
use App\Mail\ProspectOutreach;
use App\Models\Prospect;
use App\Models\User;
use App\Services\ProspectEngagementScorer;
use App\Services\ProspectPersonalisedVideo;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

function prospectNeedingVideo(?User $admin = null): Prospect
{
    $admin ??= User::factory()->create(['role' => User::ROLE_ADMIN]);
    $prospect = Prospect::factory()->for($admin, 'owner')->create([
        'business_name' => 'Acme Heating',
        'contact_name' => 'Alex',
        'email' => 'alex@acme.example',
        'status' => 'contacted',
        'lead_temperature' => 'hot',
        'outreach_subject' => 'Website opportunities for Acme Heating',
        'outreach_body' => 'Initial approved message.',
        'approved_at' => now()->subWeek(),
        'sent_at' => now()->subDays(2),
    ]);
    $prospect->outreachState()->update([
        'lifecycle_state' => ProspectLifecycleState::NeedsPersonalisedVideo,
        'engagement_score' => 12,
        'automation_status' => ProspectAutomationStatus::Paused,
        'sequence_step' => ProspectSequenceStep::AwaitingPersonalisedVideo,
        'initial_email_sent_at' => $prospect->sent_at,
        'next_action_at' => null,
    ]);

    return $prospect->refresh();
}

it('moves a newly hot prospect into the manual video queue and pauses cold automation', function (): void {
    $prospect = Prospect::factory()->create([
        'status' => 'contacted',
        'approved_at' => now()->subWeek(),
        'sent_at' => now()->subDays(4),
        'next_follow_up_at' => now(),
    ]);

    app(ProspectEngagementScorer::class)->record($prospect, ProspectEngagementEventType::PersonalisedVideoClicked, 'video-queue-threshold');

    $state = $prospect->outreachState->fresh();
    expect($state->lifecycle_state)->toBe(ProspectLifecycleState::NeedsPersonalisedVideo)
        ->and($state->automation_status)->toBe(ProspectAutomationStatus::Paused)
        ->and($state->sequence_step)->toBe(ProspectSequenceStep::AwaitingPersonalisedVideo)
        ->and($state->next_action_at)->toBeNull()
        ->and($prospect->fresh()->next_follow_up_at)->toBeNull()
        ->and($prospect->activities()->where('type', 'personalised_video_requested')->exists())->toBeTrue();
});

it('surfaces hot prospects in the personalised video queue with their reasons', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $prospect = prospectNeedingVideo($admin);
    app(ProspectEngagementScorer::class)->adjust($prospect, 5, 'Audit revisited', $admin);

    $this->actingAs($admin)->get(route('admin.prospects.index', ['tab' => 'hot']))
        ->assertSuccessful()
        ->assertSee('Needs Personalised Video')
        ->assertSee('Acme Heating')
        ->assertSee('Score 17')
        ->assertSee('Manual score adjustment')
        ->assertSee('Add Personalised Video');
});

it('sends a manually supplied personalised video and records its lifecycle', function (): void {
    Mail::fake();
    $this->freezeTime();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $prospect = prospectNeedingVideo($admin);

    $this->actingAs($admin)->post(route('admin.prospects.personalised-video', $prospect), [
        'video_url' => 'https://video.example/acme-walkthrough',
        'subject' => 'Your Acme Heating walkthrough',
        'body' => 'Hi Alex, here is the video I recorded for you.',
        'action' => 'send_now',
    ])->assertRedirect()->assertSessionHas('status', 'Personalised video sent. Engagement tracking is active.');

    $delivery = $prospect->outreachDeliveries()->with('links')->sole();
    $state = $prospect->outreachState->fresh();
    expect($delivery->message_type)->toBe(ProspectOutreachMessageType::PersonalisedVideo)
        ->and($delivery->status)->toBe('sent')
        ->and($delivery->subject)->toBe('Your Acme Heating walkthrough')
        ->and($delivery->body)->toContain('here is the video')
        ->and($delivery->links->firstWhere('kind', 'showcase_video')->destination_url)->toBe('https://video.example/acme-walkthrough')
        ->and($state->lifecycle_state)->toBe(ProspectLifecycleState::VideoSent)
        ->and($state->automation_status)->toBe(ProspectAutomationStatus::Active)
        ->and($state->sequence_step)->toBe(ProspectSequenceStep::PersonalisedVideo)
        ->and($state->video_sent_at)->not->toBeNull()
        ->and($state->next_action_at)->toBeNull()
        ->and($prospect->fresh()->next_follow_up_at)->toBeNull();
    Mail::assertSent(ProspectOutreach::class, fn (ProspectOutreach $mail): bool => $mail->hasTo('alex@acme.example'));
});

it('schedules a personalised video and ignores a stale scheduled job after rescheduling', function (): void {
    Queue::fake();
    Mail::fake();
    CarbonImmutable::setTestNow('2026-08-24 10:00:00 UTC');
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $prospect = prospectNeedingVideo($admin);

    $this->actingAs($admin)->post(route('admin.prospects.personalised-video', $prospect), [
        'video_url' => 'https://video.example/acme',
        'subject' => 'Your walkthrough',
        'body' => 'Here is your walkthrough.',
        'action' => 'schedule',
        'scheduled_send_at' => '2026-08-25T11:00',
    ])->assertRedirect();

    $firstSchedule = CarbonImmutable::parse('2026-08-25 11:00', 'Europe/London')->utc();
    $delivery = $prospect->outreachDeliveries()->sole();
    expect($delivery->status)->toBe('scheduled')
        ->and($delivery->scheduled_at->equalTo($firstSchedule))->toBeTrue();
    Queue::assertPushed(SendScheduledProspectPersonalisedVideo::class, fn (SendScheduledProspectPersonalisedVideo $job): bool => $job->deliveryId === $delivery->id && $job->scheduledFor->equalTo($firstSchedule));

    $secondSchedule = $firstSchedule->addHour();
    app(ProspectPersonalisedVideo::class)->schedule($prospect, 'https://video.example/acme', 'Updated subject', 'Updated body.', $secondSchedule, $admin);
    (new SendScheduledProspectPersonalisedVideo($delivery->id, $firstSchedule))->handle(app(ProspectPersonalisedVideo::class));

    $delivery->refresh();
    expect($delivery->status)->toBe('scheduled')
        ->and($delivery->scheduled_at->equalTo($secondSchedule))->toBeTrue();
    Mail::assertNothingSent();

    (new SendScheduledProspectPersonalisedVideo($delivery->id, $secondSchedule))->handle(app(ProspectPersonalisedVideo::class));
    expect($delivery->fresh()->status)->toBe('sent')
        ->and($prospect->outreachState->fresh()->lifecycle_state)->toBe(ProspectLifecycleState::VideoSent);
    Mail::assertSent(ProspectOutreach::class, 1);
});

it('validates scheduling details and blocks non administrators', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $prospect = prospectNeedingVideo($admin);

    $this->actingAs($admin)->post(route('admin.prospects.personalised-video', $prospect), [
        'video_url' => 'not-a-url', 'subject' => '', 'body' => '', 'action' => 'schedule',
    ])->assertSessionHasErrors(['video_url', 'subject', 'body', 'scheduled_send_at']);

    $this->actingAs(User::factory()->create())->post(route('admin.prospects.personalised-video', $prospect), [
        'video_url' => 'https://video.example/acme', 'subject' => 'Video', 'body' => 'Message', 'action' => 'send_now',
    ])->assertForbidden();
});

it('returns unapproved video sends to the form with the reason and edited content', function (string $action): void {
    Mail::fake();
    Queue::fake();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $prospect = prospectNeedingVideo($admin);
    $prospect->update(['approved_at' => null, 'approved_by' => null]);
    $formUrl = route('admin.prospects.show', $prospect);
    $message = 'The initial outreach must remain approved before sending a personalised video.';
    $data = [
        'video_url' => 'https://video.example/acme',
        'subject' => 'My edited video subject',
        'body' => 'My edited video message.',
        'action' => $action,
        'scheduled_send_at' => now('Europe/London')->addDay()->format('Y-m-d\TH:i'),
    ];

    $this->actingAs($admin)->from($formUrl)
        ->post(route('admin.prospects.personalised-video', $prospect), $data)
        ->assertRedirect($formUrl)
        ->assertSessionHasErrors(['action' => $message])
        ->assertSessionHasInput('video_url', $data['video_url'])
        ->assertSessionHasInput('subject', $data['subject'])
        ->assertSessionHasInput('body', $data['body']);

    $this->withCookie(config('session.cookie'), session()->getId())
        ->get($formUrl)->assertSuccessful()
        ->assertSee($message)
        ->assertSee($data['subject'])
        ->assertSee($data['body']);

    expect($prospect->outreachDeliveries()->count())->toBe(0)
        ->and($prospect->outreachState->fresh()->video_sent_at)->toBeNull();
    Mail::assertNothingSent();
    Queue::assertNothingPushed();
})->with(['send_now', 'schedule']);

it('never sends a second personalised video through the initial video action', function (): void {
    Mail::fake();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $prospect = prospectNeedingVideo($admin);
    $service = app(ProspectPersonalisedVideo::class);
    $service->sendNow($prospect, 'https://video.example/first', 'Video', 'First message.', $admin);

    expect(fn () => $service->sendNow($prospect, 'https://video.example/second', 'Video two', 'Second message.', $admin))
        ->toThrow(LogicException::class, 'already been sent');
    expect($prospect->outreachDeliveries()->count())->toBe(1);
    Mail::assertSent(ProspectOutreach::class, 1);
});

it('saves a video draft and reloads it without reserving or sending outreach', function (): void {
    Mail::fake();
    Queue::fake();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $prospect = prospectNeedingVideo($admin);
    $originalState = $prospect->outreachState->getAttributes();
    $originalProspect = $prospect->getAttributes();

    $this->actingAs($admin)->post(route('admin.prospects.personalised-video', $prospect), [
        'video_url' => 'https://video.example/draft',
        'subject' => 'Saved video subject',
        'body' => 'Saved video message for Alex.',
        'action' => 'save_draft',
        'scheduled_send_at' => '2020-01-01T12:00',
    ])->assertRedirect()->assertSessionHas('status', 'Personalised video draft saved.');

    $prospect->refresh();
    expect($prospect->outreachState->personalised_video_draft)->toMatchArray([
        'video_url' => 'https://video.example/draft',
        'subject' => 'Saved video subject',
        'body' => 'Saved video message for Alex.',
    ])->and($prospect->getAttributes())->toBe($originalProspect)
        ->and(collect($prospect->outreachState->getAttributes())->except(['personalised_video_draft', 'updated_at'])->all())
        ->toBe(collect($originalState)->except(['personalised_video_draft', 'updated_at'])->all())
        ->and($prospect->outreachDeliveries()->count())->toBe(0);
    Mail::assertNothingSent();
    Queue::assertNothingPushed();

    $this->get(route('admin.prospects.show', $prospect))->assertSuccessful()
        ->assertSee('Saved video subject')->assertSee('Saved video message for Alex.')
        ->assertSee('https://video.example/draft')->assertSee('Save video draft')
        ->assertSee('Save &amp; send me a test', false)->assertSee($admin->email);
});

it('saves the current video message and sends an untracked test only to the signed in admin', function (): void {
    Mail::fake();
    Queue::fake();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $prospect = prospectNeedingVideo($admin);
    app(ProspectPersonalisedVideo::class)->saveDraft($prospect, 'https://video.example/old', 'Old subject', 'Old body', $admin);

    $this->actingAs($admin)->post(route('admin.prospects.personalised-video', $prospect), [
        'video_url' => 'https://video.example/revised',
        'subject' => 'Revised video subject',
        'body' => 'The current video email.',
        'action' => 'send_test',
        'email' => $prospect->email,
        'scheduled_send_at' => '2020-01-01T12:00',
    ])->assertRedirect()->assertSessionHas('status', 'Video draft saved. Test email sent to '.$admin->email.'.');

    Mail::assertSent(ProspectOutreach::class, function (ProspectOutreach $mail) use ($admin, $prospect): bool {
        return $mail->hasTo($admin->email) && ! $mail->hasTo($prospect->email)
            && $mail->delivery === null
            && $mail->previewMessageType === ProspectOutreachMessageType::PersonalisedVideo
            && $mail->envelope()->subject === 'Revised video subject'
            && $mail->prospect->outreach_body === 'The current video email.'
            && $mail->prospect->showcase_video_url === 'https://video.example/revised';
    });
    Mail::assertSentCount(1);
    Queue::assertNothingPushed();
    $prospect->refresh();
    expect($prospect->outreach_subject)->toBe('Website opportunities for Acme Heating')
        ->and($prospect->outreach_body)->toBe('Initial approved message.')
        ->and($prospect->outreachState->personalised_video_draft['subject'])->toBe('Revised video subject')
        ->and($prospect->outreachState->video_sent_at)->toBeNull()
        ->and($prospect->outreachState->lifecycle_state)->toBe(ProspectLifecycleState::NeedsPersonalisedVideo)
        ->and($prospect->outreachState->automation_status)->toBe(ProspectAutomationStatus::Paused)
        ->and($prospect->outreachState->next_action_at)->toBeNull()
        ->and($prospect->outreachDeliveries()->count())->toBe(0)
        ->and($prospect->engagementEvents()->count())->toBe(0)
        ->and($prospect->activities()->where('type', 'personalised_video_test_sent')->exists())->toBeTrue();
});

it('renders the video test with direct links and the same video layout without engagement tracking', function (): void {
    $prospect = prospectNeedingVideo();
    $prospect->forceFill([
        'website_url' => 'https://acme.example',
        'analysed_at' => now(),
        'showcase_video_url' => 'https://video.example/preview',
        'showcase_video_thumbnail_url' => 'https://cdn.loom.com/sessions/thumbnails/preview.jpg',
        'outreach_subject' => 'Video preview',
        'outreach_body' => 'This is the video message, not the initial email.',
    ]);
    $mail = new ProspectOutreach($prospect, previewMessageType: ProspectOutreachMessageType::PersonalisedVideo);
    $mail->assertSeeInHtml('This is the video message, not the initial email.')
        ->assertSeeInHtml('Watch your video')
        ->assertSeeInHtml('https://video.example/preview')
        ->assertSeeInHtml('https://cdn.loom.com/sessions/thumbnails/preview.jpg')
        ->assertSeeInHtml('Your website audit')
        ->assertSeeInHtml('Book a call with Ross')
        ->assertSeeInHtml('01302 248 374')
        ->assertSeeInHtml('href="tel:+441302248374"', false)
        ->assertSeeInOrderInHtml(['Watch your video', 'Book a call with Ross', 'Your website audit']);
    $content = $mail->content()->with;
    expect($content['trackingOpenUrl'])->toBeNull()
        ->and($content['showcaseVideoUrl'])->toBe('https://video.example/preview')
        ->and($content['auditReportUrl'])->not->toContain('outreach_link')
        ->and($content['bookingUrl'])->toBe('https://cal.com/ross');
});

it('leaves an existing scheduled video unchanged when saving and testing newer copy', function (): void {
    Mail::fake();
    Queue::fake();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $prospect = prospectNeedingVideo($admin);
    $service = app(ProspectPersonalisedVideo::class);
    $delivery = $service->schedule($prospect, 'https://video.example/scheduled', 'Scheduled subject', 'Scheduled body', CarbonImmutable::now()->addDay(), $admin);
    $snapshot = $delivery->getAttributes();
    $state = $prospect->outreachState()->first()->getAttributes();
    Queue::fake();

    $this->actingAs($admin)->post(route('admin.prospects.personalised-video', $prospect), [
        'video_url' => 'https://video.example/draft-change', 'subject' => 'New subject',
        'body' => 'New body', 'action' => 'send_test',
    ])->assertRedirect();

    expect($delivery->fresh()->getAttributes())->toBe($snapshot)
        ->and($prospect->fresh()->showcase_video_url)->toBe('https://video.example/scheduled')
        ->and(collect($prospect->outreachState()->first()->getAttributes())->except(['personalised_video_draft', 'updated_at'])->all())
        ->toBe(collect($state)->except(['personalised_video_draft', 'updated_at'])->all());
    Queue::assertNothingPushed();
    $this->get(route('admin.prospects.show', $prospect))->assertSuccessful()
        ->assertSee('leaves this scheduled email unchanged')
        ->assertSee('New subject');
});

it('validates video drafts and tests and restricts them to administrators', function (string $action): void {
    Mail::fake();
    Queue::fake();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $prospect = prospectNeedingVideo($admin);
    $this->actingAs($admin)->post(route('admin.prospects.personalised-video', $prospect), [
        'action' => $action, 'video_url' => 'javascript:alert(1)', 'subject' => '', 'body' => '',
    ])->assertSessionHasErrors(['video_url', 'subject', 'body']);
    $this->actingAs(User::factory()->create())->post(route('admin.prospects.personalised-video', $prospect), [
        'action' => $action, 'video_url' => 'https://video.example/test', 'subject' => 'Test', 'body' => 'Test message',
    ])->assertForbidden();
    expect($prospect->outreachState->fresh()->personalised_video_draft)->toBeNull();
    Mail::assertNothingSent();
    Queue::assertNothingPushed();
})->with(['save_draft', 'send_test']);
