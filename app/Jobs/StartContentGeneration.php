<?php

namespace App\Jobs;

use App\Models\ContentGeneration;
use App\Services\ContentGenerationPromptGenerator;
use App\Services\CopilotAgentClient;
use App\Services\SearchConsoleClient;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
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

    public function handle(SearchConsoleClient $searchConsole, ContentGenerationPromptGenerator $prompts, CopilotAgentClient $copilot): void
    {
        $this->generation->refresh();

        if ($this->generation->copilot_task_id) {
            return;
        }

        $this->generation->loadMissing(['plan.website.searchConsoleConnection', 'repository', 'requester.githubAuthorization']);
        $connection = $this->generation->plan->website->searchConsoleConnection;
        $authorization = $this->generation->requester?->githubAuthorization;

        if (! $authorization) {
            $this->failGeneration('GitHub automation must remain authorized.');

            return;
        }

        $performance = $connection?->property_url ? $searchConsole->performance($connection) : [];
        $this->generation->update(['search_performance' => $performance]);
        $contentRequests = $this->generation->plan->website->contentRequests()
            ->whereNull('picked_up_at')
            ->oldest()
            ->limit(2)
            ->get();
        $this->generation->setRelation('contentRequests', $contentRequests);
        $this->generation->setRelation('contentRequests', $contentRequests);
        $prompt = $prompts->generate($this->generation);
        $this->generation->update(['status' => ContentGeneration::STATUS_RUNNING, 'prompt' => $prompt, 'started_at' => now(), 'error' => null]);
        $task = $copilot->startTask($authorization, $this->generation->repository, $prompt);
        $this->generation->update([
            'copilot_task_id' => $task['id'],
            'copilot_task_url' => $task['html_url'] ?? null,
            'copilot_task_state' => $task['state'] ?? 'queued',
        ]);
        $this->generation->plan->website->contentRequests()
            ->whereKey($contentRequests->modelKeys())
            ->whereNull('picked_up_at')
            ->update([
                'content_generation_id' => $this->generation->id,
                'picked_up_at' => now(),
            ]);
        SyncContentGeneration::dispatch($this->generation)->delay(now()->addMinute());
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
