<?php

namespace App\Services;

use App\Models\SeoProspectCandidate;
use Illuminate\Support\Str;

class SeoProspectCandidateAnalyzer
{
    public function __construct(
        private WebsiteCrawler $crawler,
        private ProspectWebsiteAnalyzer $prospectAnalyzer,
        private WebsiteMigrationAssessor $migrationAssessor,
    ) {}

    /** @return array<string, mixed> */
    public function analyze(SeoProspectCandidate $candidate): array
    {
        $candidate->loadMissing('search');
        $maximumPages = $candidate->search->maximum_pages;
        $crawlLimit = min(101, max(41, $maximumPages + 1));
        $pages = $this->crawler->crawlUrl($candidate->website_url, $crawlLimit);
        $pageCount = $this->pageCount($pages, $candidate->domain);
        $tooLarge = $pageCount > $maximumPages;
        $assessment = $this->migrationAssessor->assess($pageCount, $maximumPages, $pages);
        $observations = [
            'crawl_pages_checked' => count($pages),
            'crawl_limit' => $crawlLimit,
            'crawl_limit_reached' => count($pages) >= $crawlLimit,
            'indexable_page_count' => $pageCount,
            'page_count_band' => $this->pageCountBand($pageCount),
        ];

        if ($tooLarge) {
            return [
                'page_count' => $pageCount,
                'audit_score' => null,
                'audit_findings' => [],
                'contact_details' => [],
                ...$assessment,
                'observations' => $observations,
                'qualification_status' => 'too_large',
            ];
        }

        $audit = $this->prospectAnalyzer->analyze($candidate->website_url);
        $crawlFindings = collect($pages)
            ->where('depth', '>', 0)
            ->flatMap(fn (array $page): array => collect($page['checks'] ?? [])
                ->whereIn('status', ['warning', 'failed'])
                ->map(fn (array $check): array => [
                    'category' => 'Site-wide crawl',
                    'key' => $check['key'],
                    'title' => $check['label'],
                    'severity' => $check['status'],
                    'message' => $check['message'],
                    'source_url' => $page['url'],
                ])->all())
            ->take(50)
            ->values()
            ->all();

        return [
            'page_count' => $pageCount,
            'audit_score' => $audit['score'],
            'audit_findings' => [...$audit['findings'], ...$crawlFindings],
            'contact_details' => $audit['contacts'],
            ...$assessment,
            'observations' => $observations,
            'qualification_status' => $pageCount > 0 ? 'suitable' : 'unsuitable',
        ];
    }

    /** @param array<int, array<string, mixed>> $pages */
    private function pageCount(array $pages, string $candidateDomain): int
    {
        return collect($pages)
            ->filter(fn (array $page): bool => ($page['status_code'] ?? 0) >= 200 && ($page['status_code'] ?? 0) < 300 && ($page['is_indexable'] ?? false))
            ->map(fn (array $page): ?string => $this->canonicalKey($page, $candidateDomain))
            ->filter()
            ->unique()
            ->count();
    }

    /** @param array<string, mixed> $page */
    private function canonicalKey(array $page, string $candidateDomain): ?string
    {
        $url = (string) ($page['url'] ?? '');
        $canonical = (string) ($page['canonical_url'] ?? '');

        if ($canonical !== '') {
            $canonical = $this->resolveCanonical($url, $canonical);
        }

        $target = $canonical ?: $url;
        $host = Str::lower(Str::after((string) parse_url($target, PHP_URL_HOST), 'www.'));

        if ($host === '' || $host !== Str::lower(Str::after($candidateDomain, 'www.'))) {
            return null;
        }

        $path = preg_replace('#/+#', '/', (string) parse_url($target, PHP_URL_PATH)) ?: '/';

        return $host.($path === '/' ? '/' : rtrim($path, '/'));
    }

    private function resolveCanonical(string $pageUrl, string $canonical): string
    {
        $scheme = (string) parse_url($pageUrl, PHP_URL_SCHEME);
        $host = (string) parse_url($pageUrl, PHP_URL_HOST);
        $origin = $scheme.'://'.$host;

        if (Str::startsWith($canonical, '//')) {
            return $scheme.':'.$canonical;
        }

        if (Str::startsWith($canonical, '/')) {
            return $origin.$canonical;
        }

        if (parse_url($canonical, PHP_URL_SCHEME)) {
            return $canonical;
        }

        $path = (string) parse_url($pageUrl, PHP_URL_PATH);
        $directory = Str::finish(Str::beforeLast($path ?: '/', '/'), '/');

        return $origin.$directory.$canonical;
    }

    private function pageCountBand(int $pageCount): string
    {
        return match (true) {
            $pageCount <= 10 => 'excellent',
            $pageCount <= 20 => 'suitable',
            $pageCount <= 40 => 'borderline',
            default => 'unsuitable',
        };
    }
}
