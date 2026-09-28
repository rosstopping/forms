<?php

namespace App\Services;

use App\Models\User;

class GithubRepositoryPilot
{
    public function enabledFor(User $user): bool
    {
        return config('copilot_sdk.customer_repositories_enabled')
            && $user->isAdmin()
            && in_array((string) $user->getKey(), config('copilot_sdk.customer_repository_admin_ids', []), true);
    }
}
