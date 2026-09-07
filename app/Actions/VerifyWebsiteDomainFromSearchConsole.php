<?php

namespace App\Actions;

use App\Models\Website;
use App\Models\WebsiteDomain;
use Illuminate\Support\Facades\DB;

class VerifyWebsiteDomainFromSearchConsole
{
    public function handle(Website $website, string $propertyUrl, ?string $permissionLevel): bool
    {
        if ($permissionLevel !== 'siteOwner') {
            return false;
        }

        $property = $this->propertyDomain($propertyUrl);

        if ($property === null) {
            return false;
        }

        return DB::transaction(function () use ($property, $website): bool {
            $domain = $website->domains()
                ->where('is_primary', true)
                ->lockForUpdate()
                ->first();

            if (! $domain || ! $this->matches($domain->domain, $property)) {
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

    /** @return array{domain: string, includes_subdomains: bool}|null */
    private function propertyDomain(string $propertyUrl): ?array
    {
        if (str_starts_with($propertyUrl, 'sc-domain:')) {
            $domain = substr($propertyUrl, strlen('sc-domain:'));

            return $domain !== '' ? ['domain' => strtolower($domain), 'includes_subdomains' => true] : null;
        }

        $host = parse_url($propertyUrl, PHP_URL_HOST);

        return is_string($host) && $host !== ''
            ? ['domain' => strtolower($host), 'includes_subdomains' => false]
            : null;
    }

    /** @param array{domain: string, includes_subdomains: bool} $property */
    private function matches(string $websiteDomain, array $property): bool
    {
        if ($property['includes_subdomains']) {
            return WebsiteDomain::canonicalDomain($websiteDomain) === WebsiteDomain::canonicalDomain($property['domain']);
        }

        return strtolower($websiteDomain) === $property['domain'];
    }
}
