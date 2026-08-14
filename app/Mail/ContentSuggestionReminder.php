<?php

namespace App\Mail;

use App\Models\ContentPlan;
use App\Models\SearchOpportunity;
use App\Models\SeoOpportunity;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class ContentSuggestionReminder extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /** @param Collection<int, SearchOpportunity> $searchOpportunities
     *  @param Collection<int, SeoOpportunity> $seoOpportunities */
    public function __construct(public ContentPlan $plan, public Collection $searchOpportunities, public Collection $seoOpportunities)
    {
        $this->afterCommit();
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Content ideas for '.$this->plan->website->name,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.content-suggestion-reminder',
            with: [
                'suggestionUrl' => fn (string $type, int $id): string => URL::temporarySignedRoute('admin.content-suggestions.store', now()->addHours(30), [$this->plan->website, 'type' => $type, 'opportunity' => $id]),
            ],
        );
    }
}
