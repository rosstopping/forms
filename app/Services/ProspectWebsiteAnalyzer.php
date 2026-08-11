<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class ProspectWebsiteAnalyzer
{
    public function __construct(protected WebsiteHealthAuditor $auditor) {}

    /** @return array{score: int, findings: array<int, array<string, mixed>>} */
    public function analyze(string $url): array
    {
        $host = parse_url($url, PHP_URL_HOST);

        if (! is_string($host) || ! $this->isPublicHost($host)) {
            throw new RuntimeException('The prospect website must resolve to a public address.');
        }

        $startedAt = microtime(true);
        $response = Http::accept('text/html')->withUserAgent(config('app.name').' Prospect Research')
            ->connectTimeout(5)->timeout(12)->withOptions(['allow_redirects' => false])->get($url);
        $responseTime = (int) round((microtime(true) - $startedAt) * 1000);

        if (! $response->successful()) {
            throw new RuntimeException('The prospect website returned HTTP '.$response->status().'.');
        }

        $checks = [
            $this->check('Availability & speed', 'website_reachable', 'Website reachable', 'passed', 'The homepage returned HTTP '.$response->status().'.'),
            $this->check('Availability & speed', 'response_time', 'Homepage response time', $responseTime > 2000 ? 'warning' : 'passed', "The homepage responded in {$responseTime} ms."),
            $this->check('Security', 'https', 'HTTPS enabled', parse_url($url, PHP_URL_SCHEME) === 'https' ? 'passed' : 'warning', parse_url($url, PHP_URL_SCHEME) === 'https' ? 'The homepage is available over HTTPS.' : 'The homepage is not being checked over HTTPS.'),
            ...$this->securityChecks($response->headers()),
            ...$this->htmlChecks($response->body(), $url),
            $this->endpointCheck($this->baseUrl($url).'/robots.txt', 'robots_txt', 'robots.txt available'),
            $this->endpointCheck($this->baseUrl($url).'/sitemap.xml', 'sitemap_xml', 'XML sitemap available'),
        ];
        $findings = collect($checks)->values()->all();
        $score = min(100, collect($findings)->sum(fn (array $finding): int => $finding['severity'] === 'failed' ? 25 : ($finding['severity'] === 'warning' ? 10 : 0)));

        return ['score' => $score, 'findings' => $findings];
    }

    protected function isPublicHost(string $host): bool
    {
        if (app()->environment('testing')) {
            return true;
        }

        $addresses = gethostbynamel($host);

        return $addresses !== false && $addresses !== [] && collect($addresses)->every(fn (string $address): bool => filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false);
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
            ->map(fn (array $check): array => $this->check($this->categoryFor($check['key']), $check['key'], $check['label'], $check['status'], $check['message']))
            ->all();
    }

    /** @return array<string, mixed> */
    protected function endpointCheck(string $url, string $key, string $label): array
    {
        try {
            $response = $this->request()->get($url);

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
            'language', 'viewport', 'image_alt_text' => 'Accessibility',
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
