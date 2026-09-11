<?php

namespace App\Console\Commands;

use App\Jobs\StartContentGeneration;
use App\Models\ContentGeneration;
use App\Models\ContentPlan;
use App\Services\ContentSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('content:dispatch')]
#[Description('Dispatch scheduled content generations that are due')]
class DispatchDueContentGenerations extends Command
{
    public function handle(ContentSchedule $schedule): int
    {
        $dispatched = 0;
        ContentPlan::query()->where('enabled', true)->each(function (ContentPlan $candidate) use ($schedule, &$dispatched): void {
            DB::transaction(function () use ($candidate, $schedule, &$dispatched): void {
                $plan = ContentPlan::query()->lockForUpdate()->findOrFail($candidate->id);
                $now = CarbonImmutable::now($plan->timezone);
                if ($schedule->pauseReason($plan) || ! $schedule->occursAt($plan, $now) || ! $schedule->hasCapacity($plan, $now)) {
                    return;
                }
                if ($plan->generations()->whereIn('status', [ContentGeneration::STATUS_PENDING, ContentGeneration::STATUS_RUNNING])->exists()) {
                    return;
                }
                if ($plan->generations()->whereDate('scheduled_for', $now->toDateString())->exists()) {
                    return;
                }
                $generation = $plan->generations()->firstOrCreate(
                    ['scheduled_for' => $now->toDateString()],
                    ['trigger' => 'scheduled', 'website_repository_id' => $plan->website->repository->id, 'requested_by' => $plan->creator->id],
                );
                if ($generation->wasRecentlyCreated) {
                    $plan->update(['last_generated_at' => now()]);
                    StartContentGeneration::dispatch($generation)->afterCommit();
                    $dispatched++;
                }
            });
        });

        $this->info("Dispatched {$dispatched} content generation(s).");

        return self::SUCCESS;
    }
}
