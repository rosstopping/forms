<?php

namespace App\Jobs;

use App\Models\Website;
use App\Services\SeoIntelligence\SeoHistoryService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class BackfillSeoHistory implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public int $tries = 3;

    public int $uniqueFor = 3600;

    public function __construct(public Website $website) {}

    public function uniqueId(): string
    {
        return (string) $this->website->id;
    }

    /**
     * Execute the job.
     */
    public function handle(SeoHistoryService $history): void
    {
        if ($this->website->seo_weekly_snapshots_enabled && ! $this->website->seo_history_backfilled_at) {
            $history->backfill($this->website);
        }
    }
}
