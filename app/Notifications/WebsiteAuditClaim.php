<?php

namespace App\Notifications;

use App\Models\WebsiteAudit;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class WebsiteAuditClaim extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public WebsiteAudit $audit) {}

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
        $url = URL::temporarySignedRoute(
            'marketing.website-audits.onboarding',
            now()->addDay(),
            ['websiteAudit' => $this->audit],
        );

        return (new MailMessage)
            ->subject('Continue getting started with '.$this->audit->domain)
            ->greeting('Your website review is ready')
            ->line('Confirm your email address to start preparing a fix plan for '.$this->audit->domain.'.')
            ->action('Continue with Sitewell', $url)
            ->line('This secure link expires in 24 hours. No website changes will be made without your approval.');
    }
}
