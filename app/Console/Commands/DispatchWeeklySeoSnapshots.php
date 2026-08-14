<?php

namespace App\Console\Commands;

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
        Website::query()->where('is_active', true)->where('seo_weekly_snapshots_enabled', true)->each(function (Website $website) use ($refresh, &$queued): void {
            if ($refresh->request($website)->reason === SeoRefreshResult::REASON_QUEUED) {
                $queued++;
            }
        });
        $this->info("Queued {$queued} weekly SEO snapshot(s).");

        return self::SUCCESS;
    }
}
