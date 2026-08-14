<?php

namespace App\Services\DataForSEO;

use App\Services\DataForSEO\Data\DataForSEOResponse;
use App\Services\DataForSEO\Data\RankedKeywordData;
use App\Services\DataForSEO\Data\RankedKeywordsResponse;
use Illuminate\Support\Collection;

class RankedKeywordsService
{
    public function __construct(private DataForSEOClient $client) {}

    public function forDomain(string $domain, int $locationCode, string $languageCode, ?int $limit = null): RankedKeywordsResponse
    {
        $totalLimit = min(max($limit ?? (int) config('services.dataforseo.ranked_keywords_limit'), 1), 1000);
        $pageOneLimit = max(1, (int) floor($totalLimit * 0.4));
        $deeperRankingLimit = $totalLimit - $pageOneLimit;
        $rankField = 'ranked_serp_element.serp_item.rank_group';

        $responses = collect([
            $this->requestBucket($domain, $locationCode, $languageCode, [$rankField, '<=', 10], $pageOneLimit),
        ]);

        if ($deeperRankingLimit > 0) {
            $responses->push($this->requestBucket($domain, $locationCode, $languageCode, [
                [$rankField, '>', 10],
                'and',
                [$rankField, '<=', 100],
            ], $deeperRankingLimit));
        }

        $keywords = $responses
            ->flatMap(fn (DataForSEOResponse $response): array => $this->keywords($response))
            ->unique(fn (RankedKeywordData $keyword): string => $keyword->fingerprint)
            ->values()
            ->all();

        return new RankedKeywordsResponse(
            keywords: $keywords,
            cost: $responses->sum(fn (DataForSEOResponse $response): float => $response->cost),
            resultCount: count($keywords),
            taskId: $this->taskIds($responses),
            endpoint: 'dataforseo_labs/google/ranked_keywords/live',
        );
    }

    /** @param array<int, mixed> $filters */
    protected function requestBucket(string $domain, int $locationCode, string $languageCode, array $filters, int $limit): DataForSEOResponse
    {
        return $this->client->post('dataforseo_labs/google/ranked_keywords/live', [
            'target' => $domain,
            'location_code' => $locationCode,
            'language_code' => $languageCode,
            'item_types' => ['organic'],
            'filters' => $filters,
            'limit' => $limit,
            'order_by' => [
                'keyword_data.keyword_info.search_volume,desc',
                'ranked_serp_element.serp_item.rank_group,asc',
            ],
        ]);
    }

    /** @return array<int, RankedKeywordData> */
    protected function keywords(DataForSEOResponse $response): array
    {
        $items = data_get($response->results, '0.items', []);
        $items = is_array($items) ? $items : [];

        return collect($items)
            ->filter(fn (mixed $item): bool => is_array($item))
            ->map(fn (array $item): ?RankedKeywordData => RankedKeywordData::fromArray($item))
            ->filter()
            ->values()
            ->all();
    }

    /** @param Collection<int, DataForSEOResponse> $responses */
    protected function taskIds(Collection $responses): ?string
    {
        $taskIds = $responses->pluck('taskId')->filter()->unique()->implode(',');

        return $taskIds !== '' ? $taskIds : null;
    }
}
