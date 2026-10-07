<?php

namespace App\Services;

use App\Models\SeoSnapshot;
use App\Models\WebsiteAudit;
use App\Services\DataForSEO\BacklinksService;
use App\Services\DataForSEO\DomainOverviewService;
use App\Services\DataForSEO\Exceptions\DataForSEOException;
use App\Services\DataForSEO\RankedKeywordsService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class MarketingAuditResearch
{
    public function __construct(
        private DomainOverviewService $domainOverview,
        private RankedKeywordsService $rankedKeywords,
        private BacklinksService $backlinks,
        private MarketingAuditPageCounter $pageCounter,
        private MarketingAuditCompetitors $competitors,
        private MarketingAuditAiVisibility $aiVisibility,
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
        $seo = $this->seoForDomain($domain);

        return [
            'health_score' => $totalChecks > 0 ? (int) round($passedChecks / $totalChecks * 100) : null,
            'seo' => $seo,
            'projection' => $seo !== null ? $this->projection($seo) : null,
            'full_report' => ['status' => 'deferred'],
        ];
    }

    /** @return array<string, mixed> */
    public function forFullReport(WebsiteAudit $audit): array
    {
        $insights = $audit->insights ?? [];
        $seo = $insights['seo'] ?? $this->seoForDomain($audit->domain);
        $seo = is_array($seo) ? $this->fullSeoForDomain($audit->domain, $seo) : null;
        if (! array_key_exists('pages_listed', $insights)) {
            $pages = $this->pageCounter->count(data_get($insights, 'audited_url', $audit->website_url));
            $insights = [...$insights,
                'pages_listed' => $pages['count'],
                'pages_partial' => $pages['partial'],
                'pages_matching_domain' => $pages['matching_domain'] ?? $pages['count'],
                'pages_mismatched_domain' => $pages['mismatched_domain'] ?? 0,
                'pages_mismatched_host' => $pages['mismatched_host'] ?? null,
            ];
        }

        if (! array_key_exists('competitors', $insights)) {
            $insights['competitors'] = $seo !== null ? $this->competitors->forDomain($audit->domain, $seo) : null;
        }
        if (! array_key_exists('ai_visibility', $insights)) {
            $questions = $this->aiVisibility->questions($audit->domain, $seo);
            $insights['ai_visibility'] = $questions !== [] ? [
                'status' => $this->aiVisibility->available() ? 'pending' : 'unavailable',
                'questions' => $questions,
            ] : null;
        }

        return [...$insights, 'seo' => $seo];
    }

    /**
     * @param  array<string, mixed>  $seo
     * @return array<string, mixed>
     */
    private function fullSeoForDomain(string $domain, array $seo): array
    {
        if (blank(config('services.dataforseo.login')) || blank(config('services.dataforseo.password'))) {
            return $seo;
        }

        $key = 'marketing-audit-full-seo:'.hash('sha256', strtolower($domain).'|'.$seo['location_code'].'|'.$seo['language_code'].'|'.($seo['retrieved_at'] ?? ''));

        return Cache::remember($key, now()->addDays(7), function () use ($domain, $seo): array {
            $keywords = $seo['keywords'];
            $cost = (float) ($seo['cost_usd'] ?? 0);
            if (($seo['keyword_sample_strategy'] ?? null) === 'striking_distance' && $seo['organic_keywords'] > 0) {
                try {
                    $response = $this->rankedKeywords->forRange($domain, $seo['location_code'], $seo['language_code'], 1, 10, 10);
                    $keywords = [...array_map(fn ($keyword): array => [
                        'term' => $keyword->keyword,
                        'position' => $keyword->position,
                        'monthly_searches' => $keyword->searchVolume,
                        'url' => $keyword->rankingUrl,
                    ], $response->keywords), ...$keywords];
                    $cost += $response->cost;
                    if ($keywords === []) {
                        $response = $this->rankedKeywords->forRange($domain, $seo['location_code'], $seo['language_code'], 1, 100, 20);
                        $keywords = array_map(fn ($keyword): array => [
                            'term' => $keyword->keyword,
                            'position' => $keyword->position,
                            'monthly_searches' => $keyword->searchVolume,
                            'url' => $keyword->rankingUrl,
                        ], $response->keywords);
                        $cost += $response->cost;
                    }
                } catch (DataForSEOException $exception) {
                    report($exception);
                }
            }
            if (($seo['backlinks_checked'] ?? true) === false) {
                try {
                    $backlinks = $this->backlinks->overview($domain);
                    $seo['referring_domains'] = $backlinks->overview->referringDomains;
                    $seo['backlinks'] = $backlinks->overview->backlinks;
                    $cost += $backlinks->cost;
                } catch (DataForSEOException $exception) {
                    report($exception);
                }
            }

            return [...$seo,
                'keywords' => $keywords,
                'sample_size' => count($keywords),
                'keyword_sample_strategy' => 'page_one_and_striking_distance',
                'backlinks_checked' => true,
                'cost_usd' => round($cost, 5),
            ];
        });
    }

    /** @return array<string, mixed>|null */
    private function seoForDomain(string $domain): ?array
    {
        $locationCode = (int) config('services.dataforseo.location_code', 2826);
        $languageCode = (string) config('services.dataforseo.language_code', 'en');
        $cacheKey = 'marketing-audit-seo:v2:'.hash('sha256', strtolower($domain)."|{$locationCode}|{$languageCode}");
        $cached = Cache::get($cacheKey);

        if (is_array($cached)) {
            return $cached;
        }

        $recentSnapshot = $this->savedSeo($domain, $locationCode, $languageCode, 7, true);
        if ($recentSnapshot !== null) {
            return $recentSnapshot;
        }

        if (blank(config('services.dataforseo.login')) || blank(config('services.dataforseo.password'))) {
            return $this->savedSeo($domain, $locationCode, $languageCode, 30);
        }

        try {
            $overview = $this->domainOverview->forDomain($domain, $locationCode, $languageCode);
        } catch (DataForSEOException $exception) {
            report($exception);

            return $this->savedSeo($domain, $locationCode, $languageCode, 30);
        }

        $keywords = [];
        $keywordCost = 0.0;
        if ($overview->overview->organicKeywords > 0) {
            try {
                $response = $this->rankedKeywords->forRange($domain, $locationCode, $languageCode, 11, 30, 10);
                $keywords = $response->keywords;
                $keywordCost += $response->cost;
            } catch (DataForSEOException $exception) {
                report($exception);
            }
        }

        $seo = [
            'provider' => 'DataForSEO',
            'location_code' => $locationCode,
            'language_code' => $languageCode,
            'retrieved_at' => now()->toIso8601String(),
            'cost_usd' => round($overview->cost + $keywordCost, 5),
            'keyword_sample_strategy' => 'striking_distance',
            'organic_keywords' => $overview->overview->organicKeywords,
            'estimated_monthly_visits' => (int) round($overview->overview->estimatedOrganicTraffic),
            'top_3_keywords' => $overview->overview->top3Keywords,
            'top_10_keywords' => $overview->overview->top10Keywords,
            'top_20_keywords' => $overview->overview->top20Keywords,
            'referring_domains' => null,
            'backlinks' => null,
            'backlinks_checked' => false,
            'sample_size' => count($keywords),
            'keywords' => array_map(fn ($keyword): array => [
                'term' => $keyword->keyword,
                'position' => $keyword->position,
                'monthly_searches' => $keyword->searchVolume,
                'url' => $keyword->rankingUrl,
            ], $keywords),
        ];

        Cache::put($cacheKey, $seo, now()->addDays(7));

        return $seo;
    }

    /** @return array<string, mixed>|null */
    private function savedSeo(string $domain, int $locationCode, string $languageCode, int $maximumAgeDays, bool $requireFocusedSample = false): ?array
    {
        return $this->savedSnapshot($domain, $locationCode, $languageCode, $maximumAgeDays)
            ?? $this->previousAuditSeo($domain, $locationCode, $languageCode, $maximumAgeDays, $requireFocusedSample);
    }

    /** @return array<string, mixed>|null */
    private function previousAuditSeo(string $domain, int $locationCode, string $languageCode, int $maximumAgeDays, bool $requireFocusedSample): ?array
    {
        $previousAudits = WebsiteAudit::query()
            ->where('domain', strtolower($domain))
            ->where('status', WebsiteAudit::STATUS_COMPLETED)
            ->where('created_at', '>=', now()->subDays($maximumAgeDays))
            ->latest('id')
            ->limit(10)
            ->get(['insights']);

        foreach ($previousAudits as $audit) {
            $seo = $audit->insights['seo'] ?? null;
            if (! is_array($seo)
                || (int) ($seo['location_code'] ?? 0) !== $locationCode
                || ($seo['language_code'] ?? null) !== $languageCode
                || ($requireFocusedSample && ! in_array($seo['keyword_sample_strategy'] ?? null, ['striking_distance', 'page_one_and_striking_distance'], true))
                || ! isset($seo['retrieved_at'], $seo['organic_keywords'], $seo['estimated_monthly_visits'], $seo['keywords'])) {
                continue;
            }

            try {
                if (Carbon::parse($seo['retrieved_at'])->lt(now()->subDays($maximumAgeDays))) {
                    continue;
                }
            } catch (\Throwable) {
                continue;
            }

            return $seo;
        }

        return null;
    }

    /** @return array<string, mixed>|null */
    private function savedSnapshot(string $domain, int $locationCode, string $languageCode, int $maximumAgeDays): ?array
    {
        $snapshot = SeoSnapshot::query()
            ->where('domain', strtolower($domain))
            ->where('provider', SeoSnapshot::PROVIDER_DATAFORSEO)
            ->where('status', SeoSnapshot::STATUS_COMPLETED)
            ->where('location_code', $locationCode)
            ->where('language_code', $languageCode)
            ->whereDate('snapshot_date', '>=', today()->subDays($maximumAgeDays))
            ->orderByDesc('snapshot_date')
            ->orderByDesc('id')
            ->first();

        if ($snapshot === null) {
            return null;
        }

        $keywordQuery = $snapshot->keywords()
            ->where('location_code', $locationCode)
            ->where('language_code', $languageCode);
        $pageOne = (clone $keywordQuery)->whereBetween('position', [1, 10])->orderByDesc('search_volume')->limit(10)->get();
        $strikingDistance = (clone $keywordQuery)->whereBetween('position', [11, 30])->orderByDesc('search_volume')->limit(10)->get();
        $keywords = $pageOne->concat($strikingDistance);

        if ($keywords->isEmpty()) {
            $keywords = $keywordQuery->whereBetween('position', [1, 100])->orderBy('position')->limit(20)->get();
        }

        return [
            'location_code' => $locationCode,
            'language_code' => $languageCode,
            'retrieved_at' => $snapshot->snapshot_date->toIso8601String(),
            'keyword_sample_strategy' => 'page_one_and_striking_distance',
            'organic_keywords' => (int) $snapshot->organic_keywords,
            'estimated_monthly_visits' => (int) round((float) $snapshot->estimated_organic_traffic),
            'top_3_keywords' => (int) $snapshot->top_3_keywords,
            'top_10_keywords' => (int) $snapshot->top_10_keywords,
            'top_20_keywords' => (int) $snapshot->top_20_keywords,
            'referring_domains' => $snapshot->referring_domains,
            'backlinks' => $snapshot->backlinks,
            'sample_size' => $keywords->count(),
            'keywords' => $keywords->map(fn ($keyword): array => [
                'term' => $keyword->keyword,
                'position' => $keyword->position,
                'monthly_searches' => $keyword->search_volume,
                'url' => $keyword->ranking_url,
            ])->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $seo
     * @return array{page_one: array<int, array<string, mixed>>, striking_distance: array<int, array<string, mixed>>, other: array<int, array<string, mixed>>}
     */
    public function rankingHighlights(array $seo): array
    {
        $keywords = collect($seo['keywords'] ?? [])
            ->filter(fn (mixed $keyword): bool => is_array($keyword) && is_numeric($keyword['position'] ?? null) && (int) $keyword['position'] > 0)
            ->sort(fn (array $first, array $second): int => ((int) $first['position'] <=> (int) $second['position'])
                ?: ((int) ($second['monthly_searches'] ?? 0) <=> (int) ($first['monthly_searches'] ?? 0)));

        $pageOne = $keywords->filter(fn (array $keyword): bool => (int) $keyword['position'] <= 10)->take(6)->values()->all();
        $strikingDistance = $keywords->filter(fn (array $keyword): bool => (int) $keyword['position'] >= 11 && (int) $keyword['position'] <= 30)->take(6)->values()->all();

        return [
            'page_one' => $pageOne,
            'striking_distance' => $strikingDistance,
            'other' => $pageOne === [] && $strikingDistance === [] ? $keywords->take(6)->values()->all() : [],
        ];
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
