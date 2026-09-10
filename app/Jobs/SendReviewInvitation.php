<?php

namespace App\Jobs;

use App\Mail\CustomerReviewInvitation;
use App\Models\FormSubmission;
use App\Models\ReviewInvitation;
use App\Services\ReviewInvitationService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendReviewInvitation implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 60;

    public int $uniqueFor = 1800;

    public function __construct(public int $invitationId) {}

    public function uniqueId(): string
    {
        return (string) $this->invitationId;
    }

    public function handle(ReviewInvitationService $reviews): void
    {
        $candidate = ReviewInvitation::find($this->invitationId);
        if (! $candidate) {
            return;
        }
        $invitation = DB::transaction(function () use ($candidate, $reviews): ?ReviewInvitation {
            $lead = FormSubmission::query()->lockForUpdate()->find($candidate->form_submission_id);
            $invitation = ReviewInvitation::query()->lockForUpdate()->find($this->invitationId);
            if (! $lead || ! $invitation || $invitation->status !== 'queued') {
                return null;
            }
            $reason = $reviews->unavailableReason($lead, $invitation->requester);
            if (! $reason && ($lead->replyToEmail() !== $invitation->recipient || $lead->website->review_url !== $invitation->review_url)) {
                $reason = 'The customer email address or website review link changed after this invitation was queued.';
            }
            if ($reason) {
                $invitation->update(['status' => 'cancelled', 'cancelled_at' => now(), 'error' => $reason]);
                $lead->recordActivity('review_invitation_cancelled', 'Review invitation cancelled.', metadata: ['review_invitation_id' => $invitation->id, 'reason' => $reason]);

                return null;
            }
            $invitation->update(['status' => 'sending', 'started_at' => now()]);

            return $invitation;
        });
        if (! $invitation) {
            return;
        }
        try {
            Mail::to($invitation->recipient)->send(new CustomerReviewInvitation($invitation));
            DB::transaction(function () use ($invitation): void {
                $invitation->update(['status' => 'sent', 'sent_at' => now()]);
                $invitation->submission?->recordActivity('review_invitation_sent', 'Review invitation email sent.', metadata: ['review_invitation_id' => $invitation->id, 'recipient' => $invitation->recipient]);
            });
        } catch (Throwable $exception) {
            $this->failed($exception);
            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        DB::transaction(function () use ($exception): void {
            $invitation = ReviewInvitation::query()->lockForUpdate()->find($this->invitationId);
            if (! $invitation || ! in_array($invitation->status, ['queued', 'sending'], true)) {
                return;
            }
            $invitation->update(['status' => 'failed', 'failed_at' => now(), 'error' => $exception?->getMessage() ?? 'The invitation could not be sent.']);
            $invitation->submission?->recordActivity('review_invitation_failed', 'Review invitation could not be confirmed as sent.', metadata: ['review_invitation_id' => $invitation->id]);
        });
    }
}
