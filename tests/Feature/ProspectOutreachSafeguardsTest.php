<?php

use App\Enums\ProspectAutomationStatus;
use App\Enums\ProspectLifecycleState;
use App\Enums\ProspectOutreachMessageType;
use App\Enums\ProspectSequenceStep;
use App\Jobs\EvaluateProspectOutreach;
use App\Jobs\SendScheduledProspectOutreach;
use App\Jobs\SendScheduledProspectPersonalisedVideo;
use App\Mail\ProspectOutreach;
use App\Models\Prospect;
use App\Services\ProspectLifecycleManager;
use App\Services\ProspectOutreachEligibility;
use App\Services\ProspectOutreachSender;
use App\Services\ProspectOutreachSequence;
use App\Services\ProspectPersonalisedVideo;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

function safeguardProspect(array $attributes = []): Prospect
{
    $prospect = Prospect::factory()->create(array_merge([
        'status' => 'contacted',
        'email' => 'hello@example.com',
        'outreach_subject' => 'Website opportunities',
        'outreach_body' => 'Here is the audit.',
        'approved_at' => now()->subWeek(),
        'sent_at' => now()->subDays(5),
        'next_follow_up_at' => now(),
    ], $attributes));
    $prospect->outreachState()->update([
        'lifecycle_state' => ProspectLifecycleState::InitialEmailSent,
        'automation_status' => ProspectAutomationStatus::Active,
        'sequence_step' => ProspectSequenceStep::InitialEmail,
        'initial_email_sent_at' => $prospect->sent_at,
        'next_action_at' => now(),
    ]);

    return $prospect->refresh();
}

it('never evaluates or sends to any protected lifecycle outcome', function (ProspectLifecycleState $lifecycleState): void {
    Mail::fake();
    $prospect = safeguardProspect();
    $prospect->outreachState()->update([
        'lifecycle_state' => $lifecycleState,
        'automation_status' => ProspectAutomationStatus::Stopped,
    ]);

    app(ProspectOutreachSequence::class)->evaluate($prospect);

    expect(fn () => app(ProspectOutreachSender::class)->sendAutomated(
        $prospect,
        ProspectOutreachMessageType::ColdFollowUp,
        'Following up',
        'Following up on the audit.',
        'protected-'.$lifecycleState->value,
    ))->toThrow(LogicException::class);
    expect($prospect->outreachDeliveries()->count())->toBe(0);
    Mail::assertNothingSent();
})->with([
    'replied' => ProspectLifecycleState::Replied,
    'not interested' => ProspectLifecycleState::NotInterested,
    'future opportunity' => ProspectLifecycleState::FutureOpportunity,
    'customer' => ProspectLifecycleState::Customer,
    'pilot' => ProspectLifecycleState::Pilot,
    'closed' => ProspectLifecycleState::Closed,
    'exhausted' => ProspectLifecycleState::Exhausted,
]);

it('cancels a scheduled initial email if the prospect replies before the job runs', function (): void {
    Mail::fake();
    $this->freezeTime();
    $scheduledFor = CarbonImmutable::now()->addHour()->startOfSecond();
    $prospect = safeguardProspect(['status' => 'approved', 'sent_at' => null, 'next_follow_up_at' => null, 'scheduled_send_at' => $scheduledFor]);
    $prospect->outreachState()->update(['lifecycle_state' => ProspectLifecycleState::Scheduled, 'sequence_step' => ProspectSequenceStep::AwaitingInitialEmail, 'next_action_at' => $scheduledFor]);
    app(ProspectLifecycleManager::class)->transitionManually($prospect, ProspectLifecycleState::Replied);

    (new SendScheduledProspectOutreach($prospect->id, $scheduledFor))->handle(app(ProspectOutreachSender::class));

    expect($prospect->outreachDeliveries()->count())->toBe(0)
        ->and($prospect->fresh()->scheduled_send_at)->toBeNull();
    Mail::assertNothingSent();
});

it('cancels a scheduled personalised video if a reply arrives before delivery', function (): void {
    Mail::fake();
    Queue::fake();
    $this->freezeTime();
    $prospect = safeguardProspect();
    $prospect->outreachState()->update([
        'lifecycle_state' => ProspectLifecycleState::NeedsPersonalisedVideo,
        'automation_status' => ProspectAutomationStatus::Paused,
        'sequence_step' => ProspectSequenceStep::AwaitingPersonalisedVideo,
        'next_action_at' => null,
    ]);
    $scheduledFor = CarbonImmutable::now()->addHour()->startOfSecond();
    $delivery = app(ProspectPersonalisedVideo::class)->schedule($prospect, 'https://video.example/walkthrough', 'Your video', 'Here is your video.', $scheduledFor, $prospect->owner);
    app(ProspectLifecycleManager::class)->transitionManually($prospect, ProspectLifecycleState::Replied);
    expect($prospect->outreachState->fresh()->lifecycle_state)->toBe(ProspectLifecycleState::Replied)
        ->and(app(ProspectOutreachEligibility::class)->manualMessageError($prospect))->not->toBeNull();

    (new SendScheduledProspectPersonalisedVideo($delivery->id, $scheduledFor))->handle(app(ProspectPersonalisedVideo::class));

    expect($delivery->fresh()->status)->toBe('cancelled')
        ->and($delivery->fresh()->scheduled_at)->toBeNull()
        ->and($prospect->activities()->where('type', 'personalised_video_cancelled')->exists())->toBeTrue();
    Mail::assertNothingSent();
});

it('sends an initial approved email only once when the send boundary is replayed', function (): void {
    Mail::fake();
    $prospect = Prospect::factory()->create([
        'email' => 'hello@example.com',
        'outreach_subject' => 'Website opportunities',
        'outreach_body' => 'Here is the audit.',
        'approved_at' => now(),
    ]);
    $sender = app(ProspectOutreachSender::class);

    $sender->send($prospect);
    expect(fn () => $sender->send($prospect))->toThrow(LogicException::class, 'not due');

    expect($prospect->outreachDeliveries()->count())->toBe(1);
    Mail::assertSent(ProspectOutreach::class, 1);
});

it('enforces the configured maximum before another cold follow up is sent', function (): void {
    Mail::fake();
    config()->set('outreach.maximum_follow_up_attempts', 1);
    $prospect = safeguardProspect();
    $prospect->outreachState()->update(['follow_up_attempts' => 1]);

    app(ProspectOutreachSequence::class)->evaluate($prospect);

    expect($prospect->outreachState->fresh()->lifecycle_state)->toBe(ProspectLifecycleState::Exhausted)
        ->and($prospect->outreachDeliveries()->count())->toBe(0);
    Mail::assertNothingSent();
});

it('uses stable unique job identities for overlapping evaluator and video dispatches', function (): void {
    $scheduledFor = CarbonImmutable::parse('2026-08-25 09:00:00 UTC');
    $evaluation = new EvaluateProspectOutreach(42);
    $sameEvaluation = new EvaluateProspectOutreach(42);
    $video = new SendScheduledProspectPersonalisedVideo(91, $scheduledFor);
    $sameVideo = new SendScheduledProspectPersonalisedVideo(91, $scheduledFor);

    expect($evaluation)->toBeInstanceOf(ShouldBeUnique::class)
        ->and($evaluation->uniqueId())->toBe($sameEvaluation->uniqueId())
        ->and($video)->toBeInstanceOf(ShouldBeUnique::class)
        ->and($video->uniqueId())->toBe($sameVideo->uniqueId());
});
