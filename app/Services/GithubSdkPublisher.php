<?php

namespace App\Services;

use App\Models\CopilotSdkTestRun;
use App\Models\User;
use App\Models\WebsiteRepository;
use DomainException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Gate;

class GithubSdkPublisher extends GithubAppClient
{
    public function authorize(User $user, WebsiteRepository $repository): void
    {
        abort_unless($user->isAdmin(), 403);
        Gate::forUser($user)->authorize('update', $repository->website);
        $selected = app(GithubCustomerRepositories::class)->selected($user, $repository->installation, $repository->repository_id);
        if (($selected['full_name'] ?? null) !== $repository->full_name || ($selected['id'] ?? null) !== $repository->repository_id) {
            throw new DomainException('The repository identity changed. Reconnect it before testing.');
        }
    }

    private function repositoryRequest(WebsiteRepository $repository): PendingRequest
    {
        $token = $this->appRequest()->post("app/installations/{$repository->installation->installation_id}/access_tokens", [
            'repository_ids' => [$repository->repository_id],
            'permissions' => ['contents' => 'write', 'pull_requests' => 'write'],
        ])->throw()->json('token');
        if (! is_string($token) || $token === '') {
            throw new DomainException('Unable to obtain repository-scoped access.');
        }

        return $this->request($token)->retry(1)->withoutRedirecting();
    }

    /** @return array{base_sha: string, tree_sha: string, original: string} */
    public function snapshot(WebsiteRepository $repository, string $path): array
    {
        $this->validatePath($repository, $path);
        $request = $this->repositoryRequest($repository);
        $prefix = "repos/{$repository->full_name}";
        $commit = $request->get($prefix.'/commits/'.rawurlencode($repository->default_branch))->throw()->json();
        $baseSha = $this->sha($commit['sha'] ?? null);
        $rootTree = $this->sha(data_get($commit, 'commit.tree.sha'));
        $treeSha = $rootTree;
        $parts = explode('/', $path);
        foreach ($parts as $index => $part) {
            $tree = $request->get($prefix.'/git/trees/'.$treeSha)->throw()->json();
            if (($tree['truncated'] ?? true) !== false) {
                throw new DomainException('Cannot verify an incomplete repository tree.');
            }
            $entry = collect($tree['tree'] ?? [])->firstWhere('path', $part);
            $last = $index === count($parts) - 1;
            if (! is_array($entry) || ($entry['mode'] ?? null) !== ($last ? '100644' : '040000')
                || ($entry['type'] ?? null) !== ($last ? 'blob' : 'tree')) {
                throw new DomainException('Choose an existing regular HTML file; symlinks and submodules are not supported.');
            }
            $treeSha = $this->sha($entry['sha'] ?? null);
        }
        if (($entry['size'] ?? 8193) > 8192) {
            throw new DomainException('The test file must be under 8 KB.');
        }
        $blob = $request->get($prefix.'/git/blobs/'.$treeSha)->throw()->json();
        $original = ($blob['encoding'] ?? null) === 'base64' ? base64_decode($blob['content'] ?? '', true) : false;
        if (! is_string($original) || strlen($original) > 8192
            || sha1('blob '.strlen($original)."\0".$original) !== $treeSha) {
            throw new DomainException('The repository file could not be verified.');
        }

        return ['base_sha' => $baseSha, 'tree_sha' => $rootTree, 'original' => $original];
    }

    private function validatePath(WebsiteRepository $repository, string $path): void
    {
        if (strlen($path) > 240 || substr_count($path, '/') > 8 || ! preg_match('/\A(?:[a-zA-Z0-9_-]+\/)*[a-zA-Z0-9_-]+\.html\z/', $path)) {
            throw new DomainException('Use a repository-relative .html path without dot segments or special characters.');
        }
        $projectPath = trim((string) $repository->project_path, '/');
        if ($projectPath !== '' && ! str_starts_with($path, $projectPath.'/')) {
            throw new DomainException('The file must be inside the connected project path.');
        }
    }

