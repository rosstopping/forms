<?php

namespace App\Services;

use App\Services\DataForSEO\BacklinksService;
use App\Services\DataForSEO\DomainOverviewService;
use App\Services\DataForSEO\Exceptions\DataForSEOException;
use App\Services\DataForSEO\RankedKeywordsService;
use Illuminate\Support\Facades\Cache;

class MarketingAuditResearch
{
    public function __construct(
        private DomainOverviewService $domainOverview,
        private RankedKeywordsService $rankedKeywords,
        private BacklinksService $backlinks,
        private MarketingAuditPageCounter $pageCounter,
    ) {}

    /**
     * @param  array<string, mixed>  $analysis
     * @return array<string, mixed>
     */
    public function forAudit(string $domain, string $websiteUrl, array $analysis): array
    {
        $findings = collect($analysis['findings'] ?? []);
        $totalChecks = $findings->count();
        $passedChecks = $findings->where('severity', 'passed')->count();
        $pages = $this->pageCounter->count($websiteUrl);
        $seo = $this->seoForDomain($domain);

        return [
            'health_score' => $totalChecks > 0 ? (int) round($passedChecks / $totalChecks * 100) : null,
            'pages_listed' => $pages['count'],
            'pages_partial' => $pages['partial'],
            'seo' => $seo,
            'projection' => $seo !== null ? $this->projection($seo) : null,
        ];
    }

    /** @return array<string, mixed>|null */
    private function seoForDomain(string $domain): ?array
    {
        if (blank(config('services.dataforseo.login')) || blank(config('services.dataforseo.password'))) {
            return null;
        }

        $locationCode = (int) config('services.dataforseo.location_code', 2826);
        $languageCode = (string) config('services.dataforseo.language_code', 'en');
        $cacheKey = 'marketing-audit-seo:'.hash('sha256', strtolower($domain)."|{$locationCode}|{$languageCode}");
        $cached = Cache::get($cacheKey);

        if (is_array($cached)) {
            return $cached;
        }

        try {
            $overview = $this->domainOverview->forDomain($domain, $locationCode, $languageCode);
        } catch (DataForSEOException $exception) {
            report($exception);

            return null;
        }

        $keywords = null;
        if ($overview->overview->organicKeywords > 0) {
            try {
                $keywords = $this->rankedKeywords->forRange($domain, $locationCode, $languageCode, 1, 100, 20);
            } catch (DataForSEOException $exception) {
                report($exception);
            }
        }

        $backlinks = null;
        try {
            $backlinks = $this->backlinks->overview($domain);
        } catch (DataForSEOException $exception) {
            report($exception);
        }

        $seo = [
            'provider' => 'DataForSEO',
            'location_code' => $locationCode,
            'language_code' => $languageCode,
            'retrieved_at' => now()->toIso8601String(),
            'cost_usd' => round($overview->cost + ($keywords?->cost ?? 0) + ($backlinks?->cost ?? 0), 5),
            'organic_keywords' => $overview->overview->organicKeywords,
            'estimated_monthly_visits' => (int) round($overview->overview->estimatedOrganicTraffic),
            'top_3_keywords' => $overview->overview->top3Keywords,
            'top_10_keywords' => $overview->overview->top10Keywords,
            'top_20_keywords' => $overview->overview->top20Keywords,
            'referring_domains' => $backlinks?->overview->referringDomains,
            'backlinks' => $backlinks?->overview->backlinks,
            'sample_size' => count($keywords?->keywords ?? []),
            'keywords' => array_map(fn ($keyword): array => [
                'term' => $keyword->keyword,
                'position' => $keyword->position,
                'monthly_searches' => $keyword->searchVolume,
                'url' => $keyword->rankingUrl,
            ], $keywords?->keywords ?? []),
        ];

        Cache::put($cacheKey, $seo, now()->addDays(7));

        return $seo;
    }

    /**
     * @param  array<string, mixed>  $seo
     * @return array<string, int|string>|null
     */
    public function projection(array $seo): ?array
    {
        $midPageSearches = collect($seo['keywords'])
            ->filter(fn (array $keyword): bool => $keyword['position'] >= 11 && $keyword['position'] <= 30 && ($keyword['monthly_searches'] ?? 0) > 0)
            ->sum(fn (array $keyword): int => $keyword['monthly_searches']);

        if ($midPageSearches < 40) {
            return null;
        }

        $baseline = max(0, (int) $seo['estimated_monthly_visits']);
        $lower = $baseline + max(1, (int) round($midPageSearches * 0.0075));
        $upper = $baseline + max(2, (int) round($midPageSearches * 0.04));

        return [
            'baseline_monthly_visits' => $baseline,
            'six_month_low' => $lower,
            'six_month_high' => max($lower + 1, $upper),
            'sampled_mid_page_searches' => $midPageSearches,
            'method' => 'Based on 25–50% of sampled terms in positions 11–30 reaching page one and attracting 3–8% of those searches. Excludes gains from new content.',
        ];
    }
}
