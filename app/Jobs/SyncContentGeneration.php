<?php

namespace App\Jobs;

use App\Models\ContentGeneration;
use App\Services\ContentGenerationNotifier;
use App\Services\ContentNoWorkRecovery;
use App\Services\CopilotAgentClient;
use App\Services\GithubAppClient;
use App\Services\SeoImpactAutomation;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use RuntimeException;
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
        Cache::lock('content-generation-sync-'.$this->generation->id, 120)->get(function () use ($copilot, $github, $notifier): void {
            $this->generation->refresh();
            if ($this->generation->status !== ContentGeneration::STATUS_RUNNING) {
                return;
            }
            $this->synchronize($copilot, $github, $notifier);
        });
    }

    private function synchronize(CopilotAgentClient $copilot, GithubAppClient $github, ContentGenerationNotifier $notifier): void
    {
        $this->generation->loadMissing(['repository', 'requester.githubAuthorization']);
        $authorization = $this->generation->requester?->githubAuthorization;
        if (! $authorization || ! $this->generation->copilot_task_id) {
            $this->failGeneration('The content generation task can no longer be synchronized.');

            return;
        }
        $task = $copilot->task($authorization, $this->generation->repository, $this->generation->copilot_task_id);
        $state = (string) ($task['state'] ?? 'unknown');
        $this->generation->update(['copilot_task_state' => $state]);
        if (in_array($state, ['completed', 'failed', 'cancelled'], true)) {
            $this->inspectResult($task, $github, $notifier);

            return;
        }
        if ($this->generation->started_at?->isBefore(now()->subHours(2))) {
            $this->failGeneration('The content generation task did not complete within two hours. Its changes are unverified; no automatic replacement was started.');

            return;
        }
        self::dispatch($this->generation)->delay(now()->addMinute());
    }

    /** @param array<string, mixed> $task */
    private function inspectResult(array $task, GithubAppClient $github, ContentGenerationNotifier $notifier): void
    {
        $repository = $this->generation->repository;
        $artifacts = collect($task['artifacts'] ?? [])->filter(fn (array $artifact): bool => ($artifact['provider'] ?? null) === 'github' && ($artifact['type'] ?? null) === 'pull');
        if ($artifacts->count() > 1) {
            $this->failGeneration('Multiple pull requests were reported. Review all changes in GitHub before retrying.');

            return;
        }
        $artifact = $artifacts->first();
        $number = data_get($artifact, 'data.number');
        $heads = collect($task['sessions'] ?? [])->pluck('head_ref')->filter(fn ($head): bool => is_string($head) && $head !== '')->unique()->values();
        if ($heads->count() > 1) {
            $this->failGeneration('Multiple automation branches were reported. Review GitHub before retrying.');

            return;
        }
        $head = $heads->first();
        if ($head) {
            try {
                $number = $github->pullRequestForHead($repository, $head)['number'] ?? null;
            } catch (RuntimeException $exception) {
                if (! str_starts_with($exception->getMessage(), 'GitHub did not return a pull request')) {
                    throw $exception;
                }
                $number = null;
            }
        }
        if ($number && ctype_digit((string) $number)) {
            $details = $github->pullRequestDetails($repository, (int) $number);
            $pull = $details['pull_request'];
            $files = $details['files'];
            if ((int) data_get($pull, 'base.repo.id') !== (int) $repository->repository_id
                || (int) data_get($pull, 'head.repo.id') !== (int) $repository->repository_id
                || data_get($pull, 'base.ref') !== $repository->default_branch
                || ($head && data_get($pull, 'head.ref') !== $head)
                || (int) ($pull['number'] ?? 0) !== (int) $number) {
                $this->failGeneration('The reported pull request does not match this repository and branch. Review GitHub before retrying.');

                return;
            }
            $changedFiles = $pull['changed_files'] ?? null;
            $evidence = ['source' => 'github_pull_request', 'changed_files' => $changedFiles,
                'head_sha' => data_get($pull, 'head.sha'), 'base_sha' => data_get($pull, 'base.sha'), 'pull_request_number' => (int) $number];
            $this->generation->update([
                'pull_request_number' => (int) $number,
                'pull_request_url' => $pull['html_url'] ?? "https://github.com/{$repository->full_name}/pull/{$number}",
                'pull_request_state' => $pull['state'] ?? 'open',
            ]);
            if ($changedFiles === 0 && $files === [] && ($pull['additions'] ?? null) === 0 && ($pull['deletions'] ?? null) === 0 && empty($pull['merged_at'])) {
                app(ContentNoWorkRecovery::class)->recover($this->generation,
                    'GitHub confirms pull request #'.$number.' has no changed files. '.Str::limit((string) ($pull['body'] ?? ''), 1500, ''),
                    [...$evidence, 'classification' => 'no_work']);

                return;
            }
            if ((is_int($changedFiles) && $changedFiles > 0) || (is_array($files) && $files !== [])) {
                $this->generation->update([
                    'status' => empty($pull['merged_at']) ? ContentGeneration::STATUS_PULL_REQUEST_OPEN : ContentGeneration::STATUS_COMPLETED,
                    'merged_at' => $pull['merged_at'] ?? null, 'completed_at' => $pull['merged_at'] ?? null,
                    'completion_evidence' => [...$evidence, 'classification' => 'changed'], 'error' => null,
                ]);
                if (! empty($pull['merged_at'])) {
                    app(SeoImpactAutomation::class)->generationMerged($this->generation, $pull);
                }
                $notifier->ready($this->generation);

                return;
            }
            $this->failGeneration('GitHub returned incomplete or contradictory change evidence. Requests remain attached for staff review.');

            return;
        }
        if ($head) {
            $evidence = $github->contentBranchChanges($repository, $head);
            if (($evidence['changed_files'] ?? null) === 0) {
                app(ContentNoWorkRecovery::class)->recover($this->generation, 'The automation task ended without a pull request; GitHub confirms its branch contains no changed files.', [...$evidence, 'classification' => 'no_work']);

                return;
            }
            $this->generation->update(['completion_evidence' => [...$evidence, 'classification' => 'changed']]);
        }
        $this->failGeneration('The task ended without a verified pull request. No-work was not proven; requests remain attached and no replacement will be submitted.');
    }

    public function failed(?Throwable $exception): void
    {
        if ($this->generation->fresh()?->status === ContentGeneration::STATUS_RUNNING) {
            $this->failGeneration($exception?->getMessage() ?? 'The content generation task status could not be synchronized.');
        }
    }

    protected function failGeneration(string $message): void
    {
        $this->generation->update(['status' => ContentGeneration::STATUS_FAILED, 'error' => $message, 'completed_at' => now()]);
    }
}
