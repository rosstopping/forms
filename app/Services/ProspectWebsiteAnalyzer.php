<?php

namespace App\Services;

use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class ProspectWebsiteAnalyzer
{
    public function __construct(protected WebsiteHealthAuditor $auditor, protected ProspectContactFinder $contactFinder) {}

    /** @return array{final_url: string, score: int, findings: array<int, array<string, mixed>>, contacts: array<string, mixed>} */
    public function analyze(string $url): array
    {
        $startedAt = microtime(true);
        [$response, $url] = $this->fetchPage($url);
        $responseTime = (int) round((microtime(true) - $startedAt) * 1000);

        if (! $response->successful()) {
            throw new RuntimeException('The prospect website returned HTTP '.$response->status().'.');
        }

        $checks = [
            $this->check('Availability & speed', 'website_reachable', 'Website reachable', 'passed', 'The page returned HTTP '.$response->status().'.'),
            $this->check('Availability & speed', 'response_time', 'Page response time', $responseTime > 2000 ? 'warning' : 'passed', "The page responded in {$responseTime} ms."),
            $this->check('Security', 'https', 'HTTPS enabled', parse_url($url, PHP_URL_SCHEME) === 'https' ? 'passed' : 'warning', parse_url($url, PHP_URL_SCHEME) === 'https' ? 'The page is available over HTTPS.' : 'The page is not being checked over HTTPS.'),
            ...$this->securityChecks($response->headers()),
            ...$this->htmlChecks($response->body(), $url),
            $this->endpointCheck($this->baseUrl($url).'/robots.txt', 'robots_txt', 'robots.txt available'),
            $this->endpointCheck($this->baseUrl($url).'/sitemap.xml', 'sitemap_xml', 'XML sitemap available'),
        ];
        $findings = collect($checks)->values()->all();
        $score = min(100, collect($findings)->sum(fn (array $finding): int => $finding['severity'] === 'failed' ? 25 : ($finding['severity'] === 'warning' ? 10 : 0)));

        return ['final_url' => $url, 'score' => $score, 'findings' => $findings, 'contacts' => $this->contactFinder->find($url, $response->body())];
    }

    /** @return array{Response, string} */
    protected function fetchPage(string $url): array
    {
        $current = (new Uri($url))->withFragment('');
        $visited = [];

        for ($redirects = 0; ; $redirects++) {
            $url = (string) $current;
            $host = $current->getHost();
            if (! in_array($current->getScheme(), ['http', 'https'], true)
                || $current->getUserInfo() !== ''
                || ! in_array($current->getPort(), [null, 80, 443], true)
                || $host === '') {
                throw new RuntimeException('The website address must be a public HTTP or HTTPS URL without credentials.');
            }
            if (isset($visited[$url])) {
                throw new RuntimeException('The website has a redirect loop.');
            }
            $visited[$url] = true;
            $addresses = $this->addresses($host);
            if ($addresses === [] || collect($addresses)->contains(fn (string $address): bool => ! filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE))) {
                throw new RuntimeException('The prospect website must resolve to a public address.');
            }
            $address = str_contains($addresses[0], ':') ? '['.$addresses[0].']' : $addresses[0];
            $port = $current->getPort() ?? ($current->getScheme() === 'https' ? 443 : 80);
            $response = $this->request()->withOptions([
                'proxy' => '',
                'curl' => [CURLOPT_RESOLVE => [$host.':'.$port.':'.$address]],
            ])->get($url);

            if (! in_array($response->status(), [301, 302, 303, 307, 308], true)) {
                return [$response, $url];
            }
            if ($redirects >= 5 || ! $response->header('Location')) {
                throw new RuntimeException('The website redirect could not be followed.');
            }
            $current = UriResolver::resolve($current, new Uri($response->header('Location')))->withFragment('');
        }
    }

    /** @return array<int, string> */
    protected function addresses(string $host): array
    {
        $literal = trim($host, '[]');
        if (filter_var($literal, FILTER_VALIDATE_IP)) {
            return [$literal];
        }
        if (app()->environment('testing')) {
            return ['93.184.216.34'];
        }
        $records = dns_get_record($host, DNS_A | DNS_AAAA);

        return array_values(array_filter(array_map(fn (array $record): ?string => $record['ip'] ?? $record['ipv6'] ?? null, $records ?: [])));
    }

    /** @param array<string, array<int, string>> $headers
     * @return array<int, array<string, mixed>>
     */
    protected function securityChecks(array $headers): array
    {
        $checks = [];

        foreach ([
            'strict-transport-security' => ['HSTS', 'Protects future visits by requiring HTTPS.'],
            'content-security-policy' => ['Content Security Policy', 'Restricts which content the browser may execute.'],
            'x-content-type-options' => ['Content type protection', 'Prevents content-type sniffing.'],
            'referrer-policy' => ['Referrer Policy', 'Controls referrer information shared with other websites.'],
        ] as $header => [$label, $missingMessage]) {
            $present = isset($headers[$header]);
            $checks[] = $this->check('Security', $header, $label, $present ? 'passed' : 'warning', $present ? "The {$label} header is present." : $missingMessage);
        }

        $frameProtection = isset($headers['x-frame-options']) || Str::contains(Str::lower(implode(' ', $headers['content-security-policy'] ?? [])), 'frame-ancestors');
        $checks[] = $this->check('Security', 'frame_protection', 'Frame protection', $frameProtection ? 'passed' : 'warning', $frameProtection ? 'Frame embedding is explicitly controlled.' : 'No frame protection was found.');

        return $checks;
    }

    /** @return array<int, array<string, mixed>> */
    protected function htmlChecks(string $html, string $url): array
    {
        return collect($this->auditor->inspectHtml(substr($html, 0, 2_000_000), $url))
            ->map(fn (array $check): array => $this->check($this->categoryFor($check['key']), $check['key'], $check['label'], $check['status'], str_replace(['The homepage', 'Homepage'], ['The page', 'Page'], $check['message'])))
            ->all();
    }

    /** @return array<string, mixed> */
    protected function endpointCheck(string $url, string $key, string $label): array
    {
        try {
            $response = $key === 'sitemap_xml'
                ? app(SitemapFetcher::class)->fetch($this->request(), $url)
                : $this->request()->get($url);

            return $this->check('Discoverability', $key, $label, $response->successful() ? 'passed' : 'warning', $response->successful() ? "{$label}." : "{$url} returned HTTP {$response->status()}.");
        } catch (\Throwable) {
            return $this->check('Discoverability', $key, $label, 'warning', "{$label} could not be reached.");
        }
    }

    protected function baseUrl(string $url): string
    {
        $port = parse_url($url, PHP_URL_PORT);

        return parse_url($url, PHP_URL_SCHEME).'://'.parse_url($url, PHP_URL_HOST).($port ? ':'.$port : '');
    }

    protected function categoryFor(string $key): string
    {
        return match ($key) {
            'language', 'viewport', 'image_alt_text', 'skip_link' => 'Accessibility',
            'structured_data' => 'Structured data',
            default => 'Search essentials',
        };
    }

    /** @return array<string, mixed> */
    protected function check(string $category, string $key, string $title, string $severity, string $message): array
    {
        return compact('category', 'key', 'title', 'severity', 'message');
    }

    protected function request(): PendingRequest
    {
        return Http::accept('text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8')
            ->withUserAgent(config('app.name').' Prospect Research')
            ->connectTimeout(5)
            ->timeout(12)
            ->withOptions(['allow_redirects' => false]);
    }
}
