<?php

namespace App\Services;

use App\Models\Website;

class PixelSiteResolver
{
    public function __construct(private PixelUrlNormalizer $urls) {}

    public function resolve(string $siteKey, string $url): ?Website
    {
        $website = Website::query()
            ->with('domains:id,website_id,domain')
            ->where('pixel_public_key', $siteKey)
            ->where('pixel_enabled', true)
            ->first();

        if (! $website) {
            return null;
        }

        $urlHost = $this->urls->normalizeHost((string) parse_url($url, PHP_URL_HOST));
        $belongsToWebsite = $website->domains->contains(
            fn ($domain): bool => $this->urls->normalizeHost($domain->domain) === $urlHost,
        );

        return $belongsToWebsite ? $website : null;
    }
}
