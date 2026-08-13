<?php

namespace App\Services;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use Throwable;

class LoomVideoThumbnail
{
    public function fetch(?string $videoUrl): ?string
    {
        if (! $this->isLoomShareUrl($videoUrl)) {
            return null;
        }

        try {
            $response = Http::accept('text/html')
                ->withUserAgent('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/127.0.0.0 Safari/537.36')
                ->connectTimeout(3)
                ->timeout(5)
                ->withOptions(['allow_redirects' => false])
                ->get($videoUrl);

            if (! $response->successful()) {
                return null;
            }

            return $this->extractImageUrl($response->body());
        } catch (Throwable) {
            return null;
        }
    }

    protected function isLoomShareUrl(?string $url): bool
    {
        if (! is_string($url) || parse_url($url, PHP_URL_SCHEME) !== 'https') {
            return false;
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $path = (string) parse_url($url, PHP_URL_PATH);

        return in_array($host, ['loom.com', 'www.loom.com'], true) && str_starts_with($path, '/share/');
    }

    protected function extractImageUrl(string $html): ?string
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadHTML($html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            return null;
        }

        $xpath = new DOMXPath($document);

        foreach ([
            "//link[contains(concat(' ', normalize-space(translate(@rel, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz')), ' '), ' preload ') and translate(@as, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz') = 'image' and contains(@href, '/sessions/thumbnails/')]/@href",
            "//meta[@property='og:image']/@content",
            "//meta[@name='twitter:image']/@content",
        ] as $query) {
            $imageUrl = trim((string) $xpath->evaluate("string({$query})"));

            if ($this->isSessionThumbnail($imageUrl)) {
                return $imageUrl;
            }
        }

        return null;
    }

    protected function isSessionThumbnail(string $imageUrl): bool
    {
        return filter_var($imageUrl, FILTER_VALIDATE_URL)
            && parse_url($imageUrl, PHP_URL_SCHEME) === 'https'
            && strtolower((string) parse_url($imageUrl, PHP_URL_HOST)) === 'cdn.loom.com'
            && str_starts_with((string) parse_url($imageUrl, PHP_URL_PATH), '/sessions/thumbnails/');
    }
}
