<?php

namespace App\Mail;

use App\Models\Website;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WeeklyRankingReport extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /** @param array<string, mixed> $report */
    public function __construct(public Website $website, public array $report)
    {
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Weekly ranking report: '.$this->website->name);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.weekly-ranking-report',
            with: ['website' => $this->website, 'report' => $this->report, 'reportUrl' => route('admin.websites.show', [$this->website, 'tab' => 'seo'])],
        );
    }
}
