<?php

namespace App\Services;

use App\Models\ContentPlan;
use App\Models\Website;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class DashboardSchedule
{
    /**
     * @param  Collection<int, Website>  $websites
     * @return Collection<int, array{type: string, website: Website, next_run_at: CarbonImmutable, detail: string}>
     */
    public function forWebsites(Collection $websites): Collection
    {
        return $websites->flatMap(function (Website $website): array {
            $items = [];

            if ($website->health_reports_enabled) {
                $items[] = [
                    'type' => 'Site audit',
                    'website' => $website,
                    'next_run_at' => $this->nextAuditAt($website),
                    'detail' => 'Checked by the daily audit dispatcher at 06:00',
                ];
            }

            if ($website->contentPlan?->enabled) {
                $items[] = [
                    'type' => 'Content queue',
                    'website' => $website,
                    'next_run_at' => $this->nextContentAt($website->contentPlan),
                    'detail' => 'Weekly content preparation in '.$website->contentPlan->timezone,
                ];
            }

            return $items;
        })->sortBy('next_run_at')->values();
    }

    private function nextAuditAt(Website $website): CarbonImmutable
    {
        $now = CarbonImmutable::now(config('app.timezone'));
        $lastCompletedAt = $website->latestHealthReport?->completed_at;
        $dueAt = $lastCompletedAt
            ? CarbonImmutable::instance($lastCompletedAt)->addDays((int) config('forms.health_reports.frequency_days'))
            : $now;
        $nextRunAt = $dueAt->startOfDay()->setHour(6);

        if ($nextRunAt->lessThan($dueAt)) {
            $nextRunAt = $nextRunAt->addDay();
        }

        if ($nextRunAt->lessThanOrEqualTo($now)) {
            $nextRunAt = $now->startOfDay()->setHour(6);

            if ($nextRunAt->lessThanOrEqualTo($now)) {
                $nextRunAt = $nextRunAt->addDay();
            }
        }

        return $nextRunAt;
    }

    private function nextContentAt(ContentPlan $plan): CarbonImmutable
    {
        $now = CarbonImmutable::now($plan->timezone);
        $daysUntilRun = ((int) $plan->weekday - $now->dayOfWeek + 7) % 7;
        $nextRunAt = $now->startOfDay()->addDays($daysUntilRun)->setHour((int) $plan->hour);

        if ($nextRunAt->lessThanOrEqualTo($now)) {
            $nextRunAt = $nextRunAt->addWeek();
        }

        return $nextRunAt->setTimezone(config('app.timezone'));
    }
}
