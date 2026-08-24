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

    $this->actingAs($admin)->get(route('admin.prospects.index'))
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
        ->and($state->next_action_at->equalTo(now()->addDays(3)->startOfSecond()))->toBeTrue();
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
