<?php

namespace App\Http\Controllers;

use App\Models\ContentGeneration;
use App\Models\GithubInstallation;
use App\Models\RemediationRun;
use App\Models\WebsiteRepository;
use App\Services\GithubWebhookSignature;
use App\Services\WordPressStaticReleaseQueuer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GithubWebhookController extends Controller
{
    public function __construct(
        protected GithubWebhookSignature $signature,
        protected WordPressStaticReleaseQueuer $releases,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        abort_unless($this->signature->isValid($request->getContent(), $request->header('X-Hub-Signature-256')), 401);

        match ($request->header('X-GitHub-Event')) {
            'installation' => $this->handleInstallation($request),
            'pull_request' => $this->handlePullRequest($request),
            'push' => $this->handlePush($request),
            'workflow_run' => $this->handleWorkflowRun($request),
            default => null,
        };

        return response()->json(['received' => true]);
    }

    protected function handleInstallation(Request $request): void
    {
        $installation = GithubInstallation::query()
            ->where('installation_id', $request->integer('installation.id'))
            ->first();

        if (! $installation) {
            return;
        }

        $installation->update(match ($request->string('action')->toString()) {
            'deleted' => ['status' => GithubInstallation::STATUS_DELETED],
            'suspend' => ['status' => GithubInstallation::STATUS_SUSPENDED, 'suspended_at' => now()],
            'unsuspend' => ['status' => GithubInstallation::STATUS_ACTIVE, 'suspended_at' => null],
            default => [],
        });
    }

    protected function handlePullRequest(Request $request): void
    {
        $repository = WebsiteRepository::query()
            ->where('repository_id', $request->integer('repository.id'))
            ->first();

        if (! $repository) {
            return;
        }

        $run = RemediationRun::query()
            ->where('website_repository_id', $repository->id)
            ->where('pull_request_number', $request->integer('pull_request.number'))
            ->first();

        $merged = $request->boolean('pull_request.merged');
        if ($run) {
            $run->update([
                'pull_request_state' => $request->string('pull_request.state')->toString(),
                'status' => $merged ? RemediationRun::STATUS_COMPLETED : $run->status,
                'completed_at' => $merged ? now() : $run->completed_at,
                'merged_at' => $merged ? now() : $run->merged_at,
            ]);
        }

        $generation = ContentGeneration::query()
            ->where('website_repository_id', $repository->id)
            ->where('pull_request_number', $request->integer('pull_request.number'))
            ->first();
        if ($generation) {
            $generation->update([
                'pull_request_state' => $request->string('pull_request.state')->toString(),
                'status' => $merged ? ContentGeneration::STATUS_COMPLETED : $generation->status,
                'completed_at' => $merged ? now() : $generation->completed_at,
                'merged_at' => $merged ? now() : $generation->merged_at,
            ]);
        }
    }

    protected function handlePush(Request $request): void
    {
        $repositories = WebsiteRepository::query()
            ->with('website.wordpressConnection')
            ->where('repository_id', $request->integer('repository.id'))
            ->get();

        foreach ($repositories as $repository) {
            if ($repository->usesActionsArtifact()
                || $request->string('ref')->toString() !== 'refs/heads/'.$repository->default_branch
                || ! $repository->website->wordpressConnection?->isConnected()) {
                continue;
            }

            $commitSha = $request->string('after')->toString();

            if (! preg_match('/^[a-f0-9]{40}$/i', $commitSha) || $commitSha === str_repeat('0', 40)) {
                continue;
            }
            $this->releases->queue($repository->website, commitSha: $commitSha);
        }
    }

    protected function handleWorkflowRun(Request $request): void
    {
        if ($request->input('action') !== 'completed'
            || $request->input('workflow_run.conclusion') !== 'success'
            || ! in_array($request->input('workflow_run.event'), ['push', 'workflow_dispatch'], true)) {
            return;
        }

        $commitSha = $request->string('workflow_run.head_sha')->toString();
        $runId = $request->integer('workflow_run.id');

        if ($runId < 1 || ! preg_match('/^[a-f0-9]{40}$/i', $commitSha)) {
            return;
        }

        $repositories = WebsiteRepository::query()
            ->with(['installation', 'website.wordpressConnection'])
            ->where('repository_id', $request->integer('repository.id'))
            ->get();

        foreach ($repositories as $repository) {
            if (! $repository->usesActionsArtifact()
                || ! $repository->website->wordpressConnection?->isConnected()
                || $repository->installation->installation_id != $request->integer('installation.id')
                || $repository->repository_id != $request->integer('workflow_run.head_repository.id')
                || $repository->default_branch !== $request->input('workflow_run.head_branch')
                || $repository->wordpress_workflow_path !== $request->input('workflow_run.path')) {
                continue;
            }

            $this->releases->queue($repository->website, commitSha: $commitSha, workflowRunId: $runId);
        }
    }
}
