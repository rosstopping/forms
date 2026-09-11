<?php

namespace App\Notifications;

use App\Enums\OnboardingLifecycleStep;
use App\Models\OnboardingLifecycleMessage;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OnboardingLifecycleNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public OnboardingLifecycleMessage $message,
        public User $onboardingUser,
        public string $actionUrl,
    ) {}

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
        $website = $this->onboardingUser->onboardingAudit?->domain ?? 'your website';

        return match ($this->message->step) {
            OnboardingLifecycleStep::Welcome => (new MailMessage)
                ->subject('Your 14-day Sitewell Growth trial has started')
                ->greeting('Welcome to Sitewell')
                ->line("Your Growth trial for {$website} is active for 14 days.")
                ->line('You can review website health, connect Google Search Console, explore SEO performance, and discuss the most useful improvements with a specialist.')
                ->action('Open your Sitewell workspace', $this->actionUrl),
            OnboardingLifecycleStep::SearchConsole => (new MailMessage)
                ->subject('Connect Search Console to see how people find your website')
                ->greeting('Add your real search performance')
                ->line("Connecting the matching Google Search Console property for {$website} shows the searches, pages, clicks, and opportunities already associated with your website.")
                ->line('Sitewell only allows a property that matches your website domain.')
                ->action('Connect Google Search Console', $this->actionUrl),
            OnboardingLifecycleStep::BookCall => (new MailMessage)
                ->subject('Book your free Sitewell onboarding call')
                ->greeting('Let’s turn the findings into priorities')
                ->line("A free onboarding call helps us understand what matters most for {$website} and recommend the best way to connect and improve it.")
                ->action('Book your free call', $this->actionUrl),
            OnboardingLifecycleStep::Progress => (new MailMessage)
                ->subject('Your first week with Sitewell')
                ->greeting('Your trial progress so far')
                ->line("Your weekly website health report for {$website} is handled separately, so this message focuses on the next useful step.")
                ->line('Review the latest findings, connect any missing website data, and use your onboarding call to decide what deserves attention first.')
                ->action('Review your progress', $this->actionUrl),
            OnboardingLifecycleStep::GrowthFeatures => (new MailMessage)
                ->subject('Explore the Growth features in your Sitewell trial')
                ->greeting('Make the most of the remaining trial')
                ->line('Growth connects website health with search performance, commercial SEO opportunities, content planning, and managed improvements.')
                ->line('To prepare website changes, choose the appropriate Pixel, WordPress, or GitHub connection with help from our team.')
                ->action('Explore website content', $this->actionUrl),
            OnboardingLifecycleStep::EndingSoon => (new MailMessage)
                ->subject('Your Sitewell Growth trial ends tomorrow')
                ->greeting('One day remains in your Growth trial')
                ->line("Your trial workspace for {$website} remains available today, including its health and search performance findings.")
                ->line('Choose a package to keep the features active, or book a call if you would like help deciding what should happen next.')
                ->action('View Sitewell packages', $this->actionUrl),
            OnboardingLifecycleStep::TrialEnded => (new MailMessage)
                ->subject('Your Sitewell Growth trial has ended')
                ->greeting('Your trial has finished')
                ->line("The Growth trial for {$website} has ended, so trial-only features are now locked.")
                ->line('Your account and website information remain in Sitewell. Choose a package whenever you are ready to continue.')
                ->action('Continue with Sitewell', $this->actionUrl),
            OnboardingLifecycleStep::AdminFollowUp => (new MailMessage)
                ->subject('Onboarding follow-up: '.$this->onboardingUser->name)
                ->greeting('An onboarding lead needs follow-up')
                ->line($this->onboardingUser->name.' ('.$this->onboardingUser->email.") finished the trial for {$website} three days ago and has not converted.")
                ->line('Review their verification, call, and engagement history before following up personally.')
                ->action('Open onboarding lead', $this->actionUrl),
        };
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'message_id' => $this->message->id,
            'step' => $this->message->step->value,
        ];
    }
}
