<?php

namespace App\Services;

use App\Models\User;

class GithubRepositoryPilot
{
    public function enabledFor(User $user): bool
    {
        return $user->isAdmin();
    }
}
