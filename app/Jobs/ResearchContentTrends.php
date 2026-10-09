<?php

namespace App\Jobs;

use App\Models\ContentPlan;
use App\Services\ContentTrendResearch;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ResearchContentTrends implements ShouldBeUnique, ShouldQueue
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

    public function handle(ContentTrendResearch $research): void
    {
        $research->research($this->plan);
    }
}
