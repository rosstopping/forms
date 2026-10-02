<?php

namespace App\Jobs;

use App\Models\GoogleAdsTrackingRequest;
use App\Services\CopilotAgentClient;
use App\Services\GoogleAdsTrackingPrompt;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class StartGoogleAdsTrackingRequest implements ShouldBeEncrypted, ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 60;

    public int $uniqueFor = 3600;

    public function __construct(public GoogleAdsTrackingRequest $trackingRequest) {}

    public function uniqueId(): string
    {
        return (string) $this->trackingRequest->id;
    }

    public function handle(CopilotAgentClient $copilot, GoogleAdsTrackingPrompt $prompts): void
    {
        $this->trackingRequest->refresh()->loadMissing(['website.googleAdsConnection', 'repository', 'requester.githubAuthorization']);
        if ($this->trackingRequest->status !== GoogleAdsTrackingRequest::STATUS_QUEUED) {
            return;
        }

        if ($this->trackingRequest->website->googleAdsConnection?->customer_id !== $this->trackingRequest->customer_id
            || $this->trackingRequest->website->repository?->id !== $this->trackingRequest->website_repository_id
            || ! $this->trackingRequest->requester->githubAuthorization) {
            $this->trackingRequest->update(['status' => GoogleAdsTrackingRequest::STATUS_FAILED, 'error' => 'The selected Ads account, repository or GitHub authorization changed.']);

            return;
        }

        $this->trackingRequest->update(['status' => GoogleAdsTrackingRequest::STATUS_RUNNING, 'started_at' => now()]);
        $task = $copilot->startTask($this->trackingRequest->requester->githubAuthorization, $this->trackingRequest->repository, $prompts->generate($this->trackingRequest));
        if (! is_string($task['id'] ?? null) || $task['id'] === '') {
            $this->trackingRequest->update(['status' => GoogleAdsTrackingRequest::STATUS_UNCERTAIN, 'error' => 'GitHub did not confirm the task ID. Check GitHub before retrying.']);

            return;
        }

        $this->trackingRequest->update([
            'copilot_task_id' => $task['id'],
            'copilot_task_url' => $task['html_url'] ?? null,
            'copilot_task_state' => $task['state'] ?? 'queued',
        ]);
        SyncGoogleAdsTrackingRequest::dispatch($this->trackingRequest)->delay(now()->addMinute());
    }

    public function failed(?Throwable $exception): void
    {
        $this->trackingRequest->update(['status' => GoogleAdsTrackingRequest::STATUS_UNCERTAIN, 'error' => 'GitHub did not confirm the tracking task. Check GitHub before retrying.']);
    }
}
