<?php

namespace App\Services\DataForSEO;

use App\Services\DataForSEO\Data\RankedKeywordData;
use App\Services\DataForSEO\Data\RankedKeywordsResponse;

class RankedKeywordsService
{
    public function __construct(private DataForSEOClient $client) {}

    public function forDomain(string $domain, int $locationCode, string $languageCode, ?int $limit = null): RankedKeywordsResponse
    {
        $response = $this->client->post('dataforseo_labs/google/ranked_keywords/live', [
            'target' => $domain,
            'location_code' => $locationCode,
            'language_code' => $languageCode,
            'item_types' => ['organic'],
            'filters' => ['ranked_serp_element.serp_item.rank_group', '<=', 100],
            'limit' => min($limit ?? (int) config('services.dataforseo.ranked_keywords_limit'), 1000),
            'order_by' => [
                'keyword_data.keyword_info.search_volume,desc',
                'ranked_serp_element.serp_item.rank_group,asc',
            ],
        ]);

        $items = data_get($response->results, '0.items', []);
        $items = is_array($items) ? $items : [];
        $keywords = collect($items)
            ->filter(fn (mixed $item): bool => is_array($item))
            ->map(fn (array $item): ?RankedKeywordData => RankedKeywordData::fromArray($item))
            ->filter()
            ->unique(fn (RankedKeywordData $keyword): string => $keyword->fingerprint)
            ->values()
            ->all();

        return new RankedKeywordsResponse(
            keywords: $keywords,
            cost: $response->cost,
            resultCount: count($keywords),
            taskId: $response->taskId,
            endpoint: $response->endpoint,
        );
    }
}
