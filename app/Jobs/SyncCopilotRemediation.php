<?php

namespace App\Jobs;

use App\Models\RemediationRun;
use App\Services\CopilotAgentClient;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SyncCopilotRemediation implements ShouldBeEncrypted, ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public int $uniqueFor = 300;

    /** @var array<int, int> */
    public array $backoff = [30, 120];

    public function __construct(public RemediationRun $run) {}

    public function uniqueId(): string
    {
        return (string) $this->run->id;
    }

    public function handle(CopilotAgentClient $copilot): void
    {
        $this->run->loadMissing('repository');
        $authorization = $this->run->requester?->githubAuthorization;

        if (! $authorization || ! $this->run->copilot_task_id) {
            $this->failRun('The Copilot task can no longer be synchronized.');

            return;
        }

        $task = $copilot->task($authorization, $this->run->repository, $this->run->copilot_task_id);
        $state = (string) ($task['state'] ?? 'unknown');
        $this->run->update(['copilot_task_state' => $state]);

        if (in_array($state, ['failed', 'cancelled'], true)) {
            $this->failRun('GitHub Copilot reported that the task '.$state.'.');

            return;
        }

        if ($state === 'completed') {
            $pullRequest = collect($task['artifacts'] ?? [])->first(fn (array $artifact) => ($artifact['provider'] ?? null) === 'github' && ($artifact['type'] ?? null) === 'pull');
            $pullRequestNumber = data_get($pullRequest, 'data.number', data_get($pullRequest, 'data.id'));

            if (! $pullRequestNumber) {
                $this->failRun('GitHub Copilot completed without creating a pull request.');

                return;
            }

            $this->run->update([
                'status' => RemediationRun::STATUS_PULL_REQUEST_OPEN,
                'pull_request_number' => $pullRequestNumber,
                'pull_request_url' => "https://github.com/{$this->run->repository->full_name}/pull/{$pullRequestNumber}",
                'pull_request_state' => 'open',
            ]);

            return;
        }

        if ($this->run->started_at?->isBefore(now()->subHours(2))) {
            $this->failRun('The GitHub Copilot task did not complete within two hours.');

            return;
        }

        self::dispatch($this->run)->delay(now()->addMinute());
    }

    public function failed(?Throwable $exception): void
    {
        $this->failRun($exception?->getMessage() ?? 'The Copilot task status could not be synchronized.');
    }

    protected function failRun(string $message): void
    {
        $this->run->update([
            'status' => RemediationRun::STATUS_FAILED,
            'error' => $message,
            'completed_at' => now(),
        ]);
    }
}
