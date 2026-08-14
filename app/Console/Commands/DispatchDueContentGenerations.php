<?php

namespace App\Console\Commands;

use App\Jobs\StartContentGeneration;
use App\Models\ContentGeneration;
use App\Models\ContentPlan;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('content:dispatch')]
#[Description('Dispatch weekly content generations that are due')]
class DispatchDueContentGenerations extends Command
{
    public function handle(): int
    {
        $dispatched = 0;
        ContentPlan::query()->where('enabled', true)
            ->with(['website.repository', 'website.searchConsoleConnection', 'creator.githubAuthorization'])
            ->each(function (ContentPlan $plan) use (&$dispatched): void {
                $localNow = now($plan->timezone);
                if ($localNow->dayOfWeek !== $plan->weekday || $localNow->hour !== $plan->hour) {
                    return;
                }
                if (! $plan->website->repository || ! $plan->website->searchConsoleConnection?->property_url || ! $plan->creator?->githubAuthorization) {
                    return;
                }
                if ($plan->generations()->whereIn('status', [ContentGeneration::STATUS_PENDING, ContentGeneration::STATUS_RUNNING])->exists()) {
                    return;
                }
                $generation = $plan->generations()->firstOrCreate(
                    ['scheduled_for' => $localNow->toDateString()],
                    ['website_repository_id' => $plan->website->repository->id, 'requested_by' => $plan->creator->id],
                );
                if (! $generation->wasRecentlyCreated) {
                    return;
                }
                $plan->update(['last_generated_at' => now()]);
                StartContentGeneration::dispatch($generation);
                $dispatched++;
            });

        $this->info("Dispatched {$dispatched} content generation(s).");

        return self::SUCCESS;
    }
}
