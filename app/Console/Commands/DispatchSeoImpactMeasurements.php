<?php

namespace App\Console\Commands;

use App\Jobs\MeasureSeoImpact;
use App\Models\SeoImpact;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('seo:measure-impacts')]
#[Description('Queue due Search Console baselines and SEO impact reviews')]
class DispatchSeoImpactMeasurements extends Command
{
    public function handle(): int
    {
        $queued = 0;
        SeoImpact::whereIn('status', ['planned', 'measuring'])->where('next_measurement_at', '<=', now())
            ->whereHas('website', fn ($query) => $query->where('is_active', true))
            ->with('website.owner')->chunkById(100, function ($impacts) use (&$queued): void {
                foreach ($impacts as $impact) {
                    if ($impact->website->owner?->hasMembershipFeature('growth') || $impact->website->owner?->isAdmin()) {
                        MeasureSeoImpact::dispatch($impact);
                        $queued++;
                    }
                }
            });
        $this->info("Queued {$queued} SEO impact measurement(s).");

        return self::SUCCESS;
    }
}
