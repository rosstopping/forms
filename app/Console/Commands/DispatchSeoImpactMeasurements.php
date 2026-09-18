<?php

namespace App\Console\Commands;

use App\Jobs\MeasureSeoImpact;
use App\Jobs\VerifySeoImpact;
use App\Models\SeoImpact;
use App\Services\SeoImpactAutomation;
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
        SeoImpact::where('automated', false)->whereIn('status', ['measuring', 'review_required'])->whereNotNull('live_at')
            ->chunkById(100, function ($impacts): void {
                foreach ($impacts as $impact) {
                    $updates = ['automated' => true, 'verification_status' => 'pending', 'next_verification_at' => now(), 'next_measurement_at' => now(), 'status' => 'measuring'];
                    $review = $impact->reviews()->reorder('checkpoint', 'desc')->first();
                    if ($review && $impact->status === 'review_required') {
                        $updates = [...$updates, ...app(SeoImpactAutomation::class)->checkpointUpdates($impact, $review->assessment, $review->baseline, $review->measurement)];
                    }
                    $impact->update($updates);
                }
            });
        SeoImpact::where('status', 'planned')->whereHas('generation', fn ($query) => $query->whereNotNull('merged_at'))
            ->with('generation.plan.website')->chunkById(100, function ($impacts): void {
                foreach ($impacts as $impact) {
                    app(SeoImpactAutomation::class)->generationMerged($impact->generation);
                }
            });
        SeoImpact::where('next_verification_at', '<=', now())->whereNotIn('status', ['cancelled'])
            ->whereHas('website', fn ($query) => $query->where('is_active', true))
            ->with('website.owner')->chunkById(100, function ($impacts): void {
                foreach ($impacts as $impact) {
                    if ($impact->website->owner?->hasMembershipFeature('growth') || $impact->website->owner?->isAdmin()) {
                        VerifySeoImpact::dispatch($impact);
                    }
                }
            });
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
