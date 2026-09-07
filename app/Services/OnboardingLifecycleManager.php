<?php

namespace App\Services;

use App\Enums\OnboardingLifecycleStep;
use App\Models\OnboardingLifecycleMessage;
use App\Models\User;
use App\Notifications\OnboardingLifecycleNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

class OnboardingLifecycleManager
{
    public function synchronize(User $user): void
    {
        if (! $this->isOnboardingUser($user)) {
            return;
        }

        $trialStartsAt = $user->onboarding_trial_ends_at->copy()->subDays(14);

        foreach (OnboardingLifecycleStep::cases() as $step) {
            $scheduledFor = match ($step) {
                OnboardingLifecycleStep::Welcome => $trialStartsAt,
                OnboardingLifecycleStep::TrialEnded => $user->onboarding_trial_ends_at,
                OnboardingLifecycleStep::AdminFollowUp => $user->onboarding_trial_ends_at->copy()->addDays(3),
                default => $trialStartsAt->copy()->addDays($step->day())->setTime(9, 0),
            };
            $message = $user->onboardingLifecycleMessages()->firstOrCreate(
                ['step' => $step],
                [
                    'audience' => $step->audience(),
                    'scheduled_for' => $scheduledFor,
                ],
            );

            if ($message->wasRecentlyCreated && $scheduledFor->isBefore(now()->subDay())) {
                $message->update(['suppressed_at' => now()]);
            }
        }
    }

    public function dispatchDue(User $user): int
    {
        $this->synchronize($user);

        if (! $this->isOnboardingUser($user)) {
            return 0;
        }

        if ($user->membership_status === 'active') {
            $user->onboardingLifecycleMessages()
                ->whereNull('queued_at')
                ->whereNull('suppressed_at')
                ->update(['suppressed_at' => now()]);

            return 0;
        }

        $dispatched = 0;
        $messages = $user->onboardingLifecycleMessages()
            ->whereNull('queued_at')
            ->whereNull('suppressed_at')
            ->where('scheduled_for', '<=', now())
            ->oldest('scheduled_for')
            ->get();

        foreach ($messages as $message) {
            if ($this->shouldSuppress($user, $message->step)) {
                $message->update(['suppressed_at' => now()]);

                continue;
            }

            $message->update(['queued_at' => now()]);
            $notification = (new OnboardingLifecycleNotification(
                $message,
                $user,
                $this->trackedActionUrl($message),
            ))->afterCommit();

            if ($message->step === OnboardingLifecycleStep::AdminFollowUp) {
                Notification::send(User::query()->where('role', User::ROLE_ADMIN)->get(), $notification);
            } else {
                $user->notify($notification);
            }

            $dispatched++;
        }

        return $dispatched;
    }

    public function destination(OnboardingLifecycleMessage $message): string
    {
        $user = $message->user;
        $website = $user->onboardingAudit?->website;

        return match ($message->step) {
            OnboardingLifecycleStep::SearchConsole => $website
                ? route('admin.search-console.connect', $website)
                : route('admin.dashboard'),
            OnboardingLifecycleStep::BookCall => route('admin.onboarding-call'),
            OnboardingLifecycleStep::Progress => $website
                ? route('admin.websites.section', [$website, 'health'])
                : route('admin.dashboard'),
            OnboardingLifecycleStep::GrowthFeatures => $website
                ? route('admin.websites.section', [$website, 'content'])
                : route('admin.dashboard'),
            OnboardingLifecycleStep::EndingSoon, OnboardingLifecycleStep::TrialEnded => route('admin.billing.index'),
            OnboardingLifecycleStep::AdminFollowUp => route('admin.onboarding.index', ['search' => $user->email]),
            default => route('admin.dashboard'),
        };
    }

    private function trackedActionUrl(OnboardingLifecycleMessage $message): string
    {
        return URL::temporarySignedRoute(
            'onboarding-lifecycle.click',
            now()->addDays(45),
            ['onboardingLifecycleMessage' => $message],
        );
    }

    private function shouldSuppress(User $user, OnboardingLifecycleStep $step): bool
    {
        if ($user->membership_status === 'active' || $user->onboarding_status !== 'trial_active') {
            return true;
        }

        if ($step === OnboardingLifecycleStep::SearchConsole) {
            return $user->onboardingAudit?->website?->searchConsoleConnection !== null;
        }

        if ($step === OnboardingLifecycleStep::BookCall) {
            return $user->onboarding_call_booked_at !== null || $user->onboarding_call_completed_at !== null;
        }

        return false;
    }

    private function isOnboardingUser(User $user): bool
    {
        return $user->onboarding_status === 'trial_active'
            && $user->onboarding_trial_ends_at !== null
            && $user->onboardingAudit !== null;
    }
}
