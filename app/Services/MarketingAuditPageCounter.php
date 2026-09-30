<?php

namespace App\Services;

use DOMDocument;
use Illuminate\Support\Facades\Http;

class MarketingAuditPageCounter
{
    public function __construct(private SitemapFetcher $sitemapFetcher) {}

    /** @return array{count: int|null, partial: bool} */
    public function count(string $websiteUrl): array
    {
        $scheme = parse_url($websiteUrl, PHP_URL_SCHEME);
        $host = parse_url($websiteUrl, PHP_URL_HOST);
        $port = parse_url($websiteUrl, PHP_URL_PORT);

        if (! in_array($scheme, ['http', 'https'], true) || ! is_string($host) || $host === '') {
            return ['count' => null, 'partial' => false];
        }

        if (! app()->environment('testing')) {
            $addresses = gethostbynamel($host);
            if ($addresses === false || $addresses === [] || collect($addresses)->contains(fn (string $address): bool => filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false)) {
                return ['count' => null, 'partial' => false];
            }
        }

        $origin = $scheme.'://'.$host.($port ? ':'.$port : '');
        $root = $this->read($origin.'/sitemap.xml');

        if ($root === null) {
            return ['count' => null, 'partial' => false];
        }

        if ($root['type'] === 'urlset') {
            $pageUrls = array_filter($root['urls'], fn (string $url): bool => $this->belongsToOrigin($url, $scheme, $host, $port));

            return ['count' => count($pageUrls), 'partial' => $root['partial'] || count($pageUrls) !== count($root['urls'])];
        }

        $pageUrls = [];
        $partial = $root['partial'] || count($root['urls']) > 3;
        $loaded = 0;

        foreach (array_slice($root['urls'], 0, 3) as $sitemapUrl) {
            if (! $this->belongsToOrigin($sitemapUrl, $scheme, $host, $port)) {
                $partial = true;

                continue;
            }

            $child = $this->read($sitemapUrl);
            if ($child === null || $child['type'] !== 'urlset') {
                $partial = true;

                continue;
            }

            $loaded++;
            $partial = $partial || $child['partial'];
            foreach ($child['urls'] as $url) {
                if (! $this->belongsToOrigin($url, $scheme, $host, $port)) {
                    $partial = true;

                    continue;
                }

                $pageUrls[$url] = true;
                if (count($pageUrls) >= 5000) {
                    return ['count' => count($pageUrls), 'partial' => true];
                }
            }
        }

        return ['count' => $loaded > 0 ? count($pageUrls) : null, 'partial' => $partial];
    }

    private function belongsToOrigin(string $url, string $scheme, string $host, ?int $port): bool
    {
        $parts = parse_url($url);

        return is_array($parts)
            && ($parts['scheme'] ?? null) === $scheme
            && ($parts['host'] ?? null) === $host
            && ($parts['port'] ?? null) === $port
            && ! isset($parts['user'])
            && ! isset($parts['pass']);
    }

    /** @return array{type: string, urls: array<int, string>, partial: bool}|null */
    private function read(string $url): ?array
    {
        try {
            $response = $this->sitemapFetcher->fetch(
                Http::accept('application/xml,text/xml')->connectTimeout(3)->timeout(6)->withOptions(['allow_redirects' => false]),
                $url,
            );
        } catch (\Throwable) {
            return null;
        }

        if (! $response->successful() || strlen($response->body()) > 2_000_000) {
            return null;
        }

        $document = new DOMDocument;
        if (! @$document->loadXML($response->body(), LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) {
            return null;
        }

        $type = $document->documentElement?->localName;
        if (! in_array($type, ['urlset', 'sitemapindex'], true)) {
            return null;
        }

        $urls = [];
        $elementName = $type === 'urlset' ? 'url' : 'sitemap';
        foreach ($document->getElementsByTagName($elementName) as $element) {
            $location = trim($element->getElementsByTagName('loc')->item(0)?->textContent ?? '');
            if ($location !== '') {
                $urls[$location] = true;
            }
            if (count($urls) >= 5000) {
                return ['type' => $type, 'urls' => array_keys($urls), 'partial' => true];
            }
        }

        return ['type' => $type, 'urls' => array_keys($urls), 'partial' => false];
    }
}
