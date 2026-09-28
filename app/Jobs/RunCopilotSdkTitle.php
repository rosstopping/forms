<?php

namespace App\Jobs;

use App\Models\CopilotSdkTestRun;
use App\Services\CopilotSdkTitleRunner;
use App\Services\GithubSdkPublisher;
use DomainException;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Throwable;

class RunCopilotSdkTitle implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 240;

    public int $uniqueFor = 600;

    public bool $failOnTimeout = true;

    public function __construct(public int $runId) {}

    public function uniqueId(): string
    {
        return (string) $this->runId;
    }

    public function handle(GithubSdkPublisher $publisher, CopilotSdkTitleRunner $runner): void
    {
        $run = CopilotSdkTestRun::query()->findOrFail($this->runId);
        $lock = Cache::lock('copilot-sdk-test:'.$run->repository_id, 600);
        if (! $lock->get()) {
            $this->release(15);

            return;
        }
        try {
            $run->refresh();
            if (! in_array($run->status, ['queued', 'publish_queued'], true)) {
                return;
            }
            if (! config('copilot_sdk.enabled')) {
                throw new DomainException('SDK execution was disabled before this run started.');
            }
            $repository = $run->repository->fresh(['website', 'installation']);
            $publisher->authorize($run->requester->fresh(), $repository);
            if ($repository->repository_id !== $run->repository_id || $repository->installation->installation_id !== $run->installation_id
                || $repository->full_name !== $run->full_name || $repository->default_branch !== $run->base_branch) {
                throw new DomainException('The repository connection changed. Start a new run.');
            }
            if ($run->replacement === null) {
                $run->update(['status' => 'running', 'error' => null]);
                $run->update([...$runner->run($run), 'status' => 'validated']);
            }
            $run->update(['status' => 'publishing', 'error' => null]);
            $publisher->publish($run);
        } catch (Throwable $exception) {
            $this->recordFailure($run, $exception);
        } finally {
            $lock->release();
        }
    }

    public function failed(?Throwable $exception): void
    {
        $run = CopilotSdkTestRun::query()->find($this->runId);
        if ($run && in_array($run->status, ['queued', 'publish_queued', 'running', 'validated', 'publishing'], true)) {
            $this->recordFailure($run, null);
        }
    }

    private function recordFailure(CopilotSdkTestRun $run, ?Throwable $exception): void
    {
        $publishFailed = $run->replacement !== null;
        $run->update([
            'status' => $publishFailed ? 'publish_failed' : 'failed',
            'error' => $publishFailed ? 'Publishing did not finish. Resume publishing without another model call.'
                : ($exception instanceof DomainException ? $exception->getMessage() : 'The SDK run stopped. Check repository access and worker configuration before starting another run.'),
        ]);
    }
}
