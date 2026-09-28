<?php

namespace App\Services;

use App\Enums\ProspectAutomationStatus;
use App\Enums\ProspectLifecycleState;
use App\Enums\ProspectOutreachMessageType;
use App\Enums\ProspectOutreachStopReason;
use App\Enums\ProspectSequenceStep;
use App\Models\Prospect;
use App\Models\ProspectOutreachState;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ProspectOutreachSequence
{
    public function __construct(
        private ProspectOutreachSender $sender,
        private ProspectManualFollowUpAdvisor $manualFollowUpAdvisor,
        private ProspectLifecycleManager $lifecycleManager,
        private ProspectOutreachContent $content,
    ) {}

    public function evaluate(Prospect $prospect): void
    {
        Cache::lock('prospect-outreach-evaluate-'.$prospect->getKey(), 60)->block(5, function () use ($prospect): void {
            DB::transaction(function () use ($prospect): void {
                $state = ProspectOutreachState::query()
                    ->whereBelongsTo($prospect)
                    ->lockForUpdate()
                    ->first();

                if (! $state || ! $this->isDueAndActive($state)) {
                    return;
                }

                if ($state->sequence_step === ProspectSequenceStep::PersonalisedVideo) {
                    $this->finishVideoSequence($prospect, $state);

                    return;
                }

                if ($this->isColdSequenceStep($state->sequence_step)
                    && $state->engagement_score >= (int) config('outreach.temperature_thresholds.warm', 3)) {
                    $state->update(['next_action_at' => null]);
                    $prospect->update(['next_follow_up_at' => null]);
                    $prospect->recordActivity('sequence_paused_for_engagement', 'Automated cold follow-up paused because the prospect showed meaningful engagement.');

                    return;
                }

                if (! (bool) config('outreach.automatic_follow_ups_enabled', true)) {
                    return;
                }

                if ($this->isColdSequenceStep($state->sequence_step)
                    && $state->follow_up_attempts >= (int) config('outreach.maximum_follow_up_attempts', 2)) {
                    $this->exhaust($prospect, $state);

                    return;
                }

                match ($state->sequence_step) {
                    ProspectSequenceStep::InitialEmail => $this->sendColdFollowUp($prospect, $state),
                    ProspectSequenceStep::ColdFollowUp => $this->sendFinalFollowUp($prospect, $state),
                    ProspectSequenceStep::FinalFollowUp => $this->exhaust($prospect, $state),
                    default => null,
                };
            });
        });
    }

    private function sendColdFollowUp(Prospect $prospect, ProspectOutreachState $state): void
    {
        $this->sendFollowUp($prospect, $state, ProspectOutreachMessageType::ColdFollowUp, ProspectSequenceStep::ColdFollowUp);
    }

    private function sendFinalFollowUp(Prospect $prospect, ProspectOutreachState $state): void
    {
        $this->sendFollowUp($prospect, $state, ProspectOutreachMessageType::FinalFollowUp, ProspectSequenceStep::FinalFollowUp);
    }

    private function finishVideoSequence(Prospect $prospect, ProspectOutreachState $state): void
    {
        $state->update(['next_action_at' => null]);
        $prospect->update(['next_follow_up_at' => null]);
        $reasons = $this->manualFollowUpAdvisor->reasonsFor($prospect);

        if ($reasons !== []) {
            $this->lifecycleManager->recommendManualFollowUp($prospect, $reasons);
        }
    }

    private function sendFollowUp(
        Prospect $prospect,
        ProspectOutreachState $state,
        ProspectOutreachMessageType $messageType,
        ProspectSequenceStep $sequenceStep,
    ): void {
        $message = $this->content->followUp($prospect, $messageType);
        $attempt = $state->follow_up_attempts + 1;

        $delivery = $this->sender->sendAutomated(
            $prospect,
            $messageType,
            $message['subject'],
            $message['body'],
            'prospect:'.$prospect->getKey().':'.$messageType->value.':'.$attempt,
        );

        $nextActionAt = now()->addDays((int) config('outreach.timing.final_follow_up_days', 6));
        $state->update([
            'lifecycle_state' => ProspectLifecycleState::Cold,
            'sequence_step' => $sequenceStep,
            'follow_up_attempts' => $attempt,
            'last_outreach_at' => $delivery->sent_at,
            'next_action_at' => $nextActionAt,
        ]);
        $prospect->update(['next_follow_up_at' => $nextActionAt]);
    }

    private function exhaust(Prospect $prospect, ProspectOutreachState $state): void
    {
        $state->update([
            'lifecycle_state' => ProspectLifecycleState::Exhausted,
            'automation_status' => ProspectAutomationStatus::Stopped,
            'sequence_step' => ProspectSequenceStep::Complete,
            'next_action_at' => null,
            'stopped_at' => now(),
            'stop_reason' => ProspectOutreachStopReason::Exhausted,
        ]);
        $prospect->update(['next_follow_up_at' => null]);
        $prospect->recordActivity('outreach_exhausted', 'Automated cold outreach completed with no meaningful engagement.');
    }

    private function isDueAndActive(ProspectOutreachState $state): bool
    {
        return $state->automation_status === ProspectAutomationStatus::Active
            && ! $state->lifecycle_state->stopsNormalOutreach()
            && $state->next_action_at?->lessThanOrEqualTo(now()) === true;
    }

    private function isColdSequenceStep(ProspectSequenceStep $sequenceStep): bool
    {
        return in_array($sequenceStep, [
            ProspectSequenceStep::InitialEmail,
            ProspectSequenceStep::ColdFollowUp,
            ProspectSequenceStep::FinalFollowUp,
        ], true);
    }
}
