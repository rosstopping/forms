<?php

namespace App\Services;

use App\Models\WebsiteRepository;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class GithubAppClient
{
    /** @return array<string, mixed> */
    public function installation(int $installationId): array
    {
        return $this->appRequest()->get("app/installations/{$installationId}")->throw()->json();
    }

    /** @return array<int, array<string, mixed>> */
    public function repositories(int $installationId): array
    {
        $token = $this->installationToken($installationId);
        $request = $this->request($token);
        $repositories = [];
        $page = 1;

        do {
            $response = $request
                ->get('installation/repositories', [
                    'per_page' => 100,
                    'page' => $page,
                ])
                ->throw();
            $pageRepositories = $response->json('repositories', []);

            if (! is_array($pageRepositories)) {
                throw new RuntimeException('GitHub did not return a repository list.');
            }

            $repositories = array_merge($repositories, $pageRepositories);
            $totalRepositories = (int) $response->json('total_count', count($repositories));
            $page++;
        } while ($pageRepositories !== [] && count($repositories) < $totalRepositories);

        return $repositories;
    }

    /** @return array{commit_sha: string, archive: string} */
    public function repositoryArchive(WebsiteRepository $repository, ?string $sourceRef = null): array
    {
        $token = $this->installationToken($repository->installation->installation_id);
        $request = $this->request($token);
        $reference = rawurlencode($sourceRef ?: $repository->default_branch);
        $commitSha = $request->get("repos/{$repository->full_name}/commits/{$reference}")
            ->throw()
            ->json('sha');

        if (! is_string($commitSha) || ! preg_match('/^[a-f0-9]{40}$/i', $commitSha)) {
            throw new RuntimeException('GitHub did not return a valid commit for the repository branch.');
        }

        $archive = $request->get("repos/{$repository->full_name}/zipball/{$commitSha}")
            ->throw()
            ->body();

        if ($archive === '') {
            throw new RuntimeException('GitHub returned an empty repository archive.');
        }

        return ['commit_sha' => $commitSha, 'archive' => $archive];
    }

    public function branchCommit(WebsiteRepository $repository): string
    {
        $token = $this->installationToken($repository->installation->installation_id);
        $reference = rawurlencode($repository->default_branch);
        $sha = $this->request($token)->get("repos/{$repository->full_name}/commits/{$reference}")->throw()->json('sha');

        if (! is_string($sha) || ! preg_match('/^[a-f0-9]{40}$/i', $sha)) {
            throw new RuntimeException('GitHub did not return a valid publishing branch commit.');
        }

        return strtolower($sha);
    }

    /** @return array{commit_sha: string, archive: string, workflow_run_id: int} */
    public function workflowArtifactArchive(WebsiteRepository $repository, ?string $commitSha = null, ?int $workflowRunId = null): array
    {
        $headSha = $this->branchCommit($repository);

        if ($commitSha !== null && strtolower($commitSha) !== $headSha) {
            throw new RuntimeException('This build has been superseded by a newer commit. Waiting for the latest build.');
        }

        $token = $this->installationToken($repository->installation->installation_id);
        $request = $this->request($token);
        $base = "repos/{$repository->full_name}/actions";

        if ($workflowRunId === null) {
            $workflow = rawurlencode(basename($repository->wordpress_workflow_path));
            $workflowRunId = (int) $request->get("{$base}/workflows/{$workflow}/runs", [
                'branch' => $repository->default_branch,
                'head_sha' => $headSha,
                'per_page' => 1,
                'exclude_pull_requests' => 'true',
            ])->throw()->json('workflow_runs.0.id');
        }

        if ($workflowRunId < 1) {
            throw new RuntimeException('No build is available for the latest commit. Run the configured GitHub Actions workflow first.');
        }

        $run = $request->get("{$base}/runs/{$workflowRunId}")->throw()->json();

        if ((int) data_get($run, 'id') !== $workflowRunId
            || data_get($run, 'status') !== 'completed'
            || data_get($run, 'conclusion') !== 'success'
            || ! in_array(data_get($run, 'event'), ['push', 'workflow_dispatch'], true)
            || data_get($run, 'head_sha') !== $headSha
            || data_get($run, 'head_branch') !== $repository->default_branch
            || data_get($run, 'path') !== $repository->wordpress_workflow_path
            || (int) data_get($run, 'repository.id') !== (int) $repository->repository_id
            || (int) data_get($run, 'head_repository.id') !== (int) $repository->repository_id) {
            throw new RuntimeException('WordPress deployment requires a successful build of the latest publishing branch commit from the configured workflow.');
        }

        $artifacts = [];
        $page = 1;

        do {
            $response = $request->get("{$base}/runs/{$workflowRunId}/artifacts", ['per_page' => 100, 'page' => $page])->throw();
            $batch = $response->json('artifacts', []);

            if (! is_array($batch)) {
                throw new RuntimeException('GitHub did not return an artifact list.');
            }
            $artifacts = array_merge($artifacts, $batch);
            $page++;
        } while ($batch !== [] && count($artifacts) < (int) $response->json('total_count') && $page <= 10);

        $matches = collect($artifacts)->where('name', $repository->wordpress_artifact_name)->values();
        $artifact = $matches->first();

        if ($matches->count() !== 1 || ! is_array($artifact) || ($artifact['expired'] ?? true)
            || (int) ($artifact['id'] ?? 0) < 1) {
            throw new RuntimeException('The configured build artifact is missing, ambiguous or expired. Re-run the GitHub Actions workflow.');
        }

        $maxBytes = 50 * 1024 * 1024;

        if ((int) ($artifact['size_in_bytes'] ?? 0) > $maxBytes) {
            throw new RuntimeException('The build artifact exceeds the 50 MB deployment limit.');
        }

        $redirect = $request->withoutRedirecting()->get("{$base}/artifacts/{$artifact['id']}/zip")->throw();
        $location = $redirect->header('Location');
        $host = strtolower((string) parse_url($location, PHP_URL_HOST));

        if ($redirect->status() !== 302 || parse_url($location, PHP_URL_SCHEME) !== 'https'
            || parse_url($location, PHP_URL_USER) !== null || parse_url($location, PHP_URL_PASS) !== null
            || parse_url($location, PHP_URL_PORT) !== null
            || (! str_ends_with($host, '.blob.core.windows.net') && ! str_ends_with($host, '.githubusercontent.com'))) {
            throw new RuntimeException('GitHub returned an unexpected artifact download location.');
        }

        $archive = Http::withoutRedirecting()->connectTimeout(5)->timeout(60)
            ->withOptions(['progress' => function (int|float $total, int|float $downloaded) use ($maxBytes): void {
                if ($total > $maxBytes || $downloaded > $maxBytes) {
                    throw new RuntimeException('The build artifact exceeds the 50 MB deployment limit.');
                }
            }])
            ->get($location)->throw();

        if ($archive->status() !== 200 || $archive->body() === '' || strlen($archive->body()) > $maxBytes) {
            throw new RuntimeException('GitHub returned an empty, oversized or invalid build artifact.');
        }

        if (filled($artifact['digest'] ?? null)
            && ! hash_equals($artifact['digest'], 'sha256:'.hash('sha256', $archive->body()))) {
            throw new RuntimeException('The build artifact checksum does not match GitHub.');
        }

        return ['commit_sha' => $headSha, 'archive' => $archive->body(), 'workflow_run_id' => $workflowRunId];
    }

    /** @return array{pull_request: array<string, mixed>, files: array<int, array<string, mixed>>} */
    public function pullRequestDetails(WebsiteRepository $repository, int $pullRequestNumber): array
    {
        $token = $this->installationToken($repository->installation->installation_id);
        $request = $this->request($token);

        return [
            'pull_request' => $request->get("repos/{$repository->full_name}/pulls/{$pullRequestNumber}")->throw()->json(),
            'files' => $request->get("repos/{$repository->full_name}/pulls/{$pullRequestNumber}/files", ['per_page' => 100])->throw()->json(),
        ];
    }

    /** @return array<string, mixed> */
    public function pullRequestForHead(WebsiteRepository $repository, string $headRef): array
    {
        $token = $this->installationToken($repository->installation->installation_id);
        $owner = Str::before($repository->full_name, '/');
        $pullRequest = $this->request($token)
            ->get("repos/{$repository->full_name}/pulls", [
                'state' => 'all',
                'head' => $owner.':'.$headRef,
                'per_page' => 1,
            ])
            ->throw()
            ->collect()
            ->first();

        if (! is_array($pullRequest)) {
            throw new RuntimeException("GitHub did not return a pull request for branch {$headRef}.");
        }

        return $pullRequest;
    }

    public function installationToken(int $installationId): string
    {
        $token = $this->appRequest()
            ->post("app/installations/{$installationId}/access_tokens")
            ->throw()
            ->json('token');

        if (! is_string($token) || $token === '') {
            throw new RuntimeException('GitHub did not return an installation access token.');
        }

        return $token;
    }

    protected function appRequest(): PendingRequest
    {
        return $this->request($this->jwt());
    }

    protected function request(string $token): PendingRequest
    {
        return Http::baseUrl((string) config('services.github.api_url'))
            ->acceptJson()
            ->withHeaders([
                'X-GitHub-Api-Version' => '2022-11-28',
                'User-Agent' => config('app.name').' GitHub App',
            ])
            ->withToken($token)
            ->connectTimeout(5)
            ->timeout(15)
            ->retry([200, 500], throw: false);
    }

    protected function jwt(): string
    {
        $appId = (string) config('services.github.app_id');
        $privateKey = Str::of((string) config('services.github.private_key'))->replace('\\n', "\n")->toString();

        if ($appId === '' || $privateKey === '') {
            throw new RuntimeException('The GitHub App is not configured.');
        }

        $now = now()->timestamp;
        $header = $this->base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
        $payload = $this->base64UrlEncode(json_encode(['iat' => $now - 60, 'exp' => $now + 540, 'iss' => $appId], JSON_THROW_ON_ERROR));
        $unsignedToken = $header.'.'.$payload;

        if (! openssl_sign($unsignedToken, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('The GitHub App private key is invalid.');
        }

        return $unsignedToken.'.'.$this->base64UrlEncode($signature);
    }

    protected function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
