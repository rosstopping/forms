<?php

namespace App\Console\Commands;

use App\Jobs\CheckSeoTargetKeywordRanking;
use App\Models\Website;
use App\Services\SeoIntelligence\SeoRefreshResult;
use App\Services\SeoIntelligence\SeoRefreshService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('seo:dispatch-weekly-snapshots')]
#[Description('Dispatch enabled weekly DataForSEO snapshots')]
class DispatchWeeklySeoSnapshots extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(SeoRefreshService $refresh): int
    {
        $queued = 0;
        $targetChecks = 0;
        Website::query()->where('is_active', true)->where('seo_weekly_snapshots_enabled', true)->each(function (Website $website) use ($refresh, &$queued): void {
            if ($refresh->request($website)->reason === SeoRefreshResult::REASON_QUEUED) {
                $queued++;
            }
        });
        Website::query()->where('is_active', true)->where('seo_weekly_snapshots_enabled', true)
            ->with(['seoTargetKeywords' => fn ($query) => $query->whereNull('archived_at')])
            ->each(function (Website $website) use (&$targetChecks): void {
                foreach ($website->seoTargetKeywords as $keyword) {
                    CheckSeoTargetKeywordRanking::dispatch($keyword);
                    $targetChecks++;
                }
            });
        $this->info("Queued {$queued} weekly SEO snapshot(s).");
        $this->info("Queued {$targetChecks} target keyword check(s).");

        return self::SUCCESS;
    }
}
