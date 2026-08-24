<?php

namespace App\Services;

use App\Enums\ProspectAutomationStatus;
use App\Enums\ProspectLifecycleState;
use App\Enums\ProspectOutreachMessageType;
use App\Enums\ProspectOutreachStopReason;
use App\Enums\ProspectSequenceStep;
use App\Models\Prospect;
use App\Models\ProspectOutreachState;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ProspectLifecycleManager
{
    public function stateFor(Prospect $prospect): ProspectOutreachState
    {
        return $prospect->outreachState()->firstOrCreate([], ProspectOutreachState::initialAttributesFor($prospect));
    }

    public function transitionAutomatically(Prospect $prospect, ProspectLifecycleState $lifecycleState, string $description): ProspectOutreachState
    {
        return DB::transaction(function () use ($prospect, $lifecycleState, $description): ProspectOutreachState {
            $outreachState = $this->lockedState($prospect);

            if ($outreachState->lifecycle_state->stopsNormalOutreach() || $outreachState->lifecycle_state === $lifecycleState) {
                return $outreachState;
            }

            $outreachState->update(['lifecycle_state' => $lifecycleState]);
            $prospect->recordActivity('lifecycle_changed', $description);

            return $outreachState->refresh();
        });
    }

    public function transitionManually(Prospect $prospect, ProspectLifecycleState $lifecycleState, ?User $actor = null, ?DateTimeInterface $futureOpportunityAt = null): ProspectOutreachState
    {
        return DB::transaction(function () use ($prospect, $lifecycleState, $actor, $futureOpportunityAt): ProspectOutreachState {
            $outreachState = $this->lockedState($prospect);
            $stopsOutreach = $lifecycleState->stopsNormalOutreach();
            $stopReason = $this->stopReasonFor($lifecycleState);
            $attributes = [
                'lifecycle_state' => $lifecycleState,
                'automation_status' => $stopsOutreach ? ProspectAutomationStatus::Stopped : $outreachState->automation_status,
                'next_action_at' => $stopsOutreach ? null : $outreachState->next_action_at,
                'future_opportunity_at' => $lifecycleState === ProspectLifecycleState::FutureOpportunity ? $futureOpportunityAt : null,
                'stopped_at' => $stopsOutreach ? now() : $outreachState->stopped_at,
                'stop_reason' => $stopsOutreach ? $stopReason : $outreachState->stop_reason,
            ];

            $outreachState->update($attributes);
            $prospect->update($this->legacyProspectAttributes($lifecycleState));
            $prospect->recordActivity('status_manually_changed', 'Lifecycle manually changed to '.str($lifecycleState->value)->replace('_', ' ')->title().'.', $actor);

            return $outreachState->refresh();
        });
    }

    public function pause(Prospect $prospect, ?User $actor = null, string $description = 'Outreach automation paused manually.'): ProspectOutreachState
    {
        return $this->setAutomationStatus($prospect, ProspectAutomationStatus::Paused, $description, $actor);
    }

    public function resume(Prospect $prospect, ?User $actor = null): ProspectOutreachState
    {
        return DB::transaction(function () use ($prospect, $actor): ProspectOutreachState {
            $outreachState = $this->lockedState($prospect);

            if ($outreachState->lifecycle_state->stopsNormalOutreach()) {
                throw new InvalidArgumentException('A prospect in a stopped lifecycle state cannot resume normal outreach.');
            }

            $outreachState->update([
                'automation_status' => ProspectAutomationStatus::Active,
                'stopped_at' => null,
                'stop_reason' => null,
            ]);
            $prospect->recordActivity('automation_resumed', 'Outreach automation resumed manually.', $actor);

            return $outreachState->refresh();
        });
    }

    public function stop(Prospect $prospect, ?User $actor = null, ProspectOutreachStopReason $reason = ProspectOutreachStopReason::Manual): ProspectOutreachState
    {
        return DB::transaction(function () use ($prospect, $actor, $reason): ProspectOutreachState {
            $outreachState = $this->lockedState($prospect);
            $outreachState->update([
                'automation_status' => ProspectAutomationStatus::Stopped,
                'next_action_at' => null,
                'stopped_at' => now(),
                'stop_reason' => $reason,
            ]);
            $prospect->update(['scheduled_send_at' => null, 'next_follow_up_at' => null]);
            $prospect->recordActivity('outreach_stopped', 'Outreach stopped: '.str($reason->value)->replace('_', ' ')->headline().'.', $actor);

            return $outreachState->refresh();
        });
    }

    public function forceTemperature(Prospect $prospect, string $temperature, ?User $actor = null): ProspectOutreachState
    {
        if (! in_array($temperature, Prospect::LEAD_TEMPERATURES, true)) {
            throw new InvalidArgumentException('The lead temperature must be cold, warm, or hot.');
        }

        return DB::transaction(function () use ($prospect, $temperature, $actor): ProspectOutreachState {
            $outreachState = $this->lockedState($prospect);
            $outreachState->update(['temperature_override' => $temperature]);
            $prospect->update(['lead_temperature' => $temperature]);
            $prospect->recordActivity('temperature_manually_changed', 'Lead temperature manually changed to '.str($temperature)->title().'.', $actor);

            return $outreachState->refresh();
        });
    }

    public function clearTemperatureOverride(Prospect $prospect, ?User $actor = null): ProspectOutreachState
    {
        return DB::transaction(function () use ($prospect, $actor): ProspectOutreachState {
            $outreachState = $this->lockedState($prospect);
            $temperature = $this->temperatureForScore($outreachState->engagement_score);
            $outreachState->update(['temperature_override' => null]);
            $prospect->update(['lead_temperature' => $temperature]);
            $prospect->recordActivity('temperature_override_cleared', 'Manual lead-temperature override cleared.', $actor);

            return $outreachState->refresh();
        });
    }

    public function markQualified(Prospect $prospect): ProspectOutreachState
    {
        return $this->synchroniseLifecycle($prospect, ProspectLifecycleState::Qualified);
    }

    public function markApproved(Prospect $prospect, ?User $actor = null): ProspectOutreachState
    {
        return $this->synchroniseLifecycle($prospect, ProspectLifecycleState::Approved);
    }

    public function markScheduled(Prospect $prospect): ProspectOutreachState
    {
        return DB::transaction(function () use ($prospect): ProspectOutreachState {
            $outreachState = $this->lockedState($prospect);

            if (! $outreachState->lifecycle_state->stopsNormalOutreach()) {
                $outreachState->update([
                    'lifecycle_state' => ProspectLifecycleState::Scheduled,
                    'next_action_at' => $prospect->scheduled_send_at,
                ]);
            }

            return $outreachState->refresh();
        });
    }

    public function markMessageSent(Prospect $prospect, ProspectOutreachMessageType $messageType, DateTimeInterface $sentAt): ProspectOutreachState
    {
        return DB::transaction(function () use ($prospect, $messageType, $sentAt): ProspectOutreachState {
            $outreachState = $this->lockedState($prospect);
            $isInitialEmail = $outreachState->initial_email_sent_at === null;
            $nextActionAt = match ($messageType) {
                ProspectOutreachMessageType::Initial => now()->addDays((int) config('outreach.timing.cold_retry_days', 4)),
                ProspectOutreachMessageType::ColdFollowUp => now()->addDays((int) config('outreach.timing.final_follow_up_days', 6)),
                default => $outreachState->next_action_at,
            };
            $outreachState->update([
                'lifecycle_state' => $isInitialEmail ? ProspectLifecycleState::InitialEmailSent : $outreachState->lifecycle_state,
                'sequence_step' => $isInitialEmail ? ProspectSequenceStep::InitialEmail : $outreachState->sequence_step,
                'initial_email_sent_at' => $isInitialEmail ? $sentAt : $outreachState->initial_email_sent_at,
                'last_outreach_at' => $sentAt,
                'next_action_at' => $nextActionAt,
            ]);
            $prospect->update(['next_follow_up_at' => $nextActionAt]);

            return $outreachState->refresh();
        });
    }

    public function markPersonalisedVideoQueued(Prospect $prospect, ?User $actor = null): ProspectOutreachState
    {
        return DB::transaction(function () use ($prospect, $actor): ProspectOutreachState {
            $outreachState = $this->lockedState($prospect);

            if ($outreachState->lifecycle_state->stopsNormalOutreach()) {
                throw new InvalidArgumentException('A personalised video cannot be queued for a stopped prospect.');
            }

            $outreachState->update([
                'lifecycle_state' => ProspectLifecycleState::NeedsPersonalisedVideo,
                'automation_status' => ProspectAutomationStatus::Paused,
                'sequence_step' => ProspectSequenceStep::AwaitingPersonalisedVideo,
                'next_action_at' => null,
            ]);
            $prospect->update(['next_follow_up_at' => null]);
            $prospect->recordActivity('personalised_video_prepared', 'Personalised video message prepared; automated cold outreach remains paused.', $actor);

            return $outreachState->refresh();
        });
    }

    public function markPersonalisedVideoSent(Prospect $prospect, DateTimeInterface $sentAt, ?User $actor = null): ProspectOutreachState
    {
        return DB::transaction(function () use ($prospect, $sentAt, $actor): ProspectOutreachState {
            $outreachState = $this->lockedState($prospect);
            $nextActionAt = now()->addDays((int) config('outreach.timing.post_video_follow_up_days', 3));
            $outreachState->update([
                'lifecycle_state' => ProspectLifecycleState::VideoSent,
                'automation_status' => ProspectAutomationStatus::Active,
                'sequence_step' => ProspectSequenceStep::PersonalisedVideo,
                'video_sent_at' => $sentAt,
                'video_sent_engagement_score' => $outreachState->engagement_score,
                'last_outreach_at' => $sentAt,
                'next_action_at' => $nextActionAt,
            ]);
            $prospect->update(['next_follow_up_at' => $nextActionAt]);
            $prospect->recordActivity('personalised_video_sent', 'Personalised video email sent; engagement tracking continues.', $actor);

            return $outreachState->refresh();
        });
    }

    /** @param list<array{event_type: string, label: string, count: int, last_at: string}> $reasons */
    public function recommendManualFollowUp(Prospect $prospect, array $reasons): ProspectOutreachState
    {
        return DB::transaction(function () use ($prospect, $reasons): ProspectOutreachState {
            $outreachState = $this->lockedState($prospect);

            if ($outreachState->lifecycle_state->stopsNormalOutreach()) {
                return $outreachState;
            }

            $isNewRecommendation = $outreachState->manual_follow_up_required_at === null;
            $attributes = ['manual_follow_up_reason' => $reasons];

            if ($isNewRecommendation) {
                $attributes = array_merge($attributes, [
                    'lifecycle_state' => ProspectLifecycleState::HighlyEngaged,
                    'automation_status' => ProspectAutomationStatus::Paused,
                    'next_action_at' => null,
                    'manual_follow_up_required_at' => now(),
                ]);
            }

            $outreachState->update($attributes);
            $prospect->update(['next_follow_up_at' => null]);

            if ($isNewRecommendation) {
                $prospect->recordActivity('manual_follow_up_recommended', 'Manual follow-up recommended because engagement continued after the personalised video.');
            }

            return $outreachState->refresh();
        });
    }

    private function setAutomationStatus(Prospect $prospect, ProspectAutomationStatus $status, string $description, ?User $actor): ProspectOutreachState
    {
        return DB::transaction(function () use ($prospect, $status, $description, $actor): ProspectOutreachState {
            $outreachState = $this->lockedState($prospect);
            $outreachState->update(['automation_status' => $status]);
            $prospect->recordActivity('automation_'.$status->value, $description, $actor);

            return $outreachState->refresh();
        });
    }

    private function synchroniseLifecycle(Prospect $prospect, ProspectLifecycleState $lifecycleState): ProspectOutreachState
    {
        return DB::transaction(function () use ($prospect, $lifecycleState): ProspectOutreachState {
            $outreachState = $this->lockedState($prospect);

            if (! $outreachState->lifecycle_state->stopsNormalOutreach()) {
                $outreachState->update(['lifecycle_state' => $lifecycleState]);
            }

            return $outreachState->refresh();
        });
    }

    private function lockedState(Prospect $prospect): ProspectOutreachState
    {
        $this->stateFor($prospect);

        return ProspectOutreachState::query()->whereBelongsTo($prospect)->lockForUpdate()->firstOrFail();
    }

    private function stopReasonFor(ProspectLifecycleState $lifecycleState): ?ProspectOutreachStopReason
    {
        return match ($lifecycleState) {
            ProspectLifecycleState::Replied => ProspectOutreachStopReason::Replied,
            ProspectLifecycleState::NotInterested => ProspectOutreachStopReason::NotInterested,
            ProspectLifecycleState::FutureOpportunity => ProspectOutreachStopReason::FutureOpportunity,
            ProspectLifecycleState::Customer => ProspectOutreachStopReason::Customer,
            ProspectLifecycleState::Pilot => ProspectOutreachStopReason::Pilot,
            ProspectLifecycleState::Closed => ProspectOutreachStopReason::Closed,
            ProspectLifecycleState::Exhausted => ProspectOutreachStopReason::Exhausted,
            default => null,
        };
    }

    /** @return array<string, mixed> */
    private function legacyProspectAttributes(ProspectLifecycleState $lifecycleState): array
    {
        return match ($lifecycleState) {
            ProspectLifecycleState::Replied => ['status' => 'replied', 'replied_at' => now(), 'scheduled_send_at' => null, 'next_follow_up_at' => null],
            ProspectLifecycleState::NotInterested => ['status' => 'not_interested', 'scheduled_send_at' => null, 'next_follow_up_at' => null],
            ProspectLifecycleState::Customer => ['status' => 'converted', 'converted_at' => now(), 'scheduled_send_at' => null, 'next_follow_up_at' => null],
            ProspectLifecycleState::Pilot => ['status' => 'converted', 'converted_at' => now(), 'scheduled_send_at' => null, 'next_follow_up_at' => null],
            default => $lifecycleState->stopsNormalOutreach() ? ['scheduled_send_at' => null, 'next_follow_up_at' => null] : [],
        };
    }

    private function temperatureForScore(int $score): string
    {
        if ($score >= (int) config('outreach.temperature_thresholds.hot', 10)) {
            return 'hot';
        }

        return $score >= (int) config('outreach.temperature_thresholds.warm', 3) ? 'warm' : 'cold';
    }
}
