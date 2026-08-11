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

        if (! $connection?->property_url || ! $authorization) {
            $this->failGeneration('Search Console and GitHub Copilot must remain connected.');

            return;
        }

        $performance = $searchConsole->performance($connection);
        $this->generation->update(['search_performance' => $performance]);
        $prompt = $prompts->generate($this->generation->fresh());
        $this->generation->update(['status' => ContentGeneration::STATUS_RUNNING, 'prompt' => $prompt, 'started_at' => now(), 'error' => null]);
        $task = $copilot->startTask($authorization, $this->generation->repository, $prompt);
        $this->generation->update([
            'copilot_task_id' => $task['id'],
            'copilot_task_url' => $task['html_url'] ?? null,
            'copilot_task_state' => $task['state'] ?? 'queued',
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
