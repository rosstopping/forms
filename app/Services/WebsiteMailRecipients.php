<?php

namespace App\Services;

use App\Models\User;
use App\Models\Website;
use Illuminate\Support\Str;

class WebsiteMailRecipients
{
    /** @return array<int, string> */
    public function forReports(Website $website): array
    {
        $website->loadMissing(['owner', 'members']);

        return User::query()
            ->where('role', User::ROLE_ADMIN)
            ->where(fn ($query) => $query->where('admin_site_access', 'all')->orWhereHas('assignedWebsites', fn ($query) => $query->whereKey($website->id)))
            ->pluck('email')
            ->push($website->owner && (! $website->owner->isAdmin() || $website->isAccessibleBy($website->owner)) ? $website->owner->email : null)
            ->concat($website->members->filter(fn (User $member): bool => ! $member->isAdmin() || $website->isAccessibleBy($member))->pluck('email'))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function isViewer(Website $website, string $email): bool
    {
        $website->loadMissing('members');

        return $website->members->contains(
            fn (User $member): bool => ! $member->isAdmin()
                && $member->pivot->role === Website::MEMBER_ROLE_VIEWER
                && Str::lower($member->email) === Str::lower($email),
        );
    }

    /**
     * @param  array<int, string>  $recipients
     * @return array<int, string>
     */
    public function withoutViewers(Website $website, array $recipients): array
    {
        if (! $website->exists) {
            return $recipients;
        }

        return collect($recipients)
            ->reject(fn (string $recipient): bool => $this->isViewer($website, $recipient))
            ->values()
            ->all();
    }
}
