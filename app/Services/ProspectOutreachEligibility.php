<?php

namespace App\Services;

use App\Enums\ProspectAutomationStatus;
use App\Enums\ProspectLifecycleState;
use App\Enums\ProspectSequenceStep;
use App\Models\Prospect;

class ProspectOutreachEligibility
{
    public function error(Prospect $prospect): ?string
    {
        $outreachState = $prospect->outreachState()->first();

        return match (true) {
            $prospect->suppressed_at !== null => 'This prospect is on the suppression list.',
            $outreachState?->automation_status === ProspectAutomationStatus::Paused => 'Outreach automation is paused for this prospect.',
            $outreachState?->automation_status === ProspectAutomationStatus::Stopped => 'Outreach has been stopped for this prospect.',
            $outreachState?->lifecycle_state->stopsNormalOutreach() === true => 'This prospect is in a lifecycle state that stops normal outreach.',
            $prospect->approved_at === null => 'Approve this draft before sending.',
            $prospect->sent_at !== null && ! $prospect->isOutreachFollowUpDue() => 'This prospect is not due for another outreach email yet.',
            blank($prospect->email) => 'Add an email address before sending.',
            blank($prospect->website_url) && blank($prospect->showcase_video_url) => 'Add this prospect\'s showcase video URL before sending.',
            default => null,
        };
    }

    public function automatedError(Prospect $prospect): ?string
    {
        $error = $this->error($prospect);

        if ($error !== null) {
            return $error;
        }

        $outreachState = $prospect->outreachState()->first();

        return $outreachState && $outreachState->engagement_score >= (int) config('outreach.temperature_thresholds.warm', 3)
            ? 'Meaningful engagement has paused the automated cold sequence.'
            : null;
    }

    public function manualMessageError(Prospect $prospect): ?string
    {
        $outreachState = $prospect->outreachState()->first();

        return match (true) {
            $prospect->suppressed_at !== null => 'This prospect is on the suppression list.',
            $outreachState?->automation_status === ProspectAutomationStatus::Stopped => 'Outreach has been stopped for this prospect.',
            $outreachState?->lifecycle_state->stopsNormalOutreach() === true => 'This prospect is in a lifecycle state that stops normal outreach.',
            $prospect->approved_at === null => 'The initial outreach must remain approved before sending a personalised video.',
            blank($prospect->email) => 'Add an email address before sending.',
            default => null,
        };
    }

    public function postVideoFollowUpError(Prospect $prospect): ?string
    {
        $error = $this->manualMessageError($prospect);

        if ($error !== null) {
            return $error;
        }

        $outreachState = $prospect->outreachState()->first();

        return match (true) {
            $outreachState?->automation_status !== ProspectAutomationStatus::Active => 'Post-video automation is not active for this prospect.',
            $outreachState->lifecycle_state !== ProspectLifecycleState::VideoSent => 'The prospect is not awaiting a post-video follow-up.',
            $outreachState->sequence_step !== ProspectSequenceStep::PersonalisedVideo => 'The post-video follow-up has already been handled.',
            $outreachState->video_sent_at === null => 'No personalised video delivery has been recorded.',
            $outreachState->post_video_follow_up_sent_at !== null => 'The post-video follow-up has already been sent.',
            default => null,
        };
    }
}
