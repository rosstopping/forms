<?php

use App\Enums\ProspectAutomationStatus;
use App\Enums\ProspectEngagementEventType;
use App\Enums\ProspectLifecycleState;
use App\Enums\ProspectOutreachStopReason;
use App\Enums\ProspectSequenceStep;
use App\Models\Prospect;
use App\Models\ProspectEngagementEvent;
use App\Models\ProspectOutreachState;
use App\Models\User;
use App\Services\ProspectEngagementScorer;
use App\Services\ProspectLifecycleManager;
use App\Services\ProspectOutreachEligibility;
use Carbon\CarbonImmutable;

afterEach(fn () => CarbonImmutable::setTestNow());

it('creates an outreach state for every new prospect', function (): void {
    $prospect = Prospect::factory()->create();

    expect($prospect->outreachState)->toBeInstanceOf(ProspectOutreachState::class)
        ->and($prospect->outreachState->lifecycle_state)->toBe(ProspectLifecycleState::New)
        ->and($prospect->outreachState->automation_status)->toBe(ProspectAutomationStatus::Active)
        ->and($prospect->outreachState->engagement_score)->toBe(0);
});

it('initialises state from an existing prospect lifecycle', function (): void {
    $prospect = Prospect::factory()->create([
        'status' => 'replied', 'lead_temperature' => 'hot', 'replied_at' => now(), 'sent_at' => now()->subDay(),
    ]);

    expect($prospect->outreachState->lifecycle_state)->toBe(ProspectLifecycleState::Replied)
        ->and($prospect->outreachState->automation_status)->toBe(ProspectAutomationStatus::Stopped)
        ->and($prospect->outreachState->stop_reason)->toBe(ProspectOutreachStopReason::Replied)
        ->and($prospect->outreachState->engagement_score)->toBe(10);
});

it('backfills existing prospect state without inventing engagement', function (): void {
    $prospect = Prospect::factory()->create([
        'status' => 'contacted',
        'lead_temperature' => 'warm',
        'sent_at' => now()->subDay(),
        'next_follow_up_at' => now()->addDays(3),
    ]);
    $prospect->outreachState()->delete();

    $migration = require database_path('migrations/2026_08_24_213532_backfill_prospect_outreach_states.php');
    $migration->up();

    $outreachState = $prospect->outreachState()->firstOrFail();

    expect($outreachState->lifecycle_state)->toBe(ProspectLifecycleState::Warm)
        ->and($outreachState->engagement_score)->toBe(3)
        ->and($outreachState->initial_email_sent_at->equalTo($prospect->sent_at))->toBeTrue()
        ->and($outreachState->next_action_at->equalTo($prospect->next_follow_up_at))->toBeTrue();
});

it('awards bounded first and repeat engagement scores', function (): void {
    $prospect = Prospect::factory()->create(['status' => 'contacted', 'sent_at' => now()->subDay()]);
    $scorer = app(ProspectEngagementScorer::class);

    $scorer->record($prospect, ProspectEngagementEventType::EmailOpened, 'open:1');
    CarbonImmutable::setTestNow(now()->addMinutes(61));
    $scorer->record($prospect, ProspectEngagementEventType::EmailOpened, 'open:2');
    $thirdOpen = $scorer->record($prospect, ProspectEngagementEventType::EmailOpened, 'open:3');
    $scorer->record($prospect, ProspectEngagementEventType::AuditClicked, 'audit:1');
    CarbonImmutable::setTestNow(now()->addMinutes(61));
    $secondAuditClick = $scorer->record($prospect, ProspectEngagementEventType::AuditClicked, 'audit:2');
    $thirdAuditClick = $scorer->record($prospect, ProspectEngagementEventType::AuditClicked, 'audit:3');

    expect($thirdOpen->score_delta)->toBe(0)
        ->and($secondAuditClick->score_delta)->toBe(5)
        ->and($thirdAuditClick->score_delta)->toBe(0)
        ->and($prospect->outreachState->fresh()->engagement_score)->toBe(12)
        ->and($prospect->fresh()->lead_temperature)->toBe('hot')
        ->and($prospect->outreachState->fresh()->lifecycle_state)->toBe(ProspectLifecycleState::NeedsPersonalisedVideo)
        ->and($prospect->outreachState->automation_status)->toBe(ProspectAutomationStatus::Paused)
        ->and($prospect->outreachState->sequence_step)->toBe(ProspectSequenceStep::AwaitingPersonalisedVideo)
        ->and($prospect->outreachState->next_action_at)->toBeNull();
});

it('does not award a fingerprint twice', function (): void {
    $prospect = Prospect::factory()->create(['status' => 'contacted', 'sent_at' => now()->subDay()]);
    $scorer = app(ProspectEngagementScorer::class);

    $firstEvent = $scorer->record($prospect, ProspectEngagementEventType::AuditClicked, 'audit:duplicate');
    $duplicateEvent = $scorer->record($prospect, ProspectEngagementEventType::AuditClicked, 'audit:duplicate');

    expect($duplicateEvent->is($firstEvent))->toBeTrue()
        ->and($prospect->engagementEvents()->count())->toBe(1)
        ->and($prospect->outreachState->fresh()->engagement_score)->toBe(5);
});

it('records scanner activity without awarding intent', function (): void {
    $prospect = Prospect::factory()->create(['status' => 'contacted', 'sent_at' => now()->subDay()]);

    $event = app(ProspectEngagementScorer::class)->record(
        $prospect,
        ProspectEngagementEventType::BookingPageClicked,
        'scanner:booking:1',
        source: 'scanner',
    );

    expect($event->score_delta)->toBe(0)
        ->and($prospect->outreachState->fresh()->engagement_score)->toBe(0)
        ->and($prospect->fresh()->lead_temperature)->toBe('cold');
});

