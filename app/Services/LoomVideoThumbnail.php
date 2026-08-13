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
                ->withUserAgent(config('app.name').' Video Preview')
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

        foreach (["//meta[@property='og:image']/@content", "//meta[@name='twitter:image']/@content"] as $query) {
            $imageUrl = trim((string) $xpath->evaluate("string({$query})"));

            if (filter_var($imageUrl, FILTER_VALIDATE_URL) && parse_url($imageUrl, PHP_URL_SCHEME) === 'https') {
                return $imageUrl;
            }
        }

        return null;
    }
}
