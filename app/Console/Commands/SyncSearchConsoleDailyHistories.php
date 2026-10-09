<?php

namespace App\Console\Commands;

use App\Jobs\SyncSearchConsoleDailyHistory;
use App\Models\SearchConsoleConnection;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('search-console:sync-daily')]
#[Description('Queue daily Search Console reporting imports without paid SEO checks')]
class SyncSearchConsoleDailyHistories extends Command
{
    public function handle(): int
    {
        SearchConsoleConnection::whereNotNull('property_url')->whereNull('access_denied_at')
            ->whereHas('website', fn ($query) => $query->where('is_active', true))
            ->chunkById(100, function ($connections): void {
                foreach ($connections as $connection) {
                    SyncSearchConsoleDailyHistory::dispatch($connection);
                }
            });

        return self::SUCCESS;
    }
}