it('stops all outreach immediately when a reply is recorded', function (): void {
    $prospect = Prospect::factory()->create([
        'status' => 'contacted', 'approved_at' => now()->subDays(2), 'sent_at' => now()->subDay(),
        'scheduled_send_at' => now()->addDay(), 'next_follow_up_at' => now()->subMinute(),
        'outreach_subject' => 'Quick one', 'outreach_body' => 'Hello',
    ]);

    app(ProspectEngagementScorer::class)->record($prospect, ProspectEngagementEventType::ReplyReceived, 'reply:1');

    $prospect->refresh();
    $outreachState = $prospect->outreachState->fresh();

    expect($outreachState->lifecycle_state)->toBe(ProspectLifecycleState::Replied)
        ->and($outreachState->automation_status)->toBe(ProspectAutomationStatus::Stopped)
        ->and($outreachState->stop_reason)->toBe(ProspectOutreachStopReason::Replied)
        ->and($outreachState->next_action_at)->toBeNull()
        ->and($prospect->status)->toBe('replied')
        ->and($prospect->replied_at)->not->toBeNull()
        ->and($prospect->scheduled_send_at)->toBeNull()
        ->and($prospect->next_follow_up_at)->toBeNull()
        ->and(app(ProspectOutreachEligibility::class)->error($prospect))->toContain('stopped');
});

it('does not let automatic transitions overwrite protected manual states', function (): void {
    $prospect = Prospect::factory()->create();
    $lifecycleManager = app(ProspectLifecycleManager::class);

    $lifecycleManager->transitionManually($prospect, ProspectLifecycleState::Customer);
    $result = $lifecycleManager->transitionAutomatically($prospect, ProspectLifecycleState::Hot, 'Prospect became hot.');

    expect($result->lifecycle_state)->toBe(ProspectLifecycleState::Customer)
        ->and($result->automation_status)->toBe(ProspectAutomationStatus::Stopped)
        ->and($prospect->fresh()->status)->toBe('converted');
});

it('supports pausing resuming and manually stopping automation', function (): void {
    $prospect = Prospect::factory()->create([
        'status' => 'approved', 'approved_at' => now(), 'outreach_subject' => 'Quick one', 'outreach_body' => 'Hello',
    ]);
    $lifecycleManager = app(ProspectLifecycleManager::class);

    $pausedState = $lifecycleManager->pause($prospect);

    expect($pausedState->automation_status)->toBe(ProspectAutomationStatus::Paused)
        ->and(app(ProspectOutreachEligibility::class)->error($prospect))->toContain('paused');

    $resumedState = $lifecycleManager->resume($prospect);
    expect($resumedState->automation_status)->toBe(ProspectAutomationStatus::Active)
        ->and(app(ProspectOutreachEligibility::class)->error($prospect))->toBeNull();

    $stoppedState = $lifecycleManager->stop($prospect);
    expect($stoppedState->automation_status)->toBe(ProspectAutomationStatus::Stopped)
        ->and($stoppedState->stop_reason)->toBe(ProspectOutreachStopReason::Manual);
});

it('stores future opportunities as protected dated lifecycle states', function (): void {
    CarbonImmutable::setTestNow('2026-08-24 12:00:00');
    $prospect = Prospect::factory()->create(['scheduled_send_at' => now()->addDay(), 'next_follow_up_at' => now()->addDays(2)]);
    $followUpAt = now()->addMonths(3);

    $outreachState = app(ProspectLifecycleManager::class)->transitionManually(
        $prospect,
        ProspectLifecycleState::FutureOpportunity,
        futureOpportunityAt: $followUpAt,
    );

    expect($outreachState->future_opportunity_at->equalTo($followUpAt))->toBeTrue()
        ->and($outreachState->automation_status)->toBe(ProspectAutomationStatus::Stopped)
        ->and($prospect->fresh()->scheduled_send_at)->toBeNull()
        ->and($prospect->next_follow_up_at)->toBeNull();
});

it('supports manual score adjustments and temperature overrides', function (): void {
    $prospect = Prospect::factory()->create(['status' => 'contacted', 'sent_at' => now()->subDay()]);
    $scorer = app(ProspectEngagementScorer::class);
    $lifecycleManager = app(ProspectLifecycleManager::class);
    $actor = User::factory()->create();

    $scorer->adjust($prospect, 12, 'Strong offline conversation', $actor);
    $lifecycleManager->forceTemperature($prospect, 'warm', $actor);
    $scorer->record($prospect, ProspectEngagementEventType::BookingPageClicked, 'booking:override');

    expect($prospect->outreachState->fresh()->engagement_score)->toBe(32)
        ->and($prospect->fresh()->lead_temperature)->toBe('warm');

    $lifecycleManager->clearTemperatureOverride($prospect, $actor);
    $adjustment = $scorer->adjust($prospect, -100, 'Reset invalid engagement', $actor);

    expect($adjustment)->toBeInstanceOf(ProspectEngagementEvent::class)
        ->and($prospect->outreachState->fresh()->engagement_score)->toBe(0)
        ->and($prospect->fresh()->lead_temperature)->toBe('cold');
});

it('rejects invalid manual temperature overrides and protected resumes', function (): void {
    $prospect = Prospect::factory()->create();
    $lifecycleManager = app(ProspectLifecycleManager::class);

    expect(fn () => $lifecycleManager->forceTemperature($prospect, 'boiling'))->toThrow(InvalidArgumentException::class);

    $lifecycleManager->transitionManually($prospect, ProspectLifecycleState::NotInterested);

    expect(fn () => $lifecycleManager->resume($prospect))->toThrow(InvalidArgumentException::class);
});
