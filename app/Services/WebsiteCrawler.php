<?php

namespace App\Services;

use App\Models\Website;
use DOMDocument;
use DOMXPath;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class WebsiteCrawler
{
    /** @return array<int, array<string, mixed>> */
    public function crawl(Website $website): array
    {
        $domain = $website->primaryDomain()?->domain;

        if (! $domain) {
            throw new RuntimeException('The website does not have a registered domain.');
        }

        $this->ensurePublicDomain($domain);

        $baseUrl = 'https://'.$domain;
        $allowedHosts = $website->domains->pluck('domain')->push($domain)
            ->map(fn (string $host) => $this->normaliseHost($host))->unique()->all();

        foreach ($allowedHosts as $allowedHost) {
            $this->ensurePublicDomain($allowedHost);
        }

        $queue = [['url' => $baseUrl, 'depth' => 0]];
        $queue = [...$queue, ...$this->sitemapUrls($baseUrl, $allowedHosts)];
        $seen = [];
        $pages = [];
        $maximumPages = (int) config('forms.health_reports.max_pages', 25);
        $maximumDepth = (int) config('forms.health_reports.max_depth', 2);

        while ($queue !== [] && count($pages) < $maximumPages) {
            $candidate = array_shift($queue);
            $url = $this->normaliseUrl($candidate['url']);

            if (! $url || isset($seen[$url]) || $candidate['depth'] > $maximumDepth || ! $this->isCrawlable($url, $allowedHosts)) {
                continue;
            }

            $seen[$url] = true;
            $startedAt = microtime(true);

            try {
                $response = $this->request()->get($url);
                $responseTime = (int) round((microtime(true) - $startedAt) * 1000);
                $contentType = Str::lower((string) $response->header('content-type'));
                $analysis = Str::contains($contentType, ['text/html', 'application/xhtml+xml']) || $contentType === ''
                    ? $this->analyseHtml($this->boundedBody($response->body()), $url, $candidate['depth'])
                    : $this->emptyAnalysis($url, $candidate['depth']);

                foreach ($analysis['discovered_links'] as $link) {
                    $queue[] = ['url' => $link, 'depth' => $candidate['depth'] + 1];
                }

                unset($analysis['discovered_links']);
                $pages[] = [
                    ...$analysis,
                    'status_code' => $response->status(),
                    'response_time_ms' => $responseTime,
                ];
            } catch (ConnectionException $exception) {
                $pages[] = [
                    ...$this->emptyAnalysis($url, $candidate['depth']),
                    'checks' => [$this->check('page_available', 'Page available', 'failed', 'The page could not be reached: '.$exception->getMessage())],
                ];
                unset($pages[array_key_last($pages)]['discovered_links']);
            }

            $delay = (int) config('forms.health_reports.crawl_delay_ms', 100);

            if ($delay > 0) {
                usleep($delay * 1000);
            }
        }

        return $pages;
    }

    /** @return array<string, mixed> */
    public function analyseHtml(string $html, string $url, int $depth = 0): array
    {
        $document = new DOMDocument;
        $previousErrors = libxml_use_internal_errors(true);
        $loaded = $document->loadHTML($html, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrors);

        if (! $loaded) {
            return [
                ...$this->emptyAnalysis($url, $depth),
                'checks' => [$this->check('html_parseable', 'HTML can be analysed', 'failed', 'The page did not return analysable HTML.')],
            ];
        }

        $xpath = new DOMXPath($document);
        $title = trim((string) $xpath->evaluate('string(//title[1])'));
        $description = trim((string) $xpath->evaluate('string(//meta[translate(@name, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz")="description"]/@content)'));
        $h1Count = (int) $xpath->evaluate('count(//h1)');
        $canonical = trim((string) $xpath->evaluate('string(//link[contains(concat(" ", normalize-space(translate(@rel, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz")), " "), " canonical ")]/@href)'));
        $robots = Str::lower(trim((string) $xpath->evaluate('string(//meta[translate(@name, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz")="robots"]/@content)')));
        $language = trim((string) $xpath->evaluate('string(/html/@lang)'));
        $viewport = trim((string) $xpath->evaluate('string(//meta[translate(@name, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz")="viewport"]/@content)'));
        $images = (int) $xpath->evaluate('count(//img)');
        $missingAlt = (int) $xpath->evaluate('count(//img[not(@alt) or normalize-space(@alt)=""])');
        $bodyText = preg_replace('/\s+/u', ' ', trim((string) $xpath->evaluate('string(//body)'))) ?: '';
        $wordCount = $bodyText === '' ? 0 : str_word_count(strip_tags($bodyText));
        $links = $this->links($xpath, $url);
        $invalidStructuredData = $this->invalidStructuredDataCount($xpath);
        $expectedHost = $this->normaliseHost((string) parse_url($url, PHP_URL_HOST));
        $canonicalHost = $this->normaliseHost((string) parse_url($canonical, PHP_URL_HOST));
        $isIndexable = ! Str::contains($robots, 'noindex');

        return [
            'url' => $url,
            'url_hash' => hash('sha256', $url),
            'depth' => $depth,
            'status_code' => null,
            'response_time_ms' => null,
            'title' => $title ?: null,
            'meta_description' => $description ?: null,
            'h1_count' => $h1Count,
            'canonical_url' => $canonical ?: null,
            'is_indexable' => $isIndexable,
            'word_count' => $wordCount,
            'internal_links_count' => count($links),
            'images_count' => $images,
            'missing_alt_count' => $missingAlt,
            'checks' => [
                $this->check('page_title', 'Page title', $title === '' ? 'failed' : (Str::length($title) > 65 ? 'warning' : 'passed'), $title === '' ? 'No page title was found.' : 'Title: '.$title),
                $this->check('meta_description', 'Meta description', $description === '' || Str::length($description) > 170 ? 'warning' : 'passed', $description === '' ? 'No meta description was found.' : 'Meta description is present.'),
                $this->check('h1', 'Primary heading', $h1Count === 1 ? 'passed' : 'warning', "The page has {$h1Count} H1 elements."),
                $this->check('canonical', 'Canonical URL', $canonical === '' || ($canonicalHost !== '' && $canonicalHost !== $expectedHost) ? 'warning' : 'passed', $canonical === '' ? 'No canonical URL was found.' : 'Canonical: '.$canonical),
                $this->check('indexable', 'Indexing directive', $isIndexable ? 'passed' : 'failed', $isIndexable ? 'No noindex directive was found.' : 'The page contains a noindex directive.'),
                $this->check('language', 'Page language', $language === '' ? 'warning' : 'passed', $language === '' ? 'The HTML element has no language.' : 'Language: '.$language),
                $this->check('viewport', 'Mobile viewport', $viewport === '' ? 'warning' : 'passed', $viewport === '' ? 'No mobile viewport was found.' : 'A mobile viewport is configured.'),
                $this->check('image_alt_text', 'Image alternative text', $missingAlt > 0 ? 'warning' : 'passed', $missingAlt > 0 ? "{$missingAlt} of {$images} images have no alternative text." : 'All images have alternative text.'),
                $this->check('structured_data', 'Structured data syntax', $invalidStructuredData > 0 ? 'warning' : 'passed', $invalidStructuredData > 0 ? "{$invalidStructuredData} structured data blocks contain invalid JSON." : 'No invalid structured data JSON was found.'),
                $this->check('content_depth', 'Content depth', $wordCount < 150 ? 'warning' : 'passed', "The page contains approximately {$wordCount} words."),
            ],
            'discovered_links' => $links,
        ];
    }

    protected function request(): PendingRequest
    {
        return Http::accept('text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8')
            ->withUserAgent(config('app.name').' Website Health Monitor')
            ->connectTimeout(config('forms.health_reports.connect_timeout'))
            ->timeout(config('forms.health_reports.timeout'))
            ->withOptions(['allow_redirects' => false]);
    }

    /** @return array<int, array{url: string, depth: int}> */
    protected function sitemapUrls(string $baseUrl, array $allowedHosts): array
    {
        try {
            $response = $this->request()->get($baseUrl.'/sitemap.xml');

            if (! $response->successful()) {
                return [];
            }

            preg_match_all('/<loc[^>]*>\s*([^<]+)\s*<\/loc>/i', $this->boundedBody($response->body()), $matches);

            return collect($matches[1] ?? [])->map(fn (string $url) => html_entity_decode(trim($url)))
                ->filter(fn (string $url) => $this->isCrawlable($url, $allowedHosts))
                ->map(fn (string $url) => ['url' => $url, 'depth' => 1])->values()->all();
        } catch (ConnectionException) {
            return [];
        }
    }

    /** @return array<int, string> */
    protected function links(DOMXPath $xpath, string $currentUrl): array
    {
        $nodes = $xpath->query('//a[@href]');
        $links = [];

        if ($nodes === false) {
            return [];
        }

        foreach ($nodes as $node) {
            $resolved = $this->resolveUrl($currentUrl, trim($node->getAttribute('href')));

            if ($resolved) {
                $links[] = $resolved;
            }
        }

        return array_values(array_unique($links));
    }

    protected function resolveUrl(string $baseUrl, string $href): ?string
    {
        if ($href === '' || Str::startsWith(Str::lower($href), ['#', 'mailto:', 'tel:', 'javascript:', 'data:'])) {
            return null;
        }

        if (Str::startsWith($href, '//')) {
            return 'https:'.$href;
        }

        if (parse_url($href, PHP_URL_SCHEME)) {
            return $href;
        }

        $parts = parse_url($baseUrl);
        $origin = ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '');

        if (Str::startsWith($href, '/')) {
            return $origin.$href;
        }

        $directory = rtrim(str_replace('\\', '/', dirname($parts['path'] ?? '/')), '/');
        $directory = in_array($directory, ['.', '/'], true) ? '' : $directory;

        return $origin.($directory ? '/'.$directory : '').'/'.$href;
    }

    protected function normaliseUrl(string $url): ?string
    {
        $parts = parse_url(html_entity_decode(trim($url)));

        if (! isset($parts['host'])) {
            return null;
        }

        $path = preg_replace('#/+#', '/', $parts['path'] ?? '/') ?: '/';
        $path = $path === '/' ? '/' : rtrim($path, '/');

        return 'https://'.Str::lower($parts['host']).$path;
    }

    protected function isCrawlable(string $url, array $allowedHosts): bool
    {
        $host = $this->normaliseHost((string) parse_url($url, PHP_URL_HOST));
        $path = Str::lower((string) parse_url($url, PHP_URL_PATH));

        if (! in_array($host, $allowedHosts, true)) {
            return false;
        }

        if (preg_match('/\.(?:avif|css|csv|docx?|gif|jpe?g|js|json|mp3|mp4|pdf|png|svg|webp|xlsx?|xml|zip)$/i', $path)) {
            return false;
        }

        return collect(config('forms.health_reports.blocked_paths', []))
            ->doesntContain(fn (string $blockedPath) => Str::startsWith($path, Str::lower($blockedPath)));
    }

    protected function normaliseHost(string $host): string
    {
        return Str::lower(Str::after($host, 'www.'));
    }

    protected function invalidStructuredDataCount(DOMXPath $xpath): int
    {
        $nodes = $xpath->query('//script[translate(@type, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz")="application/ld+json"]');
        $invalid = 0;

        if ($nodes !== false) {
            foreach ($nodes as $node) {
                json_decode($node->textContent, true);
                $invalid += json_last_error() === JSON_ERROR_NONE ? 0 : 1;
            }
        }

        return $invalid;
    }

    /** @return array<string, mixed> */
    protected function emptyAnalysis(string $url, int $depth): array
    {
        return [
            'url' => $url,
            'url_hash' => hash('sha256', $url),
            'depth' => $depth,
            'status_code' => null,
            'response_time_ms' => null,
            'title' => null,
            'meta_description' => null,
            'h1_count' => 0,
            'canonical_url' => null,
            'is_indexable' => true,
            'word_count' => 0,
            'internal_links_count' => 0,
            'images_count' => 0,
            'missing_alt_count' => 0,
            'checks' => [],
            'discovered_links' => [],
        ];
    }

    /** @return array<string, string> */
    protected function check(string $key, string $label, string $status, string $message): array
    {
        return compact('key', 'label', 'status', 'message');
    }

    protected function boundedBody(string $body): string
    {
        return substr($body, 0, (int) config('forms.health_reports.max_response_kb') * 1024);
    }

    protected function ensurePublicDomain(string $domain): void
    {
        if (app()->environment('testing')) {
            return;
        }

        $addresses = collect(dns_get_record($domain, DNS_A | DNS_AAAA))
            ->map(fn (array $record) => $record['ip'] ?? $record['ipv6'] ?? null)->filter();

        if ($addresses->isEmpty()) {
            throw new RuntimeException('The registered domain does not resolve to a public address.');
        }

        foreach ($addresses as $address) {
            if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                throw new RuntimeException('Health reports cannot request private or reserved network addresses.');
            }
        }
    }
}
