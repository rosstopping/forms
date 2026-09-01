<?php

namespace App\Services;

use App\Mail\FormSubmissionAcknowledgement;
use App\Models\FormSubmission;
use App\Models\FormSubmissionEmailDelivery;
use App\Models\WebsiteMailConnection;
use Illuminate\Support\Facades\Mail;

class AutoresponderDeliveryService
{
    public function __construct(
        private ManagedAutoresponderLimitPolicy $limitPolicy,
        private AutoresponderHtmlSanitizer $htmlSanitizer,
        private PostmarkServerClient $postmark,
    ) {}

    public function send(
        FormSubmission $submission,
        string $recipient,
        string $subject,
        string $body,
        ?string $fromEmail,
        ?string $fromName,
    ): FormSubmissionEmailDelivery {
        $connection = $submission->website->mailConnection;
        $mode = $connection?->mode ?? WebsiteMailConnection::MODE_LEGACY;
        $delivery = FormSubmissionEmailDelivery::query()->firstOrCreate(
            ['form_submission_id' => $submission->id, 'type' => 'autoresponder'],
            [
                'website_id' => $submission->website_id,
                'website_mail_connection_id' => $connection?->id,
                'mode' => $mode,
                'status' => 'queued',
                'recipient' => $recipient,
                'subject' => $subject,
                'from_email' => $fromEmail,
                'from_name' => $fromName,
            ],
        );

        if (in_array($delivery->status, ['sent', 'delivered', 'suppressed'], true)) {
            return $delivery;
        }

        $reason = $connection && $connection->status !== 'active'
            ? 'mail_connection_'.$connection->status
            : ($connection ? $this->limitPolicy->suppressionReason($connection, $recipient) : null);

        if ($reason) {
            $delivery->update(['status' => 'suppressed', 'suppression_reason' => $reason]);
            $submission->recordActivity('autoresponder_suppressed', 'Automatic acknowledgement was not sent because a sending safety limit was reached.', metadata: ['reason' => $reason]);

            return $delivery->refresh();
        }

        $claimed = FormSubmissionEmailDelivery::query()
            ->whereKey($delivery->id)
            ->whereIn('status', ['queued', 'failed'])
            ->update(['status' => 'sending', 'failure_reason' => null, 'failed_at' => null]);

        if ($claimed === 0) {
            return $delivery->refresh();
        }

        try {
            if (in_array($connection?->mode, [WebsiteMailConnection::MODE_MANAGED, WebsiteMailConnection::MODE_CUSTOMER_POSTMARK], true)) {
                $this->sendWithPostmark($connection, $delivery, $submission, $body);
            } else {
                Mail::to($recipient)->send(new FormSubmissionAcknowledgement(
                    $submission,
                    $subject,
                    $body,
                    $this->htmlSanitizer->toPlainText($body),
                    $fromEmail,
                    $fromName,
                ));
            }
        } catch (\Throwable $exception) {
            $delivery->update([
                'status' => 'failed',
                'failed_at' => now(),
                'failure_reason' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        $delivery->update(['status' => 'sent', 'sent_at' => now()]);

        return $delivery->refresh();
    }

    private function sendWithPostmark(WebsiteMailConnection $connection, FormSubmissionEmailDelivery $delivery, FormSubmission $submission, string $body): void
    {
        $response = $this->postmark->send($connection->postmark_server_token, [
            'From' => filled($delivery->from_name) ? $delivery->from_name.' <'.$delivery->from_email.'>' : $delivery->from_email,
            'To' => $delivery->recipient,
            'Subject' => $delivery->subject,
            'HtmlBody' => view('emails.form-submission-acknowledgement', ['emailBody' => $body, 'submission' => $submission])->render(),
            'TextBody' => $this->htmlSanitizer->toPlainText($body),
            'MessageStream' => 'outbound',
            'Metadata' => [
                'website_id' => (string) $delivery->website_id,
                'submission_id' => (string) $submission->id,
                'delivery_id' => (string) $delivery->id,
            ],
        ]);

        $delivery->update(['provider_message_id' => $response['MessageID'] ?? null]);
    }
}
