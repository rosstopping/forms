<?php

namespace App\Console\Commands;

use App\Jobs\SyncSearchConsoleHistory;
use App\Models\SearchConsoleConnection;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('search-console:sync-history')]
#[Description('Queue durable Search Console history synchronisation for connected websites')]
class SyncSearchConsoleHistories extends Command
{
    public function handle(): int
    {
        $queued = 0;

        SearchConsoleConnection::query()
            ->whereNotNull('property_url')
            ->each(function (SearchConsoleConnection $connection) use (&$queued): void {
                SyncSearchConsoleHistory::dispatch($connection);
                $queued++;
            });

        $this->info("Queued {$queued} Search Console history sync(s).");

        return self::SUCCESS;
    }
}
