<?php

namespace App\Jobs;

use App\Models\ContentPlan;
use App\Services\ContentKeywordResearch;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ResearchContentKeywords implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 60;

    public int $uniqueFor = 600;

    public function __construct(public ContentPlan $plan) {}

    public function uniqueId(): string
    {
        return (string) $this->plan->id;
    }

    public function handle(ContentKeywordResearch $research): void
    {
        $research->research($this->plan);
    }

    public function failed(?Throwable $exception): void
    {
        $plan = $this->plan->fresh();
        if (($plan?->keyword_research['status'] ?? null) === 'pending') {
            $plan->update(['keyword_research' => [...$plan->keyword_research, 'status' => 'failed',
                'error' => 'Keyword discovery did not finish. The weekly reservation is retained to avoid duplicate purchases.']]);
        }
    }
}
