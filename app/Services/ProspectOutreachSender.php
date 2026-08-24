<?php

namespace App\Services;

use App\Enums\ProspectOutreachMessageType;
use App\Mail\ProspectOutreach;
use App\Models\Prospect;
use App\Models\ProspectOutreachDelivery;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use LogicException;
use Throwable;

class ProspectOutreachSender
{
    public function __construct(
        private ProspectOutreachTracker $tracker,
        private ProspectOutreachEligibility $eligibility,
        private ProspectLifecycleManager $lifecycleManager,
    ) {}

    public function eligibilityError(Prospect $prospect): ?string
    {
        return $this->eligibility->error($prospect);
    }

    public function send(Prospect $prospect, ?User $actor = null): void
    {
        $this->sendMessage($prospect, ProspectOutreachMessageType::Initial, null, null, null, $actor);
    }

    public function sendAutomated(
        Prospect $prospect,
        ProspectOutreachMessageType $messageType,
        string $subject,
        string $body,
        string $idempotencyKey,
    ): ProspectOutreachDelivery {
        return $this->sendMessage($prospect, $messageType, $subject, $body, $idempotencyKey);
    }

    public function sendPersonalisedVideo(
        Prospect $prospect,
        string $subject,
        string $body,
        string $idempotencyKey,
        ?User $actor = null,
    ): ProspectOutreachDelivery {
        return $this->sendMessage($prospect, ProspectOutreachMessageType::PersonalisedVideo, $subject, $body, $idempotencyKey, $actor, true);
    }

    public function sendPostVideoFollowUp(Prospect $prospect, string $subject, string $body): ProspectOutreachDelivery
    {
        return $this->sendMessage(
            $prospect,
            ProspectOutreachMessageType::PostVideoFollowUp,
            $subject,
            $body,
            'prospect:'.$prospect->getKey().':post_video_follow_up:1',
            postVideoFollowUp: true,
        );
    }

    private function sendMessage(
        Prospect $prospect,
        ProspectOutreachMessageType $messageType,
        ?string $subject,
        ?string $body,
        ?string $idempotencyKey,
        ?User $actor = null,
        bool $manualMessage = false,
        bool $postVideoFollowUp = false,
    ): ProspectOutreachDelivery {
        return Cache::lock('prospect-outreach-send-'.$prospect->getKey(), 60)->block(5, function () use ($prospect, $messageType, $subject, $body, $idempotencyKey, $actor, $manualMessage, $postVideoFollowUp): ProspectOutreachDelivery {
            $prospect->refresh();
            $eligibilityError = $postVideoFollowUp
                ? $this->eligibility->postVideoFollowUpError($prospect)
                : ($manualMessage
                ? $this->eligibility->manualMessageError($prospect)
                : ($idempotencyKey
                    ? $this->eligibility->automatedError($prospect)
                    : $this->eligibilityError($prospect)));

            if ($eligibilityError !== null) {
                throw new LogicException($eligibilityError);
            }

            $delivery = $this->tracker->createDelivery($prospect, $messageType, $subject, $body, $idempotencyKey);

            if ($delivery->sent_at !== null) {
                return $delivery;
            }

            $delivery->update(['status' => 'pending', 'scheduled_at' => null, 'failed_at' => null, 'failure_reason' => null]);

            try {
                Mail::to($prospect->email)->send(new ProspectOutreach($prospect, $delivery));
            } catch (Throwable $exception) {
                $delivery->update([
                    'status' => 'failed',
                    'failed_at' => now(),
                    'failure_reason' => str($exception->getMessage())->limit(2000),
                ]);

                throw $exception;
            }

            $sentAt = now();
            $delivery->update(['status' => 'sent', 'sent_at' => $sentAt]);
            $prospect->update([
                'status' => 'contacted',
                'sent_at' => $prospect->sent_at ?? $sentAt,
                'scheduled_send_at' => null,
            ]);
            $this->lifecycleManager->markMessageSent($prospect, $messageType, $sentAt);
            $description = $messageType === ProspectOutreachMessageType::Initial
                ? 'Approved outreach email sent.'
                : str($messageType->value)->replace('_', ' ')->headline().' sent automatically.';
            $prospect->recordActivity('sent', $description, $actor);

            return $delivery->refresh();
        });
    }
}
