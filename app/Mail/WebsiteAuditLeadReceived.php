<?php

namespace App\Mail;

use App\Models\WebsiteAudit;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WebsiteAuditLeadReceived extends Mailable implements ShouldQueue
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
            subject: 'Search audit requested: '.$this->audit->domain,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'mail.website-audit-lead-received',
            with: [
                'reportUrl' => route('marketing.website-audits.show', $this->audit),
                'onboardingUrl' => route('admin.onboarding.index', ['search' => $this->audit->domain]),
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
