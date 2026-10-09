<?php

namespace App\Jobs;

use App\Models\SeoTargetKeyword;
use App\Services\SeoTargetKeywordRankChecker;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CheckSeoTargetKeywordRanking implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public int $uniqueFor = 600;

    public function __construct(public SeoTargetKeyword $keyword) {}

    public function uniqueId(): string
    {
        return (string) $this->keyword->id;
    }

    /**
     * Execute the job.
     */
    public function handle(SeoTargetKeywordRankChecker $checker): void
    {
        $keyword = $this->keyword->fresh();
        if ($keyword && $keyword->archived_at === null) {
            $checker->check($keyword);
            DetectSeoWins::dispatch($keyword->website);
        }
    }
}
