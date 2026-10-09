<?php

namespace App\Jobs;

use App\Models\SearchConsoleConnection;
use App\Services\SearchConsoleDailyHistory;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncSearchConsoleDailyHistory implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 3600;

    public int $tries = 3;

    public int $timeout = 120;

    /** @var array<int, int> */
    public array $backoff = [60, 300];

    public function __construct(public SearchConsoleConnection $searchConsoleConnection) {}

    public function uniqueId(): string
    {
        return (string) $this->searchConsoleConnection->id;
    }

    public function handle(SearchConsoleDailyHistory $history): void
    {
        $history->sync($this->searchConsoleConnection);
    }
}
