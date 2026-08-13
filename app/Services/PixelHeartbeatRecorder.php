<?php

namespace App\Services;

use App\Models\PixelPageSighting;

class PixelHeartbeatRecorder
{
    public function __construct(
        private PixelSiteResolver $sites,
        private PixelUrlNormalizer $urls,
    ) {}

    public function record(string $siteKey, string $url, string $version): void
    {
        $website = $this->sites->resolve($siteKey, $url);

        if (! $website) {
            return;
        }

        $seenAt = now();
        $hostname = $this->urls->normalizeHost((string) parse_url($url, PHP_URL_HOST));
        $canonicalUrl = 'https://'.$this->urls->normalizeForMatch($url);
        $urlHash = $this->urls->hash($url);

        PixelPageSighting::query()->upsert([[
            'website_id' => $website->id,
            'url_hash' => $urlHash,
            'url' => $canonicalUrl,
            'hostname' => $hostname,
            'first_seen_at' => $seenAt,
            'last_seen_at' => $seenAt,
            'created_at' => $seenAt,
            'updated_at' => $seenAt,
        ]], uniqueBy: ['website_id', 'url_hash'], update: [
            'url',
            'hostname',
            'last_seen_at',
            'updated_at',
        ]);

        $website->forceFill([
            'pixel_last_seen_at' => $seenAt,
            'pixel_last_seen_url' => $canonicalUrl,
            'pixel_last_seen_hostname' => $hostname,
            'pixel_version' => $version,
        ])->save();
    }
}
