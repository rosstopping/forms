<?php

namespace App\Services;

use App\Models\Website;
use App\Models\WebsiteDomain;

class SearchConsolePropertyMatcher
{
    public function matches(Website $website, string $propertyUrl): bool
    {
        $websiteDomain = $website->primaryDomain()?->domain;

        if (! $websiteDomain) {
            return false;
        }

        if (str_starts_with($propertyUrl, 'sc-domain:')) {
            $propertyDomain = substr($propertyUrl, strlen('sc-domain:'));

            return $propertyDomain !== ''
                && WebsiteDomain::canonicalDomain(strtolower($websiteDomain)) === WebsiteDomain::canonicalDomain(strtolower($propertyDomain));
        }

        $propertyHost = parse_url($propertyUrl, PHP_URL_HOST);

        return is_string($propertyHost)
            && $propertyHost !== ''
            && strtolower($websiteDomain) === strtolower($propertyHost);
    }
}
