<?php

namespace App\Console\Commands;

use App\Jobs\SendMonthlyRankingReport;
use App\Models\Website;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('ranking-reports:dispatch-monthly')]
#[Description('Dispatch monthly SEO and Search Console ranking reports')]
class DispatchMonthlyRankingReports extends Command
{
    public function handle(): int
    {
        $queued = 0;
        Website::query()->where('is_active', true)->where('health_reports_enabled', true)
            ->where(fn ($query) => $query->whereHas('seoSnapshots')->orWhereHas('searchConsoleConnection'))
            ->each(function (Website $website) use (&$queued): void {
                SendMonthlyRankingReport::dispatch($website);
                $queued++;
            });
        $this->info("Queued {$queued} monthly ranking report(s).");

        return self::SUCCESS;
    }
}
