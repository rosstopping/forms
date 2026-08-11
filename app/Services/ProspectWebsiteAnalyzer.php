<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
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

        $checks = $this->auditor->inspectHtml(substr($response->body(), 0, 2_000_000), $url);
        $findings = collect($checks)
            ->reject(fn (array $check): bool => $check['status'] === 'passed')
            ->map(fn (array $check): array => ['key' => $check['key'], 'title' => $check['label'], 'severity' => $check['status'], 'message' => $check['message']])
            ->take(6)->values()->all();

        if ($responseTime > 2000) {
            array_unshift($findings, ['key' => 'response_time', 'title' => 'Homepage response time', 'severity' => 'warning', 'message' => "The homepage took {$responseTime} ms to respond."]);
        }

        $score = min(100, collect($findings)->sum(fn (array $finding): int => $finding['severity'] === 'failed' ? 25 : 12));

        return ['score' => $score, 'findings' => array_slice($findings, 0, 6)];
    }

    protected function isPublicHost(string $host): bool
    {
        $addresses = gethostbynamel($host);

        return $addresses !== false && $addresses !== [] && collect($addresses)->every(fn (string $address): bool => filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false);
    }
}
