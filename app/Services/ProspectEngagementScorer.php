<?php

namespace App\Services;

use App\Enums\ProspectAutomationStatus;
use App\Enums\ProspectEngagementEventType;
use App\Enums\ProspectLifecycleState;
use App\Enums\ProspectOutreachStopReason;
use App\Enums\ProspectSequenceStep;
use App\Models\Prospect;
use App\Models\ProspectEngagementEvent;
use App\Models\ProspectOutreachState;
use App\Models\User;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

class ProspectEngagementScorer
{
    public function __construct(
        private ProspectLifecycleManager $lifecycleManager,
        private ProspectManualFollowUpAdvisor $manualFollowUpAdvisor,
    ) {}

    /** @param array<string, mixed> $metadata */
    public function record(
        Prospect $prospect,
        ProspectEngagementEventType $eventType,
        string $fingerprint,
        array $metadata = [],
        string $source = 'tracking',
        ?DateTimeInterface $occurredAt = null,
        ?int $deliveryId = null,
        ?int $linkId = null,
    ): ProspectEngagementEvent {
        return DB::transaction(function () use ($prospect, $eventType, $fingerprint, $metadata, $source, $occurredAt, $deliveryId, $linkId): ProspectEngagementEvent {
            $outreachState = $this->lockedState($prospect);
            $existingEvent = ProspectEngagementEvent::query()->where('fingerprint', $fingerprint)->first();

            if ($existingEvent) {
                if (! $existingEvent->prospect->is($prospect)) {
                    throw new LogicException('The engagement fingerprint already belongs to another prospect.');
                }

                return $existingEvent;
            }

            $scoreDelta = $this->scoreDelta($prospect, $eventType, $source, $occurredAt ?? now());
            $event = $prospect->engagementEvents()->create([
                'prospect_outreach_delivery_id' => $deliveryId,
                'prospect_outreach_link_id' => $linkId,
                'event_type' => $eventType,
                'source' => $source,
                'fingerprint' => $fingerprint,
                'score_delta' => $scoreDelta,
                'metadata' => $metadata,
                'occurred_at' => $occurredAt ?? now(),
            ]);
            $this->applyEvent($prospect, $outreachState, $event);

            return $event;
        });
    }

    public function adjust(Prospect $prospect, int $requestedDelta, string $reason, ?User $actor = null): ProspectEngagementEvent
    {
        return DB::transaction(function () use ($prospect, $requestedDelta, $reason, $actor): ProspectEngagementEvent {
            $outreachState = $this->lockedState($prospect);
            $scoreDelta = max(-$outreachState->engagement_score, $requestedDelta);
            $event = $prospect->engagementEvents()->create([
                'event_type' => ProspectEngagementEventType::ManualAdjustment,
                'source' => 'manual',
                'fingerprint' => 'manual:'.Str::uuid(),
                'score_delta' => $scoreDelta,
                'metadata' => ['reason' => $reason, 'requested_delta' => $requestedDelta, 'user_id' => $actor?->id],
                'occurred_at' => now(),
            ]);
            $this->applyEvent($prospect, $outreachState, $event, $actor);

            return $event;
        });
    }

    private function lockedState(Prospect $prospect): ProspectOutreachState
    {
        $this->lifecycleManager->stateFor($prospect);

        return ProspectOutreachState::query()->whereBelongsTo($prospect)->lockForUpdate()->firstOrFail();
    }

    private function scoreDelta(Prospect $prospect, ProspectEngagementEventType $eventType, string $source, DateTimeInterface $occurredAt): int
    {
        if (in_array($source, config('outreach.ignored_engagement_sources', []), true)) {
            return 0;
        }

        $rule = config('outreach.scoring.'.$eventType->value);

        if (! is_array($rule)) {
            return 0;
        }

        $awardCount = $prospect->engagementEvents()
            ->where('event_type', $eventType->value)
            ->where('score_delta', '>', 0)
            ->count();

        if ($awardCount >= (int) ($rule['max_awards'] ?? 1)) {
            return 0;
        }

        if ($awardCount > 0) {
            $lastAwardedAt = $prospect->engagementEvents()
                ->where('event_type', $eventType->value)
                ->where('score_delta', '>', 0)
                ->latest('occurred_at')
                ->value('occurred_at');
            $repeatAwardAfterMinutes = (int) ($rule['repeat_award_after_minutes'] ?? 0);

            if ($lastAwardedAt && CarbonImmutable::parse($lastAwardedAt)->addMinutes($repeatAwardAfterMinutes)->isAfter($occurredAt)) {
                return 0;
            }
        }

        return (int) ($awardCount === 0 ? ($rule['first'] ?? 0) : ($rule['repeat'] ?? 0));
    }

