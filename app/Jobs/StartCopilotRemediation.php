<?php

namespace App\Jobs;

use App\Models\RemediationRun;
use App\Services\CopilotAgentClient;
use App\Services\RemediationPromptGenerator;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class StartCopilotRemediation implements ShouldBeEncrypted, ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public int $uniqueFor = 3600;

    /** @var array<int, int> */
    public array $backoff = [60, 300];

    public function __construct(public RemediationRun $run) {}

    public function uniqueId(): string
    {
        return (string) $this->run->id;
    }

    public function handle(CopilotAgentClient $copilot, RemediationPromptGenerator $prompts): void
    {
        $this->run->loadMissing(['repository', 'report.website']);
        $authorization = $this->run->requester?->githubAuthorization;

        if (! $authorization) {
            $this->run->update([
                'status' => RemediationRun::STATUS_FAILED,
                'error' => 'The requesting administrator has not authorized GitHub Copilot.',
                'completed_at' => now(),
            ]);

            return;
        }

        $prompt = $prompts->generate($this->run);
        $this->run->update([
            'status' => RemediationRun::STATUS_RUNNING,
            'prompt' => $prompt,
            'started_at' => now(),
            'error' => null,
        ]);

        $task = $copilot->startTask($authorization, $this->run->repository, $prompt);
        $this->run->update([
            'copilot_task_id' => $task['id'],
            'copilot_task_url' => $task['html_url'] ?? null,
            'copilot_task_state' => $task['state'] ?? 'queued',
        ]);

        SyncCopilotRemediation::dispatch($this->run)->delay(now()->addMinute());
    }

    public function failed(?Throwable $exception): void
    {
        $this->run->update([
            'status' => RemediationRun::STATUS_FAILED,
            'error' => $exception?->getMessage() ?? 'The Copilot remediation could not be started.',
            'completed_at' => now(),
        ]);
    }
}
