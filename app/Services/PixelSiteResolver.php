<?php

namespace App\Services;

use App\Models\Website;

class PixelSiteResolver
{
    public function __construct(
        private PixelUrlNormalizer $urls,
        private PixelRequestObserver $observer,
    ) {}

    public function resolve(string $siteKey, string $url): ?Website
    {
        $website = Website::query()
            ->with('domains:id,website_id,domain')
            ->where('pixel_public_key', $siteKey)
            ->where('pixel_enabled', true)
            ->first();

        if (! $website) {
            $this->observer->rejected('unknown_or_disabled_site', $siteKey, $url);

            return null;
        }

        $urlHost = $this->urls->normalizeHost((string) parse_url($url, PHP_URL_HOST));
        $belongsToWebsite = $website->domains->contains(
            fn ($domain): bool => $this->urls->normalizeHost($domain->domain) === $urlHost,
        );

        if (! $belongsToWebsite) {
            $this->observer->rejected('invalid_domain', $siteKey, $url);

            return null;
        }

        return $website;
    }
}
