<?php

namespace App\Services;

use App\Enums\ProspectAutomationStatus;
use App\Models\Prospect;

class ProspectOutreachEligibility
{
    public function error(Prospect $prospect): ?string
    {
        $outreachState = $prospect->outreachState()->first();

        return match (true) {
            $prospect->unsubscribed_at !== null => 'This prospect has unsubscribed from outreach.',
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
            $prospect->unsubscribed_at !== null => 'This prospect has unsubscribed from outreach.',
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
        return 'Automatic post-video follow-up emails are disabled.';
    }
}
