<?php

namespace App\Services;

use App\Models\User;
use App\Models\Website;

class WebsiteMailRecipients
{
    /** @return array<int, string> */
    public function for(Website $website): array
    {
        $website->loadMissing('owner');

        return User::query()
            ->where('role', User::ROLE_ADMIN)
            ->pluck('email')
            ->push($website->owner?->email)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
