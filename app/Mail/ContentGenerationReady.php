<?php

namespace App\Mail;

use App\Models\ContentGeneration;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContentGenerationReady extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public ContentGeneration $generation)
    {
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Content ready for review: '.$this->generation->plan->website->name);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.content-generation-ready', with: ['generation' => $this->generation]);
    }
}
