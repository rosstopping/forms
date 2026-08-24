<?php

namespace App\Services;

use App\Mail\ProspectOutreach;
use App\Models\Prospect;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use LogicException;
use Throwable;

class ProspectOutreachSender
{
    public function __construct(private ProspectOutreachTracker $tracker) {}

    public function eligibilityError(Prospect $prospect): ?string
    {
        return match (true) {
            $prospect->approved_at === null => 'Approve this draft before sending.',
            $prospect->sent_at !== null && ! $prospect->isOutreachFollowUpDue() => 'This prospect is not due for another outreach email yet.',
            $prospect->suppressed_at !== null => 'This prospect is on the suppression list.',
            blank($prospect->email) => 'Add an email address before sending.',
            blank($prospect->website_url) && blank($prospect->showcase_video_url) => 'Add this prospect\'s showcase video URL before sending.',
            default => null,
        };
    }

    public function send(Prospect $prospect, ?User $actor = null): void
    {
        Cache::lock('prospect-outreach-send-'.$prospect->getKey(), 60)->block(5, function () use ($prospect, $actor): void {
            $prospect->refresh();
            $eligibilityError = $this->eligibilityError($prospect);

            if ($eligibilityError !== null) {
                throw new LogicException($eligibilityError);
            }

            $delivery = $this->tracker->createDelivery($prospect);

            try {
                Mail::to($prospect->email)->send(new ProspectOutreach($prospect, $delivery));
            } catch (Throwable $exception) {
                $delivery->delete();

                throw $exception;
            }

            $sentAt = now();
            $delivery->update(['sent_at' => $sentAt]);
            $prospect->update([
                'status' => 'contacted',
                'sent_at' => $sentAt,
                'scheduled_send_at' => null,
                'next_follow_up_at' => $sentAt->copy()->addWeek(),
            ]);
            $prospect->recordActivity('sent', 'Approved outreach email sent.', $actor);
        });
    }
}
