<?php

namespace App\Console\Commands;

use App\Jobs\ResearchContentKeywords;
use App\Jobs\ResearchContentTrends;
use App\Models\ContentPlan;
use App\Services\ContentDiscovery;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('content:discover')]
#[Description('Discover deduplicated content candidates from saved provider research without Copilot')]
class DiscoverContentOpportunities extends Command
{
    public function handle(ContentDiscovery $discovery): int
    {
        $created = 0;
        ContentPlan::where(fn ($query) => $query->where('discovery_enabled', true)->orWhere('trend_research_enabled', true)->orWhere('keyword_research_enabled', true))->each(function (ContentPlan $plan) use ($discovery, &$created): void {
            try {
                $created += $discovery->discover($plan);
                if ($plan->keyword_research_enabled && (! $plan->keyword_researched_at || $plan->keyword_researched_at->lte(now()->subDays(7)))) {
                    ResearchContentKeywords::dispatch($plan);
                }
                if ($plan->trend_research_enabled && (! $plan->trend_researched_at || $plan->trend_researched_at->lte(now()->subDays(7)))) {
                    ResearchContentTrends::dispatch($plan);
                }
            } catch (Throwable $exception) {
                report($exception);
                $this->warn("Could not discover content for website {$plan->website_id}.");
            }
        });
        $this->info("Discovered {$created} content opportunities.");

        return self::SUCCESS;
    }
}
