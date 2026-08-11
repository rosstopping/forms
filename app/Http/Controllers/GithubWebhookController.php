<?php

namespace App\Http\Controllers;

use App\Models\GithubInstallation;
use App\Models\RemediationRun;
use App\Models\WebsiteRepository;
use App\Services\GithubWebhookSignature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GithubWebhookController extends Controller
{
    public function __construct(protected GithubWebhookSignature $signature) {}

    public function __invoke(Request $request): JsonResponse
    {
        abort_unless($this->signature->isValid($request->getContent(), $request->header('X-Hub-Signature-256')), 401);

        match ($request->header('X-GitHub-Event')) {
            'installation' => $this->handleInstallation($request),
            'pull_request' => $this->handlePullRequest($request),
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

        if (! $run) {
            return;
        }

        $merged = $request->boolean('pull_request.merged');
        $run->update([
            'pull_request_state' => $request->string('pull_request.state')->toString(),
            'status' => $merged ? RemediationRun::STATUS_COMPLETED : $run->status,
            'completed_at' => $merged ? now() : $run->completed_at,
            'merged_at' => $merged ? now() : $run->merged_at,
        ]);
    }
}
