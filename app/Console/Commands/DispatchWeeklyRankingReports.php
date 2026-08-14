<?php

namespace App\Console\Commands;

use App\Jobs\SendWeeklyRankingReport;
use App\Models\Website;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('ranking-reports:dispatch')]
#[Description('Dispatch weekly SEO and Search Console ranking reports')]
class DispatchWeeklyRankingReports extends Command
{
    public function handle(): int
    {
        $queued = 0;
        Website::query()
            ->where('is_active', true)
            ->where('health_reports_enabled', true)
            ->where(function ($query): void {
                $query->whereHas('seoSnapshots')->orWhereHas('searchConsoleConnection');
            })
            ->each(function (Website $website) use (&$queued): void {
                SendWeeklyRankingReport::dispatch($website);
                $queued++;
            });

        $this->info("Queued {$queued} weekly ranking report(s).");

        return self::SUCCESS;
    }
}
