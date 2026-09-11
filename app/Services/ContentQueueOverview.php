<?php

namespace App\Services;

use App\Models\ContentGeneration;
use App\Models\Website;
use App\Support\WebsiteNavigation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class ContentQueueOverview
{
    public function __construct(private ContentSchedule $schedule, private ContentWorkSelector $work) {}

    /**
     * @param  Collection<int, Website>  $websites
     * @return Collection<int, array{website: Website, count: int, state: string, reason: string, next_run_at: ?CarbonImmutable, action: string, url: string}>
     */
    public function forWebsites(Collection $websites): Collection
    {
        return $websites->filter(fn (Website $website): bool => $website->pending_content_requests_count > 0)
            ->map(fn (Website $website): array => $this->forWebsite($website))
            ->sortBy(fn (array $row): int => match ($row['state']) {
                'Needs setup' => 0,
                'Paused' => 1,
                default => 2,
            })->values();
    }

    /** @return array{website: Website, count: int, state: string, reason: string, next_run_at: ?CarbonImmutable, action: string, url: string} */
    private function forWebsite(Website $website): array
    {
        $row = [
            'website' => $website,
            'count' => (int) $website->pending_content_requests_count,
            'state' => 'Needs setup',
            'reason' => '',
            'next_run_at' => null,
            'action' => 'Configure schedule',
            'url' => WebsiteNavigation::routeFor($website, 'content'),
        ];
        $plan = $website->contentPlan;

        if (! $website->is_active) {
            return array_replace($row, ['reason' => 'Website is inactive.', 'action' => 'View settings', 'url' => WebsiteNavigation::routeFor($website, 'settings')]);
        }

        if ($this->schedule->weeklyLimit($website) === 0) {
            return array_replace($row, [
                'reason' => 'An active Growth or Complete subscription is required.',
                'action' => $website->owner ? 'View account' : 'View settings',
                'url' => $website->owner ? route('admin.users.edit', $website->owner) : WebsiteNavigation::routeFor($website, 'settings'),
            ]);
        }

        if (! $plan) {
            return array_replace($row, ['reason' => 'No content schedule configured.']);
        }

        if ($reason = $this->schedule->pauseReason($plan)) {
            $action = match (true) {
                ! $plan->enabled => 'Configure schedule',
                ! $plan->website->repository => 'Connect GitHub',
                default => 'Connect automation',
            };

            return array_replace($row, [
                'reason' => $reason,
                'action' => $action,
                'url' => $action === 'Connect GitHub' ? route('admin.website-repositories.create', $website) : $row['url'],
            ]);
        }

        if ($plan->generations()->whereIn('status', [ContentGeneration::STATUS_PENDING, ContentGeneration::STATUS_RUNNING])->exists()) {
            return array_replace($row, ['state' => 'Paused', 'reason' => 'A content generation is queued or running.', 'action' => 'View generation']);
        }

        if ($this->work->pendingRequestsBlockedByReview($plan)) {
            return array_replace($row, ['state' => 'Paused', 'reason' => 'Waiting requests overlap with an open pull request.', 'action' => 'Review pull requests']);
        }

        $nextRun = $this->schedule->nextRunAt($plan);

        return array_replace($row, [
            'state' => $nextRun ? 'Scheduled' : 'Paused',
            'reason' => $nextRun ? 'Next content preparation run.' : 'No available run in the next 15 days. Check the schedule and existing generations.',
            'next_run_at' => $nextRun,
            'action' => $nextRun ? 'View queue' : 'Check schedule',
        ]);
    }
}
