<?php

namespace App\Jobs;

use App\Models\WebsiteGrowthPlan;
use App\Services\WebsiteGrowthPlanBuilder;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class GenerateWebsiteGrowthPlan implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 150;

    public int $uniqueFor = 900;

    public bool $failOnTimeout = true;

    public function __construct(public WebsiteGrowthPlan $plan) {}

    public function uniqueId(): string
    {
        return (string) $this->plan->id;
    }

    public function handle(WebsiteGrowthPlanBuilder $builder): void
    {
        $claimed = WebsiteGrowthPlan::query()->whereKey($this->plan->id)->where('status', WebsiteGrowthPlan::STATUS_QUEUED)
            ->update(['status' => WebsiteGrowthPlan::STATUS_RUNNING, 'started_at' => now()]);
        if (! $claimed) {
            return;
        }
        $report = $builder->generate($this->plan->fresh()->brief);
        $this->plan->update(['report' => $report, 'status' => WebsiteGrowthPlan::STATUS_COMPLETED,
            'completed_at' => now(), 'published_at' => now(), 'generation_error' => null]);
    }

    public function failed(?Throwable $exception): void
    {
        WebsiteGrowthPlan::query()->whereKey($this->plan->id)->whereIn('status', [WebsiteGrowthPlan::STATUS_QUEUED, WebsiteGrowthPlan::STATUS_RUNNING])
            ->update(['status' => WebsiteGrowthPlan::STATUS_FAILED, 'completed_at' => now(),
                'generation_error' => 'We could not generate this plan. Please try generating it again.']);
    }
}