    private function applyEvent(Prospect $prospect, ProspectOutreachState $outreachState, ProspectEngagementEvent $event, ?User $actor = null): void
    {
        $previousScore = $outreachState->engagement_score;
        $newScore = max(0, $previousScore + $event->score_delta);
        $previousTemperature = $prospect->lead_temperature;
        $temperature = $event->event_type !== ProspectEngagementEventType::ReplyReceived && $outreachState->lifecycle_state->stopsNormalOutreach()
            ? $previousTemperature
            : ($outreachState->temperature_override ?: $this->temperatureForScore($newScore));
        $attributes = [
            'engagement_score' => $newScore,
            'last_engagement_at' => $event->occurred_at,
        ];

        if ($event->event_type === ProspectEngagementEventType::ReplyReceived) {
            $attributes = array_merge($attributes, [
                'lifecycle_state' => ProspectLifecycleState::Replied,
                'automation_status' => ProspectAutomationStatus::Stopped,
                'next_action_at' => null,
                'stopped_at' => $event->occurred_at,
                'stop_reason' => ProspectOutreachStopReason::Replied,
            ]);
            $prospect->update([
                'status' => 'replied',
                'lead_temperature' => $temperature,
                'replied_at' => $event->occurred_at,
                'scheduled_send_at' => null,
                'next_follow_up_at' => null,
            ]);
        } else {
            if (! $outreachState->lifecycle_state->stopsNormalOutreach() && $outreachState->temperature_override === null) {
                $isNewlyHot = $temperature === 'hot' && $previousTemperature !== 'hot';
                $attributes['lifecycle_state'] = $this->engagementLifecycleState($outreachState, $temperature);

                if ($isNewlyHot) {
                    $attributes['automation_status'] = ProspectAutomationStatus::Paused;
                    $attributes['sequence_step'] = ProspectSequenceStep::AwaitingPersonalisedVideo;
                    $attributes['next_action_at'] = null;
                    $prospect->update(['next_follow_up_at' => null]);
                }
            }

            $prospect->update(['lead_temperature' => $temperature]);
        }

        $outreachState->update($attributes);

        if ($event->score_delta !== 0) {
            $prospect->recordActivity('engagement_score_changed', 'Engagement score changed from '.$previousScore.' to '.$newScore.'.', $actor);
        }

        if ($temperature !== $previousTemperature) {
            $prospect->recordActivity('lead_temperature_changed', 'Prospect became a '.$temperature.' lead.', $actor);
        }

        if ($temperature === 'hot' && $previousTemperature !== 'hot') {
            $prospect->recordActivity('personalised_video_requested', 'Added to the personalised video queue after reaching the hot engagement threshold.', $actor);
        }

        if ($event->event_type === ProspectEngagementEventType::ReplyReceived) {
            $prospect->recordActivity('reply_detected', 'Reply received; automated outreach stopped.', $actor);
        }

        $updatedState = $outreachState->fresh();

        if ($event->score_delta > 0
            && $updatedState->post_video_follow_up_sent_at !== null
            && in_array($event->event_type, [
                ProspectEngagementEventType::AuditClicked,
                ProspectEngagementEventType::SitewellClicked,
                ProspectEngagementEventType::PersonalisedVideoClicked,
                ProspectEngagementEventType::BookingPageClicked,
            ], true)) {
            $reasons = $this->manualFollowUpAdvisor->reasonsFor($prospect);

            if ($reasons !== []) {
                $this->lifecycleManager->recommendManualFollowUp($prospect, $reasons);
            }
        }
    }

    private function temperatureForScore(int $score): string
    {
        if ($score >= (int) config('outreach.temperature_thresholds.hot', 10)) {
            return 'hot';
        }

        return $score >= (int) config('outreach.temperature_thresholds.warm', 3) ? 'warm' : 'cold';
    }

    private function engagementLifecycleState(ProspectOutreachState $outreachState, string $temperature): ProspectLifecycleState
    {
        if (in_array($outreachState->lifecycle_state, [
            ProspectLifecycleState::NeedsPersonalisedVideo,
            ProspectLifecycleState::VideoSent,
            ProspectLifecycleState::HighlyEngaged,
        ], true)) {
            return $outreachState->lifecycle_state;
        }

        return match ($temperature) {
            'hot' => ProspectLifecycleState::NeedsPersonalisedVideo,
            'warm' => ProspectLifecycleState::Warm,
            default => ProspectLifecycleState::Cold,
        };
    }
}
