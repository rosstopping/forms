<?php

namespace App\Console\Commands;

use App\Models\ContentPlan;
use App\Services\CompetitorResearchAutomation;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('competitors:dispatch-research')]
#[Description('Dispatch opted-in competitor research within website plan limits')]
class DispatchDueCompetitorResearch extends Command
{
    public function handle(CompetitorResearchAutomation $automation): int
    {
        $dispatched = 0;
        ContentPlan::query()->whereIn('competitor_research_mode', ['research', 'drafts'])
            ->each(function (ContentPlan $plan) use ($automation, &$dispatched): void {
                try {
                    $dispatched += $automation->dispatch($plan);
                } catch (Throwable $exception) {
                    report($exception);
                    $this->warn("Could not dispatch competitor research for website {$plan->website_id}.");
                }
            });
        $this->info("Scheduled research for {$dispatched} competitor(s).");

        return self::SUCCESS;
    }
}
