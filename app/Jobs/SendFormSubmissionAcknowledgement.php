<?php

namespace App\Jobs;

use App\Models\FormSubmission;
use App\Services\AutoresponderDeliveryService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
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
        public ?string $fromEmail = null,
        public ?string $fromName = null,
    ) {}

    public function handle(AutoresponderDeliveryService $deliveryService): void
    {
        $delivery = $deliveryService->send(
            $this->submission,
            $this->recipient,
            $this->emailSubject,
            $this->emailBody,
            $this->fromEmail,
            $this->fromName,
        );

        if (! in_array($delivery->status, ['sent', 'delivered'], true)) {
            return;
        }

        $this->submission->update([
            'autoresponder_sent_at' => now(),
            'autoresponder_failed_at' => null,
            'autoresponder_error' => null,
        ]);
        $this->submission->recordActivity('autoresponder_sent', 'Automatic acknowledgement sent to the customer.');
    }

    public function failed(?Throwable $exception): void
    {
        $this->submission->emailDeliveries()->where('type', 'autoresponder')->update([
            'status' => 'failed',
            'failed_at' => now(),
            'failure_reason' => $exception?->getMessage(),
        ]);
        $this->submission->update([
            'autoresponder_failed_at' => now(),
            'autoresponder_error' => $exception?->getMessage(),
        ]);
        $this->submission->recordActivity('autoresponder_failed', 'Automatic acknowledgement could not be sent.');
    }
}
