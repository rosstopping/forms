<?php

namespace App\Jobs;

use App\Models\ContentGeneration;
use App\Services\ContentGenerationNotifier;
use App\Services\CopilotAgentClient;
use App\Services\GithubAppClient;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SyncContentGeneration implements ShouldBeEncrypted, ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public int $uniqueFor = 300;

    /** @var array<int, int> */
    public array $backoff = [30, 120];

    public function __construct(public ContentGeneration $generation) {}

    public function uniqueId(): string
    {
        return (string) $this->generation->id;
    }

    public function handle(CopilotAgentClient $copilot, GithubAppClient $github, ContentGenerationNotifier $notifier): void
    {
        $this->generation->loadMissing(['repository', 'requester.githubAuthorization']);
        $authorization = $this->generation->requester?->githubAuthorization;
        if (! $authorization || ! $this->generation->copilot_task_id) {
            $this->failGeneration('The Copilot task can no longer be synchronized.');

            return;
        }

        $task = $copilot->task($authorization, $this->generation->repository, $this->generation->copilot_task_id);
        $state = (string) ($task['state'] ?? 'unknown');
        $this->generation->update(['copilot_task_state' => $state]);
        if (in_array($state, ['failed', 'cancelled'], true)) {
            $this->failGeneration('GitHub Copilot reported that the task '.$state.'.');

            return;
        }
        if ($state === 'completed') {
            $pullRequest = collect($task['artifacts'] ?? [])->first(fn (array $artifact) => ($artifact['provider'] ?? null) === 'github' && ($artifact['type'] ?? null) === 'pull');
            $number = data_get($pullRequest, 'data.number');
            $url = null;
            $headRef = data_get($task, 'sessions.0.head_ref');

            if (is_string($headRef) && $headRef !== '') {
                $resolvedPullRequest = $github->pullRequestForHead($this->generation->repository, $headRef);
                $number = $resolvedPullRequest['number'] ?? null;
                $url = $resolvedPullRequest['html_url'] ?? null;
            }

            if (! $number) {
                $this->failGeneration('GitHub Copilot completed without creating a pull request.');

                return;
            }
            $this->generation->update([
                'status' => ContentGeneration::STATUS_PULL_REQUEST_OPEN,
                'pull_request_number' => $number,
                'pull_request_url' => $url ?: "https://github.com/{$this->generation->repository->full_name}/pull/{$number}",
                'pull_request_state' => 'open',
            ]);
            $notifier->ready($this->generation);

            return;
        }
        if ($this->generation->started_at?->isBefore(now()->subHours(2))) {
            $this->failGeneration('The GitHub Copilot task did not complete within two hours.');

            return;
        }
        self::dispatch($this->generation)->delay(now()->addMinute());
    }

    public function failed(?Throwable $exception): void
    {
        $this->failGeneration($exception?->getMessage() ?? 'The Copilot task status could not be synchronized.');
    }

    protected function failGeneration(string $message): void
    {
        $this->generation->update(['status' => ContentGeneration::STATUS_FAILED, 'error' => $message, 'completed_at' => now()]);
    }
}
