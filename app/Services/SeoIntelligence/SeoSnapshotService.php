<?php

namespace App\Services\SeoIntelligence;

use App\Models\SeoSnapshot;
use App\Models\Website;
use App\Services\DataForSEO\BacklinksService;
use App\Services\DataForSEO\CompetitorsService;
use App\Services\DataForSEO\Data\OrganicCompetitorData;
use App\Services\DataForSEO\Data\RankedKeywordData;
use App\Services\DataForSEO\Data\ReferringDomainData;
use App\Services\DataForSEO\DomainOverviewService;
use App\Services\DataForSEO\Exceptions\DataForSEOException;
use App\Services\DataForSEO\RankedKeywordsService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SeoSnapshotService
{
    public function __construct(
        private DomainOverviewService $domainOverview,
        private RankedKeywordsService $rankedKeywords,
        private BacklinksService $backlinks,
        private CompetitorsService $competitors,
        private SeoOpportunityService $opportunities,
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
        $datasets = data_get($snapshot->metadata, 'datasets', []);
        $datasets = is_array($datasets) ? $datasets : [];
        $errors = [];

        if (! in_array('domain_overview', $datasets, true) || ! in_array('ranked_keywords', $datasets, true)) {
            $overviewResponse = $this->domainOverview->forDomain($domain, $locationCode, $languageCode);
            $keywordsResponse = $this->rankedKeywords->forDomain($domain, $locationCode, $languageCode);

            DB::transaction(function () use ($snapshot, $requestedAt, $overviewResponse, $keywordsResponse): void {
                $website = $snapshot->website;
                $overview = $overviewResponse->overview;
                $snapshot->update([
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
            });

            $datasets = ['domain_overview', 'ranked_keywords'];
        }

        if (! in_array('backlink_overview', $datasets, true)) {
            try {
                $backlinkResponse = $this->backlinks->overview($domain);

                $datasets[] = 'backlink_overview';
                DB::transaction(function () use ($snapshot, $requestedAt, $backlinkResponse, $datasets): void {
                    $overview = $backlinkResponse->overview;
                    $snapshot->update([
                        'backlinks' => $overview->backlinks,
                        'referring_domains' => $overview->referringDomains,
                        'referring_ips' => $overview->referringIps,
                        'referring_subnets' => $overview->referringSubnets,
                        'broken_backlinks' => $overview->brokenBacklinks,
                        'domain_rank' => $overview->domainRank,
                        'metadata' => ['data_source' => 'third_party_estimate', 'datasets' => $datasets],
                    ]);
                    $snapshot->apiUsages()->create(
                        $this->usageData($snapshot->website, $backlinkResponse->endpoint, 'backlink_overview', $backlinkResponse->resultCount, $backlinkResponse->cost, $backlinkResponse->taskId, $requestedAt),
                    );
                });
            } catch (DataForSEOException) {
                $errors['backlink_overview'] = 'Backlink overview data was unavailable from the provider.';
            }
        }

        if (! in_array('referring_domains', $datasets, true)) {
            try {
                $referringDomainsResponse = $this->backlinks->referringDomains($domain);

                $datasets[] = 'referring_domains';
                DB::transaction(function () use ($snapshot, $requestedAt, $referringDomainsResponse, $datasets): void {
                    $snapshot->referringDomains()->createMany(array_map(
                        fn (ReferringDomainData $domain): array => [
                            'website_id' => $snapshot->website_id,
                            'domain' => $domain->domain,
                            'domain_rank' => $domain->domainRank,
                            'backlinks_count' => $domain->backlinksCount,
                            'first_seen' => $domain->firstSeen,
                            'last_seen' => $domain->lastSeen,
                        ],
                        $referringDomainsResponse->domains,
                    ));
                    $snapshot->update(['metadata' => ['data_source' => 'third_party_estimate', 'datasets' => $datasets]]);
                    $snapshot->apiUsages()->create(
                        $this->usageData($snapshot->website, $referringDomainsResponse->endpoint, 'referring_domains', $referringDomainsResponse->resultCount, $referringDomainsResponse->cost, $referringDomainsResponse->taskId, $requestedAt),
                    );
                });
            } catch (DataForSEOException) {
                $errors['referring_domains'] = 'Referring domain data was unavailable from the provider.';
            }
        }

        if (! in_array('organic_competitors', $datasets, true)) {
            try {
                $competitorsResponse = $this->competitors->forDomain($domain, $locationCode, $languageCode);

                $datasets[] = 'organic_competitors';
                DB::transaction(function () use ($snapshot, $requestedAt, $competitorsResponse, $datasets): void {
                    $snapshot->competitors()->createMany(array_map(
                        fn (OrganicCompetitorData $competitor): array => [
                            'website_id' => $snapshot->website_id,
                            'domain' => $competitor->domain,
                            'common_keywords' => $competitor->commonKeywords,
                            'organic_keywords' => $competitor->organicKeywords,
                            'estimated_traffic' => $competitor->estimatedTraffic,
                            'competition_level' => null,
                        ],
                        $competitorsResponse->competitors,
                    ));
                    $snapshot->update(['metadata' => ['data_source' => 'third_party_estimate', 'datasets' => $datasets]]);
                    $snapshot->apiUsages()->create(
                        $this->usageData($snapshot->website, $competitorsResponse->endpoint, 'organic_competitors', $competitorsResponse->resultCount, $competitorsResponse->cost, $competitorsResponse->taskId, $requestedAt),
                    );
                });
            } catch (DataForSEOException) {
                $errors['organic_competitors'] = 'Organic competitor data was unavailable from the provider.';
            }
        }

        if (! in_array('seo_opportunities', $datasets, true)) {
            $datasets[] = 'seo_opportunities';
            DB::transaction(function () use ($snapshot, $datasets): void {
                $this->opportunities->generate($snapshot);
                $snapshot->update(['metadata' => ['data_source' => 'third_party_estimate', 'datasets' => $datasets]]);
            });
        }

        $snapshot->update([
            'status' => $errors === [] ? SeoSnapshot::STATUS_COMPLETED : SeoSnapshot::STATUS_COMPLETED_WITH_ERRORS,
            'metadata' => ['data_source' => 'third_party_estimate', 'datasets' => $datasets],
            'errors' => $errors,
            'completed_at' => now(),
        ]);

        return $snapshot->load('keywords', 'referringDomains', 'competitors', 'opportunities.keyword');
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
