<?php

namespace App\Services;

use App\Models\ContentPlan;
use App\Models\Website;
use App\Support\MembershipPlan;
use Carbon\CarbonImmutable;

class ContentSchedule
{
    public function weeklyLimit(Website $website): int
    {
        $owner = $website->owner;
        if (! $website->is_active || ! $owner?->hasActiveMembership()) {
            return 0;
        }

        return match ($owner->effectiveMembershipTier()) {
            MembershipPlan::COMPLETE => 3,
            MembershipPlan::GROWTH => 1,
            default => 0,
        };
    }

    /** @return list<int> */
    public function weekdays(ContentPlan $plan): array
    {
        return array_slice(array_values(array_unique(array_map(intval(...), [$plan->weekday, ...($plan->additional_weekdays ?? [])]))), 0, $this->weeklyLimit($plan->website));
    }

    public function pauseReason(ContentPlan $plan): ?string
    {
        return match (true) {
            ! $plan->enabled => 'Scheduled content is switched off.',
            $this->weeklyLimit($plan->website) === 0 => 'Scheduled content requires an active Growth or Complete website subscription.',
            ! $plan->website->repository => 'Connect a repository to enable scheduled content.',
            ! $plan->creator?->githubAuthorization => 'Ask the Sitewell team to connect content automation.',
            ! $plan->website->isManageableBy($plan->creator) => 'Ask the Sitewell team to reconnect content automation.',
            default => null,
        };
    }

    public function occursAt(ContentPlan $plan, CarbonImmutable $at): bool
    {
        $local = $at->setTimezone($plan->timezone);

        return in_array($local->dayOfWeek, $this->weekdays($plan), true)
            && $local->hour === (int) $plan->hour && $local->minute === 0;
    }

    public function hasCapacity(ContentPlan $plan, CarbonImmutable $at): bool
    {
        $week = $at->setTimezone($plan->timezone)->startOfWeek(CarbonImmutable::MONDAY);

        return $plan->generations()->where('trigger', '!=', 'manual')
            ->whereDate('scheduled_for', '>=', $week->toDateString())
            ->whereDate('scheduled_for', '<=', $week->addDays(6)->toDateString())
            ->count() < $this->weeklyLimit($plan->website);
    }

    public function nextRunAt(ContentPlan $plan): ?CarbonImmutable
    {
        if ($this->pauseReason($plan)) {
            return null;
        }
        $now = CarbonImmutable::now($plan->timezone);
        for ($day = 0; $day < 15; $day++) {
            $candidate = $now->startOfDay()->addDays($day)->setTime((int) $plan->hour, 0);
            if ($candidate->greaterThan($now) && $this->occursAt($plan, $candidate)
                && $this->hasCapacity($plan, $candidate)
                && ! $plan->generations()->whereDate('scheduled_for', $candidate->toDateString())->exists()) {
                return $candidate->setTimezone(config('app.timezone'));
            }
        }

        return null;
    }

    public function reminderRunAt(ContentPlan $plan): ?CarbonImmutable
    {
        $now = CarbonImmutable::now($plan->timezone);
        $tomorrow = $now->addDay()->setTime((int) $plan->hour, 0);

        return ! $this->pauseReason($plan) && $now->hour === (int) $plan->hour && $now->minute === 0
            && $this->occursAt($plan, $tomorrow) && $this->hasCapacity($plan, $tomorrow)
            && ! $plan->generations()->whereDate('scheduled_for', $tomorrow->toDateString())->exists()
            ? $tomorrow : null;
    }
}
