<?php

namespace App\Services;

use App\Enums\ProspectLifecycleState;
use App\Enums\ProspectOutreachMessageType;
use App\Jobs\SendScheduledProspectPersonalisedVideo;
use App\Mail\ProspectOutreach;
use App\Models\Prospect;
use App\Models\ProspectOutreachDelivery;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use LogicException;

class ProspectPersonalisedVideo
{
    public function __construct(
        private LoomVideoThumbnail $loomVideoThumbnail,
        private ProspectOutreachTracker $tracker,
        private ProspectOutreachSender $sender,
        private ProspectLifecycleManager $lifecycleManager,
        private ProspectOutreachEligibility $eligibility,
    ) {}

    public function saveDraft(Prospect $prospect, string $videoUrl, string $subject, string $body, User $actor): void
    {
        $thumbnailUrl = $this->loomVideoThumbnail->fetch($videoUrl);

        DB::transaction(function () use ($prospect, $videoUrl, $thumbnailUrl, $subject, $body, $actor): void {
            $prospect = Prospect::query()->lockForUpdate()->findOrFail($prospect->id);
            $state = $this->lifecycleManager->stateFor($prospect);

            if ($state->video_sent_at !== null) {
                throw new LogicException('A personalised video has already been sent to this prospect.');
            }

            if ($prospect->lead_temperature !== 'hot' && ! in_array($state->lifecycle_state, [ProspectLifecycleState::Hot, ProspectLifecycleState::NeedsPersonalisedVideo], true)) {
                throw new LogicException('Only a hot prospect can have a personalised video draft through this workflow.');
            }

            $state->update(['personalised_video_draft' => [
                'video_url' => $videoUrl,
                'thumbnail_url' => $thumbnailUrl,
                'subject' => $subject,
                'body' => $body,
            ]]);
            $prospect->recordActivity('personalised_video_draft_saved', 'Personalised video email saved as a draft.', $actor);
        });
    }

    public function sendTest(Prospect $prospect, User $actor): void
    {
        $prospect->refresh();
        $draft = $prospect->outreachState->personalised_video_draft;

        if ($draft === null) {
            throw new LogicException('Save the personalised video draft before sending a test.');
        }

        $preview = clone $prospect;
        $preview->forceFill([
            'showcase_video_url' => $draft['video_url'],
            'showcase_video_thumbnail_url' => $draft['thumbnail_url'],
            'outreach_subject' => $draft['subject'],
            'outreach_body' => $draft['body'],
        ]);

        Mail::to($actor->email)->send(new ProspectOutreach($preview, previewMessageType: ProspectOutreachMessageType::PersonalisedVideo));
        $prospect->recordActivity('personalised_video_test_sent', 'Personalised video test email sent to '.$actor->email.'.', $actor);
    }

    public function sendNow(Prospect $prospect, string $videoUrl, string $subject, string $body, User $actor): ProspectOutreachDelivery
    {
        $delivery = $this->prepare($prospect, $videoUrl, $subject, $body, $actor);
        $delivery = $this->sender->sendPersonalisedVideo($prospect, $subject, $body, $delivery->idempotency_key, $actor);
        $this->lifecycleManager->markPersonalisedVideoSent($prospect, $delivery->sent_at, $actor);

        return $delivery;
    }

    public function schedule(Prospect $prospect, string $videoUrl, string $subject, string $body, CarbonImmutable $scheduledFor, User $actor): ProspectOutreachDelivery
    {
        $delivery = $this->prepare($prospect, $videoUrl, $subject, $body, $actor);
        $delivery->update(['status' => 'scheduled', 'scheduled_at' => $scheduledFor]);
        $prospect->recordActivity('personalised_video_scheduled', 'Personalised video email scheduled for '.$scheduledFor->setTimezone('Europe/London')->format('j M Y, H:i').' UK time.', $actor);
        SendScheduledProspectPersonalisedVideo::dispatch($delivery->id, $scheduledFor)->delay($scheduledFor)->afterCommit();

        return $delivery->refresh();
    }

    public function sendScheduled(ProspectOutreachDelivery $delivery): void
    {
        if ($delivery->message_type !== ProspectOutreachMessageType::PersonalisedVideo || $delivery->status !== 'scheduled') {
            return;
        }

        $prospect = $delivery->prospect;
        $sentDelivery = $this->sender->sendPersonalisedVideo($prospect, (string) $delivery->subject, (string) $delivery->body, (string) $delivery->idempotency_key);
        $this->lifecycleManager->markPersonalisedVideoSent($prospect, $sentDelivery->sent_at);
    }

    public function defaultSubject(Prospect $prospect): string
    {
        $configuredSubject = config('outreach.templates.personalised_video.subject');

        return filled($configuredSubject) ? $this->render((string) $configuredSubject, $prospect) : (string) $prospect->outreach_subject;
    }

    public function defaultBody(Prospect $prospect): string
    {
        return $this->render((string) config('outreach.templates.personalised_video.body', ''), $prospect);
    }

    private function prepare(Prospect $prospect, string $videoUrl, string $subject, string $body, User $actor): ProspectOutreachDelivery
    {
        $thumbnailUrl = $this->loomVideoThumbnail->fetch($videoUrl);

        return DB::transaction(function () use ($prospect, $videoUrl, $thumbnailUrl, $subject, $body, $actor): ProspectOutreachDelivery {
            $prospect->refresh();
            $state = $this->lifecycleManager->stateFor($prospect);
            $eligibilityError = $this->eligibility->manualMessageError($prospect);

            if ($eligibilityError !== null) {
                throw new LogicException($eligibilityError);
            }

            if ($prospect->lead_temperature !== 'hot' && ! in_array($state->lifecycle_state, [ProspectLifecycleState::Hot, ProspectLifecycleState::NeedsPersonalisedVideo], true)) {
                throw new LogicException('Only a hot prospect can receive a personalised video through this workflow.');
            }

            if ($state->video_sent_at !== null) {
                throw new LogicException('A personalised video has already been sent to this prospect.');
            }

            $prospect->update([
                'showcase_video_url' => $videoUrl,
                'showcase_video_thumbnail_url' => $thumbnailUrl,
            ]);
            $state->update(['personalised_video_draft' => null]);
            $this->lifecycleManager->markPersonalisedVideoQueued($prospect, $actor);
            $delivery = $this->tracker->createDelivery(
                $prospect,
                ProspectOutreachMessageType::PersonalisedVideo,
                $subject,
                $body,
                'prospect:'.$prospect->getKey().':personalised_video:1',
            );
            $delivery->update(['subject' => $subject, 'body' => $body]);
            $delivery->links()->where('kind', 'showcase_video')->update(['destination_url' => $videoUrl]);

            return $delivery->refresh()->load('links');
        });
    }

    private function render(string $template, Prospect $prospect): string
    {
        return strtr($template, [
            '{contact_name}' => filled($prospect->contact_name) ? $prospect->contact_name : 'there',
            '{company_name}' => $prospect->business_name,
        ]);
    }
}
