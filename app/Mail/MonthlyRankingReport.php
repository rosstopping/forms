<?php

namespace App\Mail;

use App\Models\Website;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MonthlyRankingReport extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /** @param array<string, mixed> $report */
    public function __construct(public Website $website, public array $report)
    {
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your monthly search performance: '.$this->website->name);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.monthly-ranking-report', with: [
            'website' => $this->website, 'report' => $this->report,
            'reportUrl' => collect($this->report['targetKeywords'] ?? [])->isNotEmpty()
                ? route('admin.websites.show', [$this->website, 'tab' => 'seo', 'seo_section' => 'targets'])
                : route('admin.websites.section', [$this->website, 'search']),
        ]);
    }
}
