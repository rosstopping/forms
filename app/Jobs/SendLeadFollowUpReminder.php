<?php

namespace App\Jobs;

use App\Mail\LeadFollowUpReminder;
use App\Models\FormSubmission;
use App\Models\FormSubmissionFollowUpReminder;
use App\Models\User;
use App\Models\Website;
use App\Services\WebsiteMailRecipients;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendLeadFollowUpReminder implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public int $uniqueFor = 1800;

    /** @var array<int, int> */
    public array $backoff = [60, 300];

    public function __construct(public int $reminderId) {}

    public function uniqueId(): string
    {
        return (string) $this->reminderId;
    }

    public function handle(WebsiteMailRecipients $recipients): void
    {
        $submissionId = FormSubmissionFollowUpReminder::query()->whereKey($this->reminderId)->value('form_submission_id');
        if (! $submissionId) {
            return;
        }

        $attemptedRecipient = null;
        try {
            DB::transaction(function () use ($submissionId, $recipients, &$attemptedRecipient): void {
                $submission = FormSubmission::query()->lockForUpdate()->find($submissionId);
                $reminder = FormSubmissionFollowUpReminder::query()->lockForUpdate()->find($this->reminderId);
                if (! $submission || ! $reminder || $reminder->status !== 'pending') {
                    return;
                }
                $reminder->setRelation('submission', $submission);
                if (! $submission->follow_up_at?->equalTo($reminder->due_at)) {
                    $reminder->cancel('Follow-up date no longer matches this reminder.');

                    return;
                }
                if ($reminder->due_at->isFuture()) {
                    return;
                }
                $submission->load(['website.owner', 'website.members', 'assignee']);
                $website = $submission->website;
                if ($submission->is_spam || in_array($submission->status, ['won', 'lost'], true)) {
                    $reminder->cancel('Follow-up reminder cancelled because the lead is closed or spam.');

                    return;
                }
                if (! $website?->is_active || ($website->owner && ! $website->owner->hasActiveMembership())) {
                    $reminder->cancel('Follow-up reminder cancelled because the website or its membership is inactive.');

                    return;
                }
                $recipient = $this->eligibleRecipient($website, $submission->assignee, $recipients)
                    ?? $this->eligibleRecipient($website, $website->owner, $recipients);
                if (! $recipient) {
                    $reminder->cancel('Follow-up reminder cancelled because no eligible recipient is available.');

                    return;
                }
                $attemptedRecipient = $recipient;
                $reminder->update(['recipient' => $recipient]);
                Mail::to($recipient)->send(new LeadFollowUpReminder($submission, $reminder));
                $reminder->update(['status' => 'sent', 'sent_at' => now(), 'error' => null, 'failed_at' => null]);
                $submission->recordActivity('follow_up_reminder_sent', 'Follow-up reminder email sent.', metadata: ['reminder_id' => $reminder->id, 'recipient' => $recipient]);
            });
        } catch (Throwable $exception) {
            FormSubmissionFollowUpReminder::query()->whereKey($this->reminderId)->where('status', 'pending')
                ->update(['error' => $exception->getMessage(), 'recipient' => $attemptedRecipient]);
            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        DB::transaction(function () use ($exception): void {
            $reminder = FormSubmissionFollowUpReminder::query()->lockForUpdate()->find($this->reminderId);
            if (! $reminder || $reminder->status !== 'pending') {
                return;
            }
            $reminder->update(['status' => 'failed', 'failed_at' => now(), 'error' => $exception?->getMessage() ?? 'The reminder could not be sent.']);
            $reminder->submission?->recordActivity('follow_up_reminder_failed', 'Follow-up reminder email could not be sent. Change the follow-up date to schedule another attempt.', metadata: ['reminder_id' => $reminder->id]);
        });
    }

    private function eligibleRecipient(Website $website, ?User $user, WebsiteMailRecipients $recipients): ?string
    {
        return $user && filled($user->email) && $website->isManageableBy($user) && ! $recipients->isViewer($website, $user->email)
            ? $user->email
            : null;
    }
}
