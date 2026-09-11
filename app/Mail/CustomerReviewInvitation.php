<?php

namespace App\Mail;

use App\Models\ReviewInvitation;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class CustomerReviewInvitation extends Mailable
{
    public function __construct(public ReviewInvitation $invitation) {}

    public function envelope(): Envelope
    {
        return new Envelope(from: new Address($this->invitation->from_email, $this->invitation->from_name), subject: $this->invitation->subject);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.customer-review-invitation', text: 'emails.customer-review-invitation-text');
    }
}
