<?php

namespace App\Jobs;

use App\Models\ContentGeneration;
use App\Models\ContentPlan;
use App\Services\BacklinkContentContext;
use App\Services\CompetitorContentContext;
use App\Services\ContentGenerationPromptGenerator;
use App\Services\ContentSchedule;
use App\Services\ContentWorkSelector;
use App\Services\CopilotAgentClient;
use App\Services\SearchConsoleClient;
use App\Services\SeoTargetKeywordSelector;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Throwable;

class StartContentGeneration implements ShouldBeEncrypted, ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 90;

    public int $uniqueFor = 3600;

    /** @var array<int, int> */
    public array $backoff = [60, 300];

    public function __construct(public ContentGeneration $generation) {}

    public function uniqueId(): string
    {
        return (string) $this->generation->id;
    }

    public function handle(SearchConsoleClient $searchConsole, ContentGenerationPromptGenerator $prompts, CopilotAgentClient $copilot, ?SeoTargetKeywordSelector $targets = null): void
    {
        $targets ??= app(SeoTargetKeywordSelector::class);
        $claimed = DB::transaction(function (): bool {
            $plan = ContentPlan::query()->lockForUpdate()->findOrFail($this->generation->content_plan_id);
            $this->generation->refresh();
            if ($this->generation->copilot_task_id || $this->generation->status !== ContentGeneration::STATUS_PENDING) {
                return false;
            }
            if ($plan->generations()->whereKeyNot($this->generation->id)->where('status', ContentGeneration::STATUS_RUNNING)->exists()) {
                $this->skipGeneration('Another content generation is still running.');

                return false;
            }
            $this->generation->update(['status' => ContentGeneration::STATUS_RUNNING, 'started_at' => now()]);

            return true;
        });
        if (! $claimed) {
            return;
        }

        $this->generation->loadMissing(['plan.website.searchConsoleConnection', 'repository', 'requester.githubAuthorization']);
        $connection = $this->generation->plan->website->searchConsoleConnection;
        $authorization = $this->generation->requester?->githubAuthorization;

        if (! $authorization) {
            $this->failGeneration('GitHub automation must remain authorized.');

            return;
        }

        if ($this->generation->trigger === 'scheduled') {
            $plan = $this->generation->plan;
            $schedule = app(ContentSchedule::class);
            $reason = $schedule->pauseReason($plan);
            if (! $reason && (! in_array($this->generation->scheduled_for->dayOfWeek, $schedule->weekdays($plan), true)
                || $this->generation->scheduled_for->toDateString() !== now($plan->timezone)->toDateString())) {
                $reason = 'The scheduled day has changed or this run has expired.';
            }
            $week = $this->generation->scheduled_for->copy()->startOfWeek(Carbon::MONDAY);
            if (! $reason && $plan->generations()->where('trigger', '!=', 'manual')
                ->where('id', '<', $this->generation->id)
                ->whereDate('scheduled_for', '>=', $week->toDateString())
                ->whereDate('scheduled_for', '<=', $week->copy()->addDays(6)->toDateString())
                ->count() >= $schedule->weeklyLimit($plan->website)) {
                $reason = 'The weekly content allowance has been used.';
            }
            if ($reason) {
                $this->skipGeneration($reason);

                return;
            }
            $work = app(ContentWorkSelector::class)->select($this->generation);
            $contentRequests = $work['requests'];
            if ($contentRequests->isEmpty() && ! $work['target']) {
                $this->skipGeneration('No eligible work: add a content request or target keyword, or wait for recent changes and open reviews.');

                return;
            }
            $this->generation->update(['seo_target_keyword_id' => $work['target']?->id, 'target_keyword_context' => $work['snapshot']]);
        } elseif ($this->generation->trigger === 'manual') {
            $work = app(ContentWorkSelector::class)->select($this->generation, applyCooldown: false);
            $contentRequests = $work['requests'];
            if ($contentRequests->isEmpty() && ! $work['target']
                && ($work['snapshot'] !== [] || $this->generation->plan->website->contentRequests()->whereNull('picked_up_at')->exists())) {
                $this->skipGeneration('The available work is already awaiting pull request review.');

                return;
            }
            $this->generation->update(['seo_target_keyword_id' => $work['target']?->id, 'target_keyword_context' => $work['snapshot']]);
        } else {
            $contentRequests = $this->generation->plan->website->contentRequests()->pendingInQueueOrder()->limit(2)->get();
            if ($this->generation->target_keyword_context === null) {
                $activeTargets = $targets->active($this->generation->plan->website);
                $selectedTarget = $contentRequests->isEmpty() ? $targets->select($activeTargets) : null;
                $this->generation->update([
                    'seo_target_keyword_id' => $selectedTarget?->id,
                    'target_keyword_context' => $targets->snapshot($activeTargets),
                ]);
            }
        }
        $this->generation->setRelation('contentRequests', $contentRequests);
        $performance = $connection?->property_url ? $searchConsole->performance($connection) : [];
        $this->generation->update(['search_performance' => $performance]);
        if ($this->generation->competitor_context === null) {
            $this->generation->update(['competitor_context' => app(CompetitorContentContext::class)->forGeneration($this->generation, $contentRequests)]);
        }
        if ($this->generation->backlink_context === null) {
            $this->generation->update(['backlink_context' => app(BacklinkContentContext::class)->forGeneration($contentRequests)]);
        }
        $prompt = $prompts->generate($this->generation);
        $this->generation->update(['status' => ContentGeneration::STATUS_RUNNING, 'prompt' => $prompt, 'started_at' => now(), 'error' => null]);
        $task = $copilot->startTask($authorization, $this->generation->repository, $prompt);
        $this->generation->update([
            'copilot_task_id' => $task['id'],
            'copilot_task_url' => $task['html_url'] ?? null,
            'copilot_task_state' => $task['state'] ?? 'queued',
        ]);
        $this->generation->targetKeyword?->update(['last_selected_at' => now()]);
        $this->generation->plan->website->contentRequests()
            ->whereKey($contentRequests->modelKeys())
            ->whereNull('picked_up_at')
            ->update([
                'content_generation_id' => $this->generation->id,
                'picked_up_at' => now(),
            ]);
        SyncContentGeneration::dispatch($this->generation)->delay(now()->addMinute());
    }

    protected function skipGeneration(string $reason): void
    {
        $this->generation->update(['status' => ContentGeneration::STATUS_SKIPPED, 'skip_reason' => $reason, 'completed_at' => now()]);
    }

    public function failed(?Throwable $exception): void
    {
        $this->failGeneration($exception?->getMessage() ?? 'The content generation could not be started.');
    }

    protected function failGeneration(string $message): void
    {
        $this->generation->update(['status' => ContentGeneration::STATUS_FAILED, 'error' => $message, 'completed_at' => now()]);
    }
}
