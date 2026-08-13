<?php

namespace App\Services;

use App\Models\Website;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

class PixelRequestObserver
{
    public function rejected(string $reason, string $siteKey, string $url): void
    {
        $fingerprint = hash('sha256', $reason.'|'.$siteKey.'|'.$url);

        RateLimiter::attempt(
            'pixel-observation:rejected:'.$fingerprint,
            1,
            fn () => Log::notice('Pixel public request rejected.', [
                'reason' => $reason,
                'site_key_hash' => hash('sha256', $siteKey),
                'url_hash' => hash('sha256', $url),
            ]),
            3600,
        );
    }

    public function payloadRequested(Website $website, string $urlHash): void
    {
        RateLimiter::attempt(
            'pixel-observation:payload:'.$website->id.':'.$urlHash,
            1,
            fn () => Log::info('Pixel payload requested.', [
                'website_id' => $website->id,
                'url_hash' => $urlHash,
            ]),
            86400,
        );
    }

    public function payloadFailed(string $siteKey, string $url, Throwable $exception): void
    {
        Log::error('Pixel payload generation failed.', [
            'site_key_hash' => hash('sha256', $siteKey),
            'url_hash' => hash('sha256', $url),
            'exception' => $exception,
        ]);
    }
}
