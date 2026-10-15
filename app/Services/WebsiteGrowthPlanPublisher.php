<?php

namespace App\Services;

use App\Mail\WebsiteGrowthPlanReady;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteGrowthPlan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class WebsiteGrowthPlanPublisher
{
    /** @return array<int, string> */
    public function recipients(Website $website): array
    {
        $website->loadMissing(['owner', 'members']);

        return $website->members->concat([$website->owner])->filter(fn (?User $user): bool => $user !== null && ! $user->isAdmin())
            ->pluck('email')->filter()->unique()->values()->all();
    }

    public function send(WebsiteGrowthPlan $plan, string $recipient): void
    {
        DB::transaction(function () use ($plan, $recipient): void {
            $locked = WebsiteGrowthPlan::query()->lockForUpdate()->findOrFail($plan->id);
            if (! $locked->isReady()) {
                throw ValidationException::withMessages(['recipient' => 'Wait for the plan to finish generating before emailing it.']);
            }
            if ($locked->email_queued_at !== null) {
                return;
            }
            if (! in_array($recipient, $this->recipients($locked->website), true)) {
                throw ValidationException::withMessages(['recipient' => 'Choose a customer attached to this website.']);
            }
            $locked->update(['email_queued_at' => now(), 'email_recipient' => $recipient]);
            Mail::to($recipient)->queue(new WebsiteGrowthPlanReady($locked->report['website_name'], $locked->shareUrl()));
        });
    }
}
