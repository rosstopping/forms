<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\OnboardingLifecycleManager;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('onboarding:dispatch-lifecycle')]
#[Description('Dispatch due onboarding trial messages and follow-up reminders')]
class DispatchDueOnboardingLifecycleMessages extends Command
{
    public function handle(OnboardingLifecycleManager $lifecycle): int
    {
        $dispatched = 0;

        User::query()
            ->with(['onboardingAudit.website.searchConsoleConnection'])
            ->where('onboarding_status', 'trial_active')
            ->whereNotNull('onboarding_trial_ends_at')
            ->whereHas('websiteAudits', fn ($query) => $query->whereNotNull('claimed_at'))
            ->chunkById(100, function ($users) use ($lifecycle, &$dispatched): void {
                foreach ($users as $user) {
                    $dispatched += $lifecycle->dispatchDue($user);
                }
            });

        $this->info("Dispatched {$dispatched} onboarding lifecycle message(s).");

        return self::SUCCESS;
    }
}
