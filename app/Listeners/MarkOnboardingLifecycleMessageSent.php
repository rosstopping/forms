<?php

namespace App\Listeners;

use App\Notifications\OnboardingLifecycleNotification;
use Illuminate\Notifications\Events\NotificationSent;

class MarkOnboardingLifecycleMessageSent
{
    public function handle(NotificationSent $event): void
    {
        if ($event->channel === 'mail' && $event->notification instanceof OnboardingLifecycleNotification) {
            $event->notification->message->update(['sent_at' => now()]);
        }
    }
}
