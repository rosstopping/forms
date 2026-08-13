<?php

namespace App\Services\SeoIntelligence;

use App\Models\SeoSnapshot;
use App\Models\Website;
use App\Services\DataForSEO\Data\RankedKeywordData;
use App\Services\DataForSEO\DomainOverviewService;
use App\Services\DataForSEO\RankedKeywordsService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SeoSnapshotService
{
    public function __construct(
        private DomainOverviewService $domainOverview,
        private RankedKeywordsService $rankedKeywords,
    ) {}

    public function create(Website $website, int $locationCode = 2826, string $languageCode = 'en'): SeoSnapshot
    {
        $domain = $website->primaryDomain()?->domain;

        if (! is_string($domain) || $domain === '') {
            throw new InvalidArgumentException('The website does not have a domain to analyse.');
        }

        $snapshot = $website->seoSnapshots()->create([
            'provider' => SeoSnapshot::PROVIDER_DATAFORSEO,
            'domain' => $domain,
            'location_code' => $locationCode,
            'language_code' => $languageCode,
            'status' => SeoSnapshot::STATUS_PENDING,
            'snapshot_date' => today(),
            'metadata' => ['data_source' => 'third_party_estimate'],
            'errors' => [],
        ]);

        return $this->process($snapshot);
    }

    public function process(SeoSnapshot $snapshot): SeoSnapshot
    {
        if (in_array($snapshot->status, [SeoSnapshot::STATUS_COMPLETED, SeoSnapshot::STATUS_COMPLETED_WITH_ERRORS], true)) {
            return $snapshot->loadMissing('keywords');
        }

        $snapshot->loadMissing('website');
        $requestedAt = $snapshot->started_at ?? now();
        $snapshot->update(['status' => SeoSnapshot::STATUS_PROCESSING, 'started_at' => $requestedAt]);

        $domain = $snapshot->domain;
        $locationCode = $snapshot->location_code;
        $languageCode = $snapshot->language_code;
        $overviewResponse = $this->domainOverview->forDomain($domain, $locationCode, $languageCode);
        $keywordsResponse = $this->rankedKeywords->forDomain($domain, $locationCode, $languageCode);

        return DB::transaction(function () use ($snapshot, $requestedAt, $overviewResponse, $keywordsResponse): SeoSnapshot {
            $website = $snapshot->website;
            $overview = $overviewResponse->overview;
            $snapshot->update([
                'status' => SeoSnapshot::STATUS_COMPLETED,
                'organic_keywords' => $overview->organicKeywords,
                'estimated_organic_traffic' => $overview->estimatedOrganicTraffic,
                'top_3_keywords' => $overview->top3Keywords,
                'top_10_keywords' => $overview->top10Keywords,
                'top_20_keywords' => $overview->top20Keywords,
                'top_100_keywords' => $overview->top100Keywords,
                'metadata' => [
                    'data_source' => 'third_party_estimate',
                    'datasets' => ['domain_overview', 'ranked_keywords'],
                ],
                'errors' => [],
                'completed_at' => now(),
            ]);

            $snapshot->keywords()->createMany(array_map(
                fn (RankedKeywordData $keyword): array => [
                    'website_id' => $website->id,
                    'fingerprint' => $keyword->fingerprint,
                    'keyword' => $keyword->keyword,
                    'position' => $keyword->position,
                    'previous_position' => $keyword->previousPosition,
                    'ranking_url' => $keyword->rankingUrl,
                    'search_volume' => $keyword->searchVolume,
                    'cpc' => $keyword->cpc,
                    'competition' => $keyword->competition,
                    'competition_level' => $keyword->competitionLevel,
                    'search_intent' => $keyword->searchIntent,
                    'estimated_traffic' => $keyword->estimatedTraffic,
                    'keyword_difficulty' => $keyword->keywordDifficulty,
                    'location_code' => $keyword->locationCode,
                    'language_code' => $keyword->languageCode,
                ],
                $keywordsResponse->keywords,
            ));

            $snapshot->apiUsages()->createMany([
                $this->usageData($website, $overviewResponse->endpoint, 'domain_overview', $overviewResponse->resultCount, $overviewResponse->cost, $overviewResponse->taskId, $requestedAt),
                $this->usageData($website, $keywordsResponse->endpoint, 'ranked_keywords', $keywordsResponse->resultCount, $keywordsResponse->cost, $keywordsResponse->taskId, $requestedAt),
            ]);

            return $snapshot->load('keywords');
        });
    }

    /** @return array<string, mixed> */
    protected function usageData(Website $website, string $endpoint, string $requestType, int $resultCount, float $cost, ?string $taskId, Carbon $requestedAt): array
    {
        return [
            'website_id' => $website->id,
            'provider' => SeoSnapshot::PROVIDER_DATAFORSEO,
            'endpoint' => $endpoint,
            'request_type' => $requestType,
            'result_count' => $resultCount,
            'cost' => $cost,
            'provider_task_id' => $taskId,
            'metadata' => [],
            'requested_at' => $requestedAt,
        ];
    }
}
