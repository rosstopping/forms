<?php

namespace App\Mail;

use App\Models\Prospect;
use App\Models\ProspectOutreachDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class ProspectOutreach extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(public Prospect $prospect, public ?ProspectOutreachDelivery $delivery = null) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(config('mail.from.address'), 'Ross'),
            subject: $this->prospect->outreach_subject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $showcaseVideoUrl = $this->prospect->showcase_video_url;
        $auditReportUrl = $this->auditReportUrl();
        $bookingUrl = 'https://cal.com/ross';
        $trackingOpenUrl = null;

        if ($this->delivery) {
            $this->delivery->loadMissing('links');
            $showcaseVideoLink = $this->delivery->links->firstWhere('kind', 'showcase_video');
            $auditReportLink = $this->delivery->links->firstWhere('kind', 'website_audit');
            $bookingLink = $this->delivery->links->firstWhere('kind', 'book_call');
            $showcaseVideoUrl = $showcaseVideoLink ? URL::signedRoute('prospect-outreach-links.show', $showcaseVideoLink) : null;
            $auditReportUrl = $auditReportLink ? URL::signedRoute('prospect-outreach-links.show', $auditReportLink) : null;
            $bookingUrl = URL::signedRoute('prospect-outreach-links.show', $bookingLink);
            $trackingOpenUrl = URL::signedRoute('prospect-outreach-opens.show', $this->delivery);
        }

        return new Content(
            view: 'mail.prospects.outreach',
            with: [
                'showcaseVideoUrl' => $showcaseVideoUrl,
                'auditReportUrl' => $auditReportUrl,
                'bookingUrl' => $bookingUrl,
                'trackingOpenUrl' => $trackingOpenUrl,
            ],
        );
    }

    private function auditReportUrl(): ?string
    {
        if (blank($this->prospect->website_url) || $this->prospect->analysed_at === null) {
            return null;
        }

        return URL::temporarySignedRoute('prospect-reports.show', now()->addDays(30), ['prospect' => $this->prospect]);
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
