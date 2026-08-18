<?php

namespace App\Notifications;

use App\Models\Prospect;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProspectBecameHot extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public Prospect $prospect, public string $clickedLink)
    {
        $this->afterCommit();
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Hot prospect: '.$this->prospect->business_name)
            ->greeting('A prospect has turned hot')
            ->line($this->prospect->business_name.' clicked the “'.$this->clickedLink.'” link in the outreach email.')
            ->line('They have shown stronger intent and may be worth following up with now.')
            ->action('View prospect', route('admin.prospects.show', $this->prospect));
    }
}
