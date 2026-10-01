<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCopilotSdkRunRequest;
use App\Jobs\RunCopilotSdkTitle;
use App\Models\CopilotSdkTestRun;
use App\Models\Website;
use App\Services\CopilotSdkTitleRunner;
use App\Services\GithubSdkPublisher;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class CopilotSdkRunController extends Controller
{
    public function store(StoreCopilotSdkRunRequest $request, Website $website, GithubSdkPublisher $publisher, CopilotSdkTitleRunner $runner): RedirectResponse
    {
        abort_unless(config('copilot_sdk.workspace_enabled'), 404);

        $repository = $website->repository;
        abort_unless($repository, 422, 'Connect a repository first.');
        $lock = Cache::lock('copilot-sdk-test:'.$repository->repository_id, 600);
        if (! $lock->get()) {
            return $this->back($website)->withErrors(['sdk' => 'Another SDK run is already active for this repository.']);
        }
        $run = null;
        try {
            $this->ensureQueueReady();
            $runner->ensureConfigured();
            $publisher->authorize($request->user(), $repository);
            if (CopilotSdkTestRun::query()->where('repository_id', $repository->repository_id)
                ->whereIn('status', ['queued', 'publish_queued', 'running', 'validated', 'publishing'])->exists()) {
                throw new DomainException('Another SDK run is already active for this repository.');
            }
            $data = $request->validated();
            $snapshot = $publisher->snapshot($repository, $data['path']);
            if (CopilotSdkTestRun::query()->where('website_repository_id', $repository->id)
                ->where('base_sha', $snapshot['base_sha'])->where('path', $data['path'])->where('title', $data['title'])
                ->whereIn('status', ['publish_failed', 'pull_request_open'])->exists()) {
                throw new DomainException('This change already has a run awaiting review or publishing. Use the existing run below.');
            }
            if ($runner->expected($snapshot['original'], $data['title']) === $snapshot['original']) {
                return $this->back($website)->with('status', 'The title is already correct. No model call or pull request is needed.');
            }
            $id = (string) Str::uuid();
            $run = CopilotSdkTestRun::query()->create([
                ...$snapshot, 'run_id' => $id, 'website_repository_id' => $repository->id, 'requested_by' => $request->user()->id,
                'repository_id' => $repository->repository_id, 'installation_id' => $repository->installation->installation_id,
                'full_name' => $repository->full_name, 'base_branch' => $repository->default_branch,
                ...$data, 'branch' => 'sitewell/sdk-test-'.$id, 'status' => 'queued',
            ]);
            RunCopilotSdkTitle::dispatch($run->id);

            return $this->back($website)->with('status', 'SDK run queued. A draft pull request will be prepared for review.');
        } catch (Throwable $exception) {
            $run?->update(['status' => 'failed', 'error' => 'The run could not be queued. No model call was made.']);

            return $this->back($website)->withInput()->withErrors(['sdk' => $exception instanceof DomainException ? $exception->getMessage() : 'Unable to queue this run. Check repository access, the file path and worker configuration.']);
        } finally {
            $lock->release();
        }
    }

    public function resume(Request $request, Website $website, CopilotSdkTestRun $run): RedirectResponse
    {
        abort_unless(config('copilot_sdk.workspace_enabled'), 404);

        $this->authorizeWebsite($request, $website);
        abort_unless($run->website_repository_id === $website->repository?->id && $run->requested_by === $request->user()->id, 404);
        try {
            $this->ensureQueueReady();
            abort_unless(config('copilot_sdk.enabled'), 403);
            $claimed = CopilotSdkTestRun::query()->whereKey($run->id)->where('status', 'publish_failed')
                ->whereNotNull('replacement')->update(['status' => 'publish_queued', 'error' => null]);
            if ($claimed) {
                try {
                    RunCopilotSdkTitle::dispatch($run->id);
                } catch (Throwable $exception) {
                    $run->update(['status' => 'publish_failed', 'error' => 'Publishing could not be queued. Please try again.']);
                    throw $exception;
                }
            }

            return $this->back($website)->with('status', $claimed ? 'Publishing queued. No additional model call will be made.' : 'This run is not awaiting a publishing retry.');
        } catch (Throwable) {
            return $this->back($website)->withErrors(['sdk' => 'Unable to resume publishing. Check SDK and queue configuration.']);
        }
    }

    public function status(Request $request, Website $website): View
    {
        abort_unless(config('copilot_sdk.workspace_enabled'), 404);

        $this->authorizeWebsite($request, $website);
        $sdkRuns = CopilotSdkTestRun::query()->where('website_repository_id', $website->repository?->id)->latest('id')->limit(10)->get();

        return view('admin.websites.partials.content-sdk-runs', compact('website', 'sdkRuns'));
    }

    private function authorizeWebsite(Request $request, Website $website): void
    {
        abort_unless($request->user()?->isAdmin() && $website->isManageableBy($request->user()), 403);
    }

    private function ensureQueueReady(): void
    {
        $connection = config('queue.connections.'.config('queue.default'));
        if (! in_array($connection['driver'] ?? null, ['database', 'redis'], true) || ($connection['retry_after'] ?? 0) <= 240) {
            throw new DomainException('SDK runs need a database or Redis queue with retry_after greater than 240 seconds.');
        }
    }

    private function back(Website $website): RedirectResponse
    {
        return redirect()->route('admin.websites.section', [$website, 'content', 'content_section' => 'sdk']);
    }
}
