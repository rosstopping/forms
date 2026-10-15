<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class WebsiteGrowthPlanReady extends Mailable implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $websiteName, public string $reportUrl)
    {
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your website plan of action: '.$this->websiteName);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.website-growth-plan');
    }
}
