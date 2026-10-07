<?php

namespace App\Mail;

use App\Models\WebsiteAudit;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class WebsiteAuditReport extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public WebsiteAudit $audit)
    {
        $this->afterCommit();
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(config('mail.from.address'), 'Ross at Sitewell'),
            replyTo: [config('marketing.audit_notification_email')],
            subject: ($this->audit->personal_review_requested_at ? 'Your growth plan request for ' : 'Your Sitewell search audit for ').$this->audit->domain,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'mail.website-audit-report',
            with: [
                'reportUrl' => route('marketing.website-audits.show', $this->audit),
                'preferencesUrl' => URL::signedRoute('marketing.website-audits.email-preferences', $this->audit),
                'bookingUrl' => route('marketing.ppc.book'),
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
