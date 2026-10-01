<?php

namespace App\Services;

use DOMDocument;
use Illuminate\Support\Facades\Http;

class MarketingAuditPageCounter
{
    public function __construct(private SitemapFetcher $sitemapFetcher) {}

    /** @return array{count: int|null, partial: bool, matching_domain: int, mismatched_domain: int, mismatched_host: string|null} */
    public function count(string $websiteUrl): array
    {
        $scheme = parse_url($websiteUrl, PHP_URL_SCHEME);
        $host = parse_url($websiteUrl, PHP_URL_HOST);
        $port = parse_url($websiteUrl, PHP_URL_PORT);

        if (! in_array($scheme, ['http', 'https'], true) || ! is_string($host) || $host === '') {
            return $this->result(null, false, is_string($host) ? $host : null);
        }

        if (! app()->environment('testing')) {
            $addresses = gethostbynamel($host);
            if ($addresses === false || $addresses === [] || collect($addresses)->contains(fn (string $address): bool => filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false)) {
                return $this->result(null, false, $host);
            }
        }

        $origin = $scheme.'://'.$host.($port ? ':'.$port : '');
        $root = $this->read($origin.'/sitemap.xml');

        if ($root === null) {
            return $this->result(null, false, $host);
        }

        if ($root['type'] === 'urlset') {
            return $this->result($root['urls'], $root['partial'], $host);
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
                $pageUrls[$url] = true;
                if (count($pageUrls) >= 5000) {
                    return $this->result(array_keys($pageUrls), true, $host);
                }
            }
        }

        return $this->result($loaded > 0 ? array_keys($pageUrls) : null, $partial, $host);
    }

    /**
     * @param  array<int, string>|null  $urls
     * @return array{count: int|null, partial: bool, matching_domain: int, mismatched_domain: int, mismatched_host: string|null}
     */
    private function result(?array $urls, bool $partial, ?string $host): array
    {
        $matchingDomain = 0;
        $mismatchedDomain = 0;
        $mismatchedHost = null;

        foreach ($urls ?? [] as $url) {
            $listedHost = parse_url($url, PHP_URL_HOST);

            if ($listedHost === $host) {
                $matchingDomain++;
            } else {
                $mismatchedDomain++;
                $mismatchedHost ??= is_string($listedHost) ? $listedHost : null;
            }
        }

        return [
            'count' => $urls !== null ? count($urls) : null,
            'partial' => $partial,
            'matching_domain' => $matchingDomain,
            'mismatched_domain' => $mismatchedDomain,
            'mismatched_host' => $mismatchedHost,
        ];
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
