<?php

namespace App\Mail;

use App\Models\WebsiteHealthReport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class WebsiteHealthReportReady extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public WebsiteHealthReport $report)
    {
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Weekly website health report: '.$this->report->website->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.website-health-report',
            with: [
                'report' => $this->report,
                'reportUrl' => URL::temporarySignedRoute(
                    'website-health-reports.show',
                    now()->addDays(30),
                    $this->report,
                ),
            ],
        );
    }
}
