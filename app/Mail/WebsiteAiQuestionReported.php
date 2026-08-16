<?php

namespace App\Mail;

use App\Models\WebsiteAiQuestion;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WebsiteAiQuestionReported extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(public WebsiteAiQuestion $websiteAiQuestion)
    {
        $this->afterCommit();
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'AI answer reported: '.$this->websiteAiQuestion->website->name,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.website-ai-question-reported',
            with: [
                'reviewUrl' => route('admin.website-ai-question-reports.show', $this->websiteAiQuestion),
            ],
        );
    }
}
