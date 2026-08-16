<?php

namespace App\Jobs;

use App\Models\SearchConsoleConnection;
use App\Services\SearchConsoleHistoryStore;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncSearchConsoleHistory implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 240;

    /** @var array<int, int> */
    public array $backoff = [60, 300];

    public function __construct(public SearchConsoleConnection $searchConsoleConnection) {}

    public function handle(SearchConsoleHistoryStore $history): void
    {
        $history->syncTracked($this->searchConsoleConnection);
    }
}
