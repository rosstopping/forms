<?php

namespace App\Mail;

use App\Models\FormSubmission;
use App\Models\FormSubmissionFollowUpReminder;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class LeadFollowUpReminder extends Mailable
{
    public function __construct(public FormSubmission $submission, public FormSubmissionFollowUpReminder $reminder) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Follow-up due: '.$this->submission->website->name);
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.lead-follow-up-reminder');
    }
}
