<?php

namespace App\Services;

use App\Models\GithubInstallation;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class GithubCustomerRepositories
{
    public function __construct(private GithubOAuthClient $oauth) {}

    /** @return Collection<int, array<string, mixed>> */
    public function available(User $user): Collection
    {
        $authorization = $user->githubAuthorization;
        abort_unless($authorization, 403, 'Connect your GitHub account first.');

        return collect($this->oauth->installations($authorization))
            ->filter(fn (array $details): bool => $this->isUsable($details))
            ->flatMap(function (array $details) use ($authorization, $user): array {
                $installation = $this->remember($details, $user);

                return collect($this->oauth->installationRepositories($authorization, $installation->installation_id))
                    ->filter(fn (array $repository): bool => data_get($repository, 'permissions.push') === true)
                    ->map(fn (array $repository): array => [
                        ...$repository,
                        'github_installation_id' => $installation->id,
                        'account_login' => $installation->account_login,
                    ])->values()->all();
            })->sortBy('full_name')->values();
    }

    /** @return array<string, mixed> */
    public function selected(User $user, GithubInstallation $installation, int $repositoryId): array
    {
        $this->verifyInstallation($user, $installation->installation_id);
        $repository = collect($this->oauth->installationRepositories($user->githubAuthorization, $installation->installation_id))
            ->firstWhere('id', $repositoryId);
        if (! is_array($repository) || data_get($repository, 'permissions.push') !== true) {
            throw ValidationException::withMessages(['repository' => 'Your GitHub account cannot write to this repository through the Sitewell App.']);
        }

        return $repository;
    }

    public function verifyInstallation(User $user, int $externalId): GithubInstallation
    {
        abort_unless($user->githubAuthorization, 403, 'Connect your GitHub account first.');
        $details = collect($this->oauth->installations($user->githubAuthorization))->firstWhere('id', $externalId);
        abort_unless(is_array($details) && $this->isUsable($details), 403, 'Your GitHub account cannot access this active Sitewell installation.');

        return $this->remember($details, $user);
    }

    /** @param array<string, mixed> $details */
    private function isUsable(array $details): bool
    {
        return (int) ($details['app_id'] ?? 0) > 0
            && (string) $details['app_id'] === (string) config('services.github.app_id')
            && empty($details['suspended_at'])
            && data_get($details, 'permissions.contents') === 'write'
            && data_get($details, 'permissions.pull_requests') === 'write';
    }

    /** @param array<string, mixed> $details */
    private function remember(array $details, User $user): GithubInstallation
    {
        $installation = GithubInstallation::query()->firstOrCreate(
            ['installation_id' => $details['id']],
            [
                'installed_by' => $user->id,
                'account_id' => $details['account']['id'],
                'account_login' => $details['account']['login'],
                'account_type' => $details['account']['type'],
                'repository_selection' => $details['repository_selection'],
                'permissions' => $details['permissions'],
                'status' => GithubInstallation::STATUS_ACTIVE,
            ],
        );
        $installation->update([
            'account_login' => $details['account']['login'],
            'repository_selection' => $details['repository_selection'],
            'permissions' => $details['permissions'],
            'status' => GithubInstallation::STATUS_ACTIVE,
            'suspended_at' => null,
        ]);

        return $installation;
    }
}
