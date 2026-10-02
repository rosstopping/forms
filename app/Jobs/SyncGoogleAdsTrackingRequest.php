<?php

namespace App\Jobs;

use App\Models\GoogleAdsTrackingRequest;
use App\Services\CopilotAgentClient;
use App\Services\GithubAppClient;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SyncGoogleAdsTrackingRequest implements ShouldBeEncrypted, ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public int $uniqueFor = 300;

    public function __construct(public GoogleAdsTrackingRequest $trackingRequest) {}

    public function uniqueId(): string
    {
        return (string) $this->trackingRequest->id;
    }

    public function handle(CopilotAgentClient $copilot, GithubAppClient $github): void
    {
        $this->trackingRequest->refresh()->loadMissing(['repository', 'requester.githubAuthorization']);
        if ($this->trackingRequest->status !== GoogleAdsTrackingRequest::STATUS_RUNNING) {
            return;
        }

        $authorization = $this->trackingRequest->requester->githubAuthorization;
        if (! $authorization || ! $this->trackingRequest->copilot_task_id) {
            $this->failRequest('The tracking task can no longer be checked.');

            return;
        }

        $task = $copilot->task($authorization, $this->trackingRequest->repository, $this->trackingRequest->copilot_task_id);
        $state = (string) ($task['state'] ?? 'unknown');
        $this->trackingRequest->update(['copilot_task_state' => $state]);

        if (in_array($state, ['failed', 'cancelled'], true)) {
            $this->failRequest('GitHub reported that the tracking task '.$state.'.');

            return;
        }

        if ($state === 'completed') {
            $pullRequest = collect($task['artifacts'] ?? [])->first(fn (array $artifact): bool => ($artifact['provider'] ?? null) === 'github' && ($artifact['type'] ?? null) === 'pull');
            $number = data_get($pullRequest, 'data.number');
            $url = null;
            $headRef = data_get($task, 'sessions.0.head_ref');
            if (is_string($headRef) && $headRef !== '') {
                $resolved = $github->pullRequestForHead($this->trackingRequest->repository, $headRef);
                $number = $resolved['number'] ?? $number;
                $url = $resolved['html_url'] ?? null;
            }

            if (! $number) {
                $this->failRequest('The tracking task finished without a pull request.');

                return;
            }

            $this->trackingRequest->update([
                'status' => GoogleAdsTrackingRequest::STATUS_PULL_REQUEST_OPEN,
                'pull_request_number' => $number,
                'pull_request_url' => $url ?: "https://github.com/{$this->trackingRequest->repository->full_name}/pull/{$number}",
                'completed_at' => now(),
            ]);

            return;
        }

        if ($this->trackingRequest->started_at?->isBefore(now()->subHours(2))) {
            $this->failRequest('The tracking task did not finish within two hours.');

            return;
        }

        self::dispatch($this->trackingRequest)->delay(now()->addMinute());
    }

    public function failed(?Throwable $exception): void
    {
        $this->trackingRequest->update(['status' => GoogleAdsTrackingRequest::STATUS_UNCERTAIN, 'error' => 'The tracking task status could not be confirmed. Check GitHub.']);
    }

    private function failRequest(string $message): void
    {
        $this->trackingRequest->update(['status' => GoogleAdsTrackingRequest::STATUS_FAILED, 'error' => $message, 'completed_at' => now()]);
    }
}
