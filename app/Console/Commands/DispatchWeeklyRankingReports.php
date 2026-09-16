<?php

namespace App\Console\Commands;

use App\Jobs\SendWeeklyRankingReport;
use App\Models\Website;
use App\Services\AiVisibilityScheduler;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('ranking-reports:dispatch')]
#[Description('Dispatch weekly SEO and Search Console ranking reports')]
class DispatchWeeklyRankingReports extends Command
{
    public function handle(AiVisibilityScheduler $aiVisibility): int
    {
        $aiVisibility->dispatchDue();
        $queued = 0;
        Website::query()
            ->where('is_active', true)
            ->where('weekly_ranking_reports_enabled', true)
            ->where(function ($query): void {
                $query->whereExists(fn ($query) => $query->selectRaw('1')->from('ai_visibility_results')->whereColumn('ai_visibility_results.website_id', 'websites.id')->where('ai_visibility_results.status', 'completed'))->orWhereHas('seoSnapshots')->orWhereHas('searchConsoleConnection')->orWhereHas('healthReports')->orWhereHas('businessProfileConnection', fn ($query) => $query->whereNotNull('location_name'))->orWhereHas('optimisations')->orWhereHas('contentPlan')->orWhereHas('seoTargetKeywords', fn ($query) => $query->whereNull('archived_at'));
            })
            ->each(function (Website $website) use (&$queued): void {
                SendWeeklyRankingReport::dispatch($website);
                $queued++;
            });

        $this->info("Queued {$queued} weekly ranking report(s).");

        return self::SUCCESS;
    }
}
