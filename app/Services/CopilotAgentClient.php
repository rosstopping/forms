<?php

namespace App\Services;

use App\Models\GithubUserAuthorization;
use App\Models\WebsiteRepository;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class CopilotAgentClient
{
    public function __construct(protected GithubOAuthClient $oauth) {}

    /** @return array<string, mixed> */
    public function startTask(GithubUserAuthorization $authorization, WebsiteRepository $repository, string $prompt): array
    {
        return $this->request($authorization, retry: false)
            ->post("agents/repos/{$repository->full_name}/tasks", [
                'prompt' => $prompt,
                'base_ref' => $repository->default_branch,
                'create_pull_request' => true,
            ])
            ->throw()
            ->json();
    }

    /** @return array<string, mixed> */
    public function task(GithubUserAuthorization $authorization, WebsiteRepository $repository, string $taskId): array
    {
        return $this->request($authorization)
            ->get("agents/repos/{$repository->full_name}/tasks/{$taskId}")
            ->throw()
            ->json();
    }

    protected function request(GithubUserAuthorization $authorization, bool $retry = true): PendingRequest
    {
        $request = Http::baseUrl((string) config('services.github.api_url'))
            ->acceptJson()
            ->withHeaders([
                'X-GitHub-Api-Version' => '2026-03-10',
                'User-Agent' => config('app.name').' Copilot Remediation',
            ])
            ->withToken($this->oauth->accessToken($authorization))
            ->connectTimeout(5)
            ->timeout(30);

        return $retry ? $request->retry([500, 1500], throw: false) : $request;
    }
}
