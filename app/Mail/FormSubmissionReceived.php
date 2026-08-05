<?php

namespace App\Mail;

use App\Models\FormSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class FormSubmissionReceived extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public FormSubmission $submission) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->submission->resolvedEmailSubject(),
            replyTo: [$this->submission->replyToEmail() ?: config('forms.from_address')],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.form-submission',
            with: [
                'submission' => $this->submission,
            ],
        );
    }
}
