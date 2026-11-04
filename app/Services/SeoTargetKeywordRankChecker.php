<?php

namespace App\Services;

use App\Data\SerpResult;
use App\Models\ExternalApiUsage;
use App\Models\SeoTargetKeyword;
use App\Models\SeoTargetKeywordRanking;
use App\Models\WebsiteDomain;
use Throwable;

class SeoTargetKeywordRankChecker
{
    public function __construct(private CachedSerpProvider $serp) {}

    public function check(SeoTargetKeyword $keyword): SeoTargetKeywordRanking
    {
        $keyword->loadMissing('website.domains');
        $locationCode = (int) config('services.dataforseo.location_code');
        $languageCode = (string) config('services.dataforseo.language_code');
        $observedAt = now();

        try {
            $response = $this->serp->searchForMarket($keyword->term, $locationCode, $languageCode, 100);
            $domain = WebsiteDomain::canonicalDomain(strtolower((string) $keyword->website->primaryDomain()?->domain));
            $results = $response->results
                ->filter(fn (SerpResult $result): bool => $result->position >= 1 && $result->position <= 100)
                ->sortBy('position')
                ->take(100)
                ->map(fn (SerpResult $result): array => [
                    'domain' => WebsiteDomain::canonicalDomain(strtolower($result->domain)),
                    'position' => $result->position,
                    'url' => $result->url,
                ])
                ->unique('domain')->values();
            $match = $results->firstWhere('domain', $domain);
            $incomplete = $response->results->contains(fn (SerpResult $result): bool => GoogleSerpRedirectResolver::isRedirect($result->url) && ($match === null || $result->position <= $match['position']));
            $ranking = $keyword->rankings()->create([
                'website_id' => $keyword->website_id, 'provider' => $response->provider,
                'location_code' => $locationCode, 'language_code' => $languageCode, 'device' => 'desktop',
                'status' => $incomplete ? SeoTargetKeywordRanking::STATUS_FAILED : ($match ? SeoTargetKeywordRanking::STATUS_RANKED : SeoTargetKeywordRanking::STATUS_NOT_FOUND),
                'error' => $incomplete ? 'Google redirect destinations could not all be resolved; ranking is inconclusive.' : null,
                'position' => $incomplete ? null : ($match['position'] ?? null), 'ranking_url' => $incomplete ? null : ($match['url'] ?? null), 'cached' => $response->cached,
                'organic_results' => $results->all(),
                'provider_task_id' => $response->taskId, 'observed_at' => $response->fetchedAt ?? $observedAt,
            ]);

            if (! $response->cached) {
                ExternalApiUsage::create([
                    'website_id' => $keyword->website_id, 'seo_target_keyword_id' => $keyword->id,
                    'provider' => $response->provider, 'endpoint' => $response->endpoint,
                    'request_type' => 'target_keyword_rank', 'result_count' => $response->results->count(),
                    'cost' => $response->cost, 'provider_task_id' => $response->taskId,
                    'metadata' => ['term' => $keyword->term, 'location_code' => $locationCode, 'language_code' => $languageCode, 'device' => 'desktop', 'depth' => 100],
                    'requested_at' => $observedAt,
                ]);
            }

            return $ranking;
        } catch (Throwable $exception) {
            report($exception);

            return $keyword->rankings()->create([
                'website_id' => $keyword->website_id, 'provider' => 'dataforseo', 'location_code' => $locationCode,
                'language_code' => $languageCode, 'device' => 'desktop', 'status' => SeoTargetKeywordRanking::STATUS_FAILED,
                'error' => 'The ranking provider could not complete this check.', 'observed_at' => $observedAt,
            ]);
        }
    }
}
