<?php

use App\Enums\ProspectAutomationStatus;
use App\Enums\ProspectEngagementEventType;
use App\Enums\ProspectLifecycleState;
use App\Enums\ProspectSequenceStep;
use App\Models\Prospect;
use App\Models\User;
use App\Services\ProspectEngagementScorer;
use App\Services\ProspectOutreachSender;
use App\Services\ProspectOutreachSequence;
use Illuminate\Support\Facades\Mail;

function prospectAwaitingPostVideoFollowUp(?User $admin = null): Prospect
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
        'showcase_video_url' => 'https://video.example/acme',
        'approved_at' => now()->subWeeks(2),
        'sent_at' => now()->subWeek(),
        'next_follow_up_at' => now(),
    ]);
    $prospect->outreachState()->update([
        'lifecycle_state' => ProspectLifecycleState::VideoSent,
        'engagement_score' => 12,
        'automation_status' => ProspectAutomationStatus::Active,
        'sequence_step' => ProspectSequenceStep::PersonalisedVideo,
        'follow_up_attempts' => 2,
        'initial_email_sent_at' => now()->subWeek(),
        'video_sent_at' => now()->subDays(3),
        'video_sent_engagement_score' => 12,
        'next_action_at' => now(),
    ]);

    return $prospect->refresh();
}

it('clears legacy post video follow ups without sending an email', function (): void {
    Mail::fake();
    $prospect = prospectAwaitingPostVideoFollowUp();
    $sequence = app(ProspectOutreachSequence::class);

    $sequence->evaluate($prospect);
    $sequence->evaluate($prospect);

    $state = $prospect->outreachState->fresh();
    expect($prospect->outreachDeliveries()->count())->toBe(0)
        ->and($state->lifecycle_state)->toBe(ProspectLifecycleState::VideoSent)
        ->and($state->post_video_follow_up_sent_at)->toBeNull()
        ->and($state->next_action_at)->toBeNull()
        ->and($prospect->fresh()->next_follow_up_at)->toBeNull();
    Mail::assertNothingSent();
});

it('blocks post video follow ups at the sender too', function (): void {
    Mail::fake();
    $prospect = prospectAwaitingPostVideoFollowUp();

    expect(fn () => app(ProspectOutreachSender::class)->sendPostVideoFollowUp($prospect, 'Follow up', 'Message'))
        ->toThrow(LogicException::class, 'Automatic post-video follow-up emails are disabled.');
    expect($prospect->outreachDeliveries()->count())->toBe(0);
    Mail::assertNothingSent();
});

it('does not send after a reply or when follow ups are disabled', function (bool $reply, bool $enabled): void {
    Mail::fake();
    config()->set('outreach.automatic_follow_ups_enabled', $enabled);
    $prospect = prospectAwaitingPostVideoFollowUp();

    if ($reply) {
        app(ProspectEngagementScorer::class)->record($prospect, ProspectEngagementEventType::ReplyReceived, 'post-video-reply');
    }

    app(ProspectOutreachSequence::class)->evaluate($prospect);

    expect($prospect->outreachDeliveries()->count())->toBe(0);
    Mail::assertNothingSent();
})->with([
    'reply stops delivery' => [true, true],
    'automation disabled' => [false, false],
]);

it('recommends manual follow up immediately after strong post video engagement', function (): void {
    Mail::fake();
    $this->freezeTime();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $prospect = prospectAwaitingPostVideoFollowUp($admin);
    app(ProspectEngagementScorer::class)->record(
        $prospect,
        ProspectEngagementEventType::PersonalisedVideoClicked,
        'video-view-after-send',
        ['link_label' => 'Personalised video'],
    );

    expect($prospect->outreachState->fresh()->manual_follow_up_required_at)->not->toBeNull();

    app(ProspectOutreachSequence::class)->evaluate($prospect);

    $state = $prospect->outreachState->fresh();
    expect($state->lifecycle_state)->toBe(ProspectLifecycleState::HighlyEngaged)
        ->and($state->automation_status)->toBe(ProspectAutomationStatus::Paused)
        ->and($state->sequence_step)->toBe(ProspectSequenceStep::PersonalisedVideo)
        ->and($state->manual_follow_up_required_at)->not->toBeNull()
        ->and(data_get($state->manual_follow_up_reason, '0.event_type'))->toBe('personalised_video_clicked')
        ->and(data_get($state->manual_follow_up_reason, '0.label'))->toBe('Video viewed 1 time')
        ->and(data_get($state->manual_follow_up_reason, '0.count'))->toBe(1)
        ->and($prospect->activities()->where('type', 'manual_follow_up_recommended')->count())->toBe(1);
    Mail::assertNothingSent();

    $this->actingAs($admin)->get(route('admin.prospects.index', ['tab' => 'replies']))
        ->assertSuccessful()
        ->assertSee('Manual Follow-up Recommended')
        ->assertSee('Video viewed 1 time')
        ->assertSee('No more automatic email will be sent.');
});

it('surfaces later strong engagement but ignores weak opens after the video', function (): void {
    Mail::fake();
    $prospect = prospectAwaitingPostVideoFollowUp();
    app(ProspectOutreachSequence::class)->evaluate($prospect);
    $scorer = app(ProspectEngagementScorer::class);

    $scorer->record($prospect, ProspectEngagementEventType::EmailOpened, 'post-video-open');
    expect($prospect->outreachState->fresh()->manual_follow_up_required_at)->toBeNull();

    $scorer->record($prospect, ProspectEngagementEventType::AuditClicked, 'post-video-audit', ['link_label' => 'Website audit']);
    $state = $prospect->outreachState->fresh();

    expect($state->lifecycle_state)->toBe(ProspectLifecycleState::HighlyEngaged)
        ->and(data_get($state->manual_follow_up_reason, '0.event_type'))->toBe('audit_clicked')
        ->and(data_get($state->manual_follow_up_reason, '0.label'))->toBe('Audit viewed 1 time')
        ->and(data_get($state->manual_follow_up_reason, '0.count'))->toBe(1);

    $this->travel(61)->minutes();
    $scorer->record($prospect, ProspectEngagementEventType::AuditClicked, 'post-video-audit-repeat', ['link_label' => 'Website audit']);
    $reasons = $prospect->outreachState->fresh()->manual_follow_up_reason;
    expect(data_get($reasons, '0.label'))->toBe('Audit viewed 2 times')
        ->and(data_get($reasons, '0.count'))->toBe(2)
        ->and($prospect->activities()->where('type', 'manual_follow_up_recommended')->count())->toBe(1);
    Mail::assertNothingSent();
});

it('does not recommend manual follow up without post video buying intent', function (): void {
    Mail::fake();
    $prospect = prospectAwaitingPostVideoFollowUp();

    app(ProspectOutreachSequence::class)->evaluate($prospect);

    $state = $prospect->outreachState->fresh();
    expect($state->manual_follow_up_required_at)->toBeNull()
        ->and($state->manual_follow_up_reason)->toBeNull()
        ->and($state->automation_status)->toBe(ProspectAutomationStatus::Active);
    Mail::assertNothingSent();
});
