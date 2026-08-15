<?php

namespace App\Notifications;

use App\Models\Website;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class WebsiteInvitation extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public Website $website, public bool $requiresSetup) {}

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
        $url = $this->requiresSetup
            ? URL::temporarySignedRoute('website-invitations.accept', now()->addDays(7), ['user' => $notifiable])
            : route('login');

        return (new MailMessage)
            ->subject('You have been invited to '.$this->website->name)
            ->greeting('You have been invited')
            ->line('You now have access to '.$this->website->name.' in Sitewell.')
            ->action($this->requiresSetup ? 'Set up your account' : 'Sign in to Sitewell', $url)
            ->line($this->requiresSetup ? 'This account setup link expires in 7 days.' : 'Use your existing Sitewell account details to sign in.');
    }
}
