<?php

namespace App\Console\Commands;

use App\Jobs\DiscoverSearchOpportunities;
use App\Models\SearchConsoleConnection;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('search-opportunities:dispatch')]
#[Description('Dispatch weekly Search Console opportunity discovery jobs')]
class DispatchDueSearchOpportunities extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dispatched = 0;

        SearchConsoleConnection::query()
            ->whereNotNull('property_url')
            ->whereHas('website', fn ($query) => $query->where('is_active', true))
            ->where(fn ($query) => $query->whereNull('opportunities_checked_at')->orWhere('opportunities_checked_at', '<=', now()->subWeek()))
            ->each(function (SearchConsoleConnection $connection) use (&$dispatched): void {
                DiscoverSearchOpportunities::dispatch($connection);
                $dispatched++;
            });

        $this->info("Dispatched {$dispatched} search opportunity job(s).");

        return self::SUCCESS;
    }
}
