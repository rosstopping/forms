<?php

namespace App\Services;

use App\Models\ContentGeneration;
use App\Models\RemediationRun;
use App\Models\WebsiteHealthReport;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class DashboardWorkActivity
{
    /**
     * @param  list<int>  $websiteIds
     * @return array{attention: Collection, completed: Collection}
     */
    public function forWebsites(array $websiteIds): array
    {
        $sources = [
            WebsiteHealthReport::query()->whereIn('website_id', $websiteIds)->with('website:id,name')
                ->select(['id', 'website_id', 'status', 'error', 'created_at', 'updated_at', 'started_at', 'completed_at']),
            ContentGeneration::query()->whereHas('plan', fn ($query) => $query->whereIn('website_id', $websiteIds))->with('plan.website:id,name')
                ->select(['id', 'content_plan_id', 'status', 'error', 'created_at', 'updated_at', 'started_at', 'completed_at', 'merged_at', 'scheduled_for']),
            RemediationRun::query()->whereHas('report', fn ($query) => $query->whereIn('website_id', $websiteIds))->with('report.website:id,name')
                ->select(['id', 'website_health_report_id', 'status', 'error', 'created_at', 'updated_at', 'started_at', 'completed_at', 'merged_at']),
        ];
        $attention = collect();
        $completed = collect();

        foreach ($sources as $source) {
            $isAudit = $source->getModel() instanceof WebsiteHealthReport;
            $runningCutoff = $isAudit ? now()->subMinutes(15) : now()->subHours(2);
            $attentionQuery = (clone $source)->where(function (Builder $query) use ($runningCutoff): void {
                $query->where('status', 'failed')
                    ->orWhere(fn ($query) => $query->whereIn('status', ['pending', 'awaiting_runner'])
                        ->where('created_at', '<', now()->subHour())
                        ->when($query->getModel() instanceof ContentGeneration, fn ($query) => $query->whereDate('scheduled_for', '<=', today())))
                    ->orWhere(fn ($query) => $query->where('status', 'running')
                        ->whereRaw('COALESCE(started_at, created_at) < ?', [$runningCutoff]));
            });
            $attention = $attention->concat($attentionQuery->latest('updated_at')->latest('id')->limit(10)->get()
                ->map(fn ($work): array => $this->item($work, false)));

            $completedAt = $isAudit ? 'completed_at' : 'COALESCE(merged_at, completed_at)';
            $completed = $completed->concat((clone $source)->where('status', 'completed')
                ->whereRaw($completedAt.' IS NOT NULL')
                ->orderByRaw($completedAt.' DESC')->latest('id')->limit(10)->get()
                ->map(fn ($work): array => $this->item($work, true)));
        }

        return [
            'attention' => $attention->sortByDesc('at')->take(10)->values(),
            'completed' => $completed->sortByDesc('at')->take(10)->values(),
        ];
    }

    /** @return array{website: string, type: string, status: string, reason: string, at: CarbonInterface, age_at: CarbonInterface, url: string} */
    private function item(WebsiteHealthReport|ContentGeneration|RemediationRun $work, bool $completed): array
    {
        $website = match (true) {
            $work instanceof WebsiteHealthReport => $work->website,
            $work instanceof ContentGeneration => $work->plan->website,
            default => $work->report->website,
        };
        $type = match (true) {
            $work instanceof WebsiteHealthReport => 'Health report',
            $work instanceof ContentGeneration => 'Content generation',
            default => 'Website fixes',
        };
        $isMerged = ! $work instanceof WebsiteHealthReport && $work->merged_at !== null;
        $at = $completed ? ($isMerged ? $work->merged_at : $work->completed_at) : $work->updated_at;

        return [
            'website' => $website->name,
            'type' => $type,
            'status' => $completed ? ($isMerged ? 'Merged' : 'Completed') : ($work->status === 'failed' ? 'Failed' : 'Possibly stalled'),
            'reason' => $completed ? 'Finished work is ready to view.' : ($work->status === 'failed'
                ? ($work->error ?: 'No failure details were recorded.')
                : ($work->status === 'running' ? 'Running longer than expected. Check its progress.' : 'Queued for over an hour without starting.')),
            'at' => $at,
            'age_at' => $completed || $work->status === 'failed' ? $at : ($work->status === 'running' ? ($work->started_at ?? $work->created_at) : $work->created_at),
            'url' => $work instanceof ContentGeneration
                ? route('admin.websites.section', [$website, 'content'])
                : route('admin.website-health-reports.show', [$website, $work instanceof WebsiteHealthReport ? $work : $work->report]),
        ];
    }
}
