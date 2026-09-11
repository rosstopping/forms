<?php

namespace App\Actions;

use App\Models\Website;
use App\Models\WebsiteDomain;
use App\Services\SearchConsolePropertyMatcher;
use Illuminate\Support\Facades\DB;

class VerifyWebsiteDomainFromSearchConsole
{
    public function __construct(public SearchConsolePropertyMatcher $propertyMatcher) {}

    public function handle(Website $website, string $propertyUrl, ?string $permissionLevel): bool
    {
        if ($permissionLevel !== 'siteOwner') {
            return false;
        }

        if (! $this->propertyMatcher->matches($website, $propertyUrl)) {
            return false;
        }

        return DB::transaction(function () use ($website): bool {
            $domain = $website->domains()
                ->where('is_primary', true)
                ->lockForUpdate()
                ->first();

            if (! $domain) {
                return false;
            }

            $canonicalDomain = WebsiteDomain::canonicalDomain($domain->domain);
            $conflictExists = WebsiteDomain::query()
                ->where('verified_domain', $canonicalDomain)
                ->whereKeyNot($domain->id)
                ->exists();

            if ($conflictExists) {
                $domain->update([
                    'ownership_status' => WebsiteDomain::OWNERSHIP_CONFLICT,
                    'verification_method' => 'search_console',
                ]);

                return false;
            }

            $domain->update([
                'ownership_status' => WebsiteDomain::OWNERSHIP_VERIFIED,
                'verification_method' => 'search_console',
            ]);

            return true;
        });
    }
}
