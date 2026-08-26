<?php

namespace App\Jobs;

use App\Mail\FormSubmissionAcknowledgement;
use App\Models\FormSubmission;
use App\Services\AutoresponderHtmlSanitizer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendFormSubmissionAcknowledgement implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [60, 300];

    public function __construct(
        public FormSubmission $submission,
        public string $recipient,
        public string $emailSubject,
        public string $emailBody,
    ) {}

    public function handle(AutoresponderHtmlSanitizer $autoresponderHtmlSanitizer): void
    {
        Mail::to($this->recipient)->send(new FormSubmissionAcknowledgement(
            $this->submission,
            $this->emailSubject,
            $this->emailBody,
            $autoresponderHtmlSanitizer->toPlainText($this->emailBody),
        ));

        $this->submission->update([
            'autoresponder_sent_at' => now(),
            'autoresponder_failed_at' => null,
            'autoresponder_error' => null,
        ]);
        $this->submission->recordActivity('autoresponder_sent', 'Automatic acknowledgement sent to the customer.');
    }

    public function failed(?Throwable $exception): void
    {
        $this->submission->update([
            'autoresponder_failed_at' => now(),
            'autoresponder_error' => $exception?->getMessage(),
        ]);
        $this->submission->recordActivity('autoresponder_failed', 'Automatic acknowledgement could not be sent.');
    }
}
