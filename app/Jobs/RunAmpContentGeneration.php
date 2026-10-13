<?php

namespace App\Jobs;

use App\Models\ContentGeneration;
use App\Services\AmpContentRunner;
use App\Services\ContentGenerationNotifier;
use App\Services\GithubAppClient;
use DomainException;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Runs an accepted content generation through Amp, then confirms Amp's pull request in the
 * website's repository through the Sitewell GitHub App before marking it ready for review.
 */
class RunAmpContentGeneration implements ShouldBeEncrypted, ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /** Releases wait for another run on the same repository; any exception fails the run. */
    public int $tries = 30;

    public int $maxExceptions = 1;

    public int $timeout;

    public int $uniqueFor;

    public bool $failOnTimeout = true;

    public function __construct(public ContentGeneration $generation)
    {
        $this->timeout = max(60, min(14400, (int) config('amp_content.timeout_seconds'))) + 300;
        $this->uniqueFor = $this->timeout + 600;
        $this->onConnection(config('amp_content.queue_connection'));
        $this->onQueue(config('amp_content.queue'));
    }

    public function uniqueId(): string
    {
        return (string) $this->generation->id;
    }

    public function handle(AmpContentRunner $runner, GithubAppClient $github, ContentGenerationNotifier $notifier): void
    {
        $this->generation->loadMissing('repository.installation', 'plan.website');
        $lock = Cache::lock('amp-content-repository:'.$this->generation->repository->repository_id, $this->uniqueFor);
        if (! $lock->get()) {
            $this->release(120);

            return;
        }

        try {
            $this->generation->refresh();
            if ($this->generation->status !== ContentGeneration::STATUS_RUNNING || ! $this->generation->usesAmp()
                || $this->generation->pull_request_number || ! $this->generation->head_branch) {
                return;
            }

            $pullRequest = $this->existingPullRequest($github);
            if ($pullRequest === null && $this->generation->agent_thread_id === null) {
                $runError = null;
                $reply = '';
                try {
                    $reply = $runner->run($this->generation);
                } catch (DomainException $exception) {
                    $runError = $exception;
                }
                $pullRequest = $this->existingPullRequest($github);
                if ($pullRequest === null && $runError !== null) {
                    throw $runError;
                }
                if ($pullRequest === null && ($reason = $runner->noEligibleWorkReason($reply)) !== null) {
                    $this->skipAndRequeue($reason);

                    return;
                }
            }
            if ($pullRequest === null) {
                throw new DomainException($this->generation->agent_thread_id
                    ? "Amp finished without a pull request from {$this->generation->head_branch} in {$this->generation->repository->full_name}. Review the Amp thread; retrying only checks GitHub again."
                    : 'Amp did not start. Retry to start a new Amp run.');
            }

            $this->generation->update([
                'status' => ContentGeneration::STATUS_PULL_REQUEST_OPEN,
                'pull_request_number' => (int) $pullRequest['number'],
                'pull_request_url' => (string) ($pullRequest['html_url'] ?? "https://github.com/{$this->generation->repository->full_name}/pull/{$pullRequest['number']}"),
                'pull_request_state' => (string) ($pullRequest['state'] ?? 'open'),
                'error' => null,
            ]);
            $notifier->ready($this->generation);
        } catch (Throwable $exception) {
            if (! $exception instanceof DomainException) {
                report($exception);
            }
            $this->failGeneration($exception instanceof DomainException ? $exception->getMessage() : 'The Amp content run stopped unexpectedly. Check the Amp thread and GitHub before retrying.');
        } finally {
            $lock->release();
        }
    }

    public function failed(?Throwable $exception): void
    {
        if ($this->generation->fresh()?->status === ContentGeneration::STATUS_RUNNING) {
            $this->failGeneration('The Amp content run did not finish on the worker. Check the Amp thread and GitHub before retrying.');
        }
    }

    /**
     * Find the run's pull request in the website's own repository, ignoring forks and other base branches.
     *
     * @return array<string, mixed>|null
     */
    protected function existingPullRequest(GithubAppClient $github): ?array
    {
        $repository = $this->generation->repository;
        try {
            $pullRequest = $github->pullRequestForHead($repository, $this->generation->head_branch);
        } catch (\RuntimeException $exception) {
            if (str_starts_with($exception->getMessage(), 'GitHub did not return a pull request')) {
                return null;
            }

            throw $exception;
        }

        if ((int) data_get($pullRequest, 'base.repo.id') !== (int) $repository->repository_id
            || (int) data_get($pullRequest, 'head.repo.id') !== (int) $repository->repository_id
            || data_get($pullRequest, 'base.ref') !== $repository->default_branch
            || (int) ($pullRequest['number'] ?? 0) < 1) {
            throw new DomainException("The pull request for {$this->generation->head_branch} is not in {$repository->full_name} against {$repository->default_branch}. Review it in GitHub.");
        }

        return $pullRequest;
    }

    /**
     * Amp deliberately delivered nothing, so return the run's requests to the queue and release its budget.
     */
    protected function skipAndRequeue(string $reason): void
    {
        DB::transaction(function () use ($reason): void {
            $this->generation->contentRequests()->update(['content_generation_id' => null, 'picked_up_at' => null]);
            $this->generation->update([
                'status' => ContentGeneration::STATUS_SKIPPED,
                'skip_reason' => 'Amp found no eligible work; queued requests were returned to the queue. '.$reason,
                'error' => null,
                'completed_at' => now(),
            ]);
        });
    }

    protected function failGeneration(string $message): void
    {
        $this->generation->update(['status' => ContentGeneration::STATUS_FAILED, 'error' => $message, 'completed_at' => now()]);
    }
}
