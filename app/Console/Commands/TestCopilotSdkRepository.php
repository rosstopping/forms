<?php

namespace App\Console\Commands;

use App\Models\CopilotSdkTestRun;
use App\Models\User;
use App\Models\Website;
use App\Services\CopilotSdkTitleRunner;
use App\Services\GithubSdkPublisher;
use DomainException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

class TestCopilotSdkRepository extends Command
{
    protected $signature = 'copilot-sdk:test-repository {website : Website ID} {--user= : Requesting Sitewell admin ID} {--path=index.html : Repository-relative HTML file} {--title=Sitewell SDK test : New page title} {--publish : Authorize a paid SDK call and a draft PR} {--resume= : Resume publishing this run UUID without another SDK call}';

    protected $description = 'Check readiness or run an admin-only, title-only SDK test against a connected repository';

    public function handle(GithubSdkPublisher $publisher, CopilotSdkTitleRunner $runner): int
    {
        $run = null;
        $lock = null;
        try {
            $user = User::query()->findOrFail($this->option('user'));
            $website = Website::query()->with('repository.installation')->findOrFail($this->argument('website'));
            $repository = $website->repository;
            abort_unless($repository, 422, 'Connect a repository first.');
            $publisher->authorize($user, $repository);
            $runner->ensureConfigured();
            $lock = Cache::lock('copilot-sdk-test:'.$repository->repository_id, 600);
            if (! $lock->get()) {
                $this->error('Another SDK test is running for this repository.');

                return self::FAILURE;
            }
            if ($this->option('resume')) {
                $run = CopilotSdkTestRun::query()->where('run_id', $this->option('resume'))
                    ->where('website_repository_id', $repository->id)->where('requested_by', $user->id)->firstOrFail();
                abort_unless(in_array($run->status, ['validated', 'publishing', 'publish_failed', 'pull_request_open', 'pull_request_closed', 'pull_request_merged'], true), 422);
                if (! $this->option('publish')) {
                    $this->info('Run is ready to resume publishing. Add --publish to continue.');

                    return self::SUCCESS;
                }
            } else {
                $path = (string) $this->option('path');
                $title = (string) $this->option('title');
                $snapshot = $publisher->snapshot($repository, $path);
                if ($runner->expected($snapshot['original'], $title) === $snapshot['original']) {
                    $this->info('The title is already correct. No model call or pull request is needed.');

                    return self::SUCCESS;
                }
                if (! $this->option('publish')) {
                    $this->info('Repository and title change verified. Add --publish to run the paid SDK test and open a draft PR.');

                    return self::SUCCESS;
                }
                $id = (string) Str::uuid();
                $run = CopilotSdkTestRun::query()->create([
                    ...$snapshot, 'run_id' => $id, 'website_repository_id' => $repository->id, 'requested_by' => $user->id,
                    'repository_id' => $repository->repository_id, 'installation_id' => $repository->installation->installation_id,
                    'full_name' => $repository->full_name, 'base_branch' => $repository->default_branch,
                    'path' => $path, 'title' => $title, 'branch' => 'sitewell/sdk-test-'.$id, 'status' => 'running',
                ]);
                $this->line('Run: '.$id);
                $run->update([...$runner->run($run), 'status' => 'validated']);
            }
            $run->update(['status' => 'publishing', 'error' => null]);
            $url = $publisher->publish($run);
            $this->info('SDK title-only validation passed. Review PR: '.$url);
            $this->line('Status: '.$run->status);
            $this->line('Reported input/output tokens: '.data_get($run->usage, 'inputTokens', 0).'/'.data_get($run->usage, 'outputTokens', 0));

            return self::SUCCESS;
        } catch (Throwable $exception) {
            if ($run?->exists) {
                $publishFailed = $run->replacement !== null;
                $reason = $exception instanceof DomainException ? $exception->getMessage() : 'SDK test did not finish or pass independent validation.';
                $run->update(['status' => $publishFailed ? 'publish_failed' : 'failed', 'error' => $publishFailed ? 'Publishing did not finish. Resume this run to reconcile the branch and draft PR.' : $reason]);
                $this->error($publishFailed && $exception instanceof DomainException ? $reason.' '.$run->error : $run->error);
                $this->line('Run: '.$run->run_id);
            } else {
                $this->error($exception instanceof DomainException ? $exception->getMessage() : 'Readiness check failed. Check the admin ID, repository access, HTML path and SDK model configuration.');
            }

            return self::FAILURE;
        } finally {
            $lock?->release();
        }
    }
}