    public function publish(CopilotSdkTestRun $run): string
    {
        $repository = $run->repository->fresh(['website', 'installation']);
        $this->authorize($run->requester->fresh(), $repository);
        $this->validatePath($repository, $run->path);
        if ($repository->repository_id !== $run->repository_id || $repository->installation->installation_id !== $run->installation_id
            || $repository->full_name !== $run->full_name || $repository->default_branch !== $run->base_branch) {
            throw new DomainException('The connection changed during this test. Start a new test.');
        }
        if ($run->replacement !== app(CopilotSdkTitleRunner::class)->expected($run->original, $run->title)
            || $run->replacement === $run->original) {
            throw new DomainException('The saved change failed independent validation.');
        }
        $request = $this->repositoryRequest($repository);
        $prefix = "repos/{$run->full_name}";
        $head = $request->get($prefix.'/git/ref/heads/'.$run->branch);
        if ($head->status() !== 404) {
            $head->throw();
            if (! $run->commit_sha || $head->json('object.sha') !== $run->commit_sha) {
                throw new DomainException('The test branch was changed outside this run. It will not be overwritten.');
            }
        }
        $pulls = $request->get($prefix.'/pulls', ['state' => 'all', 'head' => explode('/', $run->full_name)[0].':'.$run->branch, 'per_page' => 100])->throw()->json();
        foreach ($pulls as $pull) {
            if ($run->commit_sha && data_get($pull, 'head.sha') === $run->commit_sha && data_get($pull, 'base.ref') === $run->base_branch) {
                return $this->pullUrl($pull, $run);
            }
        }
        if ($request->get($prefix.'/commits/'.rawurlencode($run->base_branch))->throw()->json('sha') !== $run->base_sha) {
            throw new DomainException('The base branch changed. Start a new test against the latest commit.');
        }
        if (! $run->commit_sha) {
            $treeSha = $this->sha($request->post($prefix.'/git/trees', [
                'base_tree' => $run->tree_sha,
                'tree' => [['path' => $run->path, 'mode' => '100644', 'type' => 'blob', 'content' => $run->replacement]],
            ])->throw()->json('sha'));
            $commitSha = $this->sha($request->post($prefix.'/git/commits', [
                'message' => 'Test Sitewell Copilot SDK title editing', 'tree' => $treeSha, 'parents' => [$run->base_sha],
            ])->throw()->json('sha'));
            $run->update(['commit_sha' => $commitSha]);
        }
        if ($head->status() === 404) {
            $request->post($prefix.'/git/refs', ['ref' => 'refs/heads/'.$run->branch, 'sha' => $run->commit_sha])->throw();
        }
        $pull = $request->post($prefix.'/pulls', [
            'title' => 'Sitewell SDK test: update page title', 'head' => $run->branch, 'base' => $run->base_branch, 'draft' => true,
            'body' => "Changes only the approved HTML title in `{$run->path}`.\n\nValidation: Sitewell independently checked that every other byte is unchanged. No project scripts or builds were executed.\n\nSDK run: {$run->run_id}\n\nReview and merge manually when ready.",
        ])->throw()->json();

        return $this->pullUrl($pull, $run);
    }

    /** @param array<string, mixed> $pull */
    private function pullUrl(array $pull, CopilotSdkTestRun $run): string
    {
        if (! is_int($pull['number'] ?? null) || $pull['number'] < 1) {
            throw new DomainException('GitHub did not return a valid pull request. Resume this run to reconcile it.');
        }

        $url = 'https://github.com/'.$run->full_name.'/pull/'.$pull['number'];
        $run->update([
            'pull_request_url' => $url,
            'status' => ! empty($pull['merged_at']) ? 'pull_request_merged' : (($pull['state'] ?? 'open') === 'closed' ? 'pull_request_closed' : 'pull_request_open'),
        ]);

        return $url;
    }

    private function sha(mixed $sha): string
    {
        if (! is_string($sha) || ! preg_match('/\A[a-f0-9]{40}\z/', $sha)) {
            throw new DomainException('GitHub did not return a valid commit or tree identity.');
        }

        return $sha;
    }
}
