<?php

namespace App\Services\DataForSEO;

use App\Services\DataForSEO\Data\OrganicCompetitorData;
use App\Services\DataForSEO\Data\OrganicCompetitorsResponse;
use Illuminate\Support\Str;

class CompetitorsService
{
    public function __construct(private DataForSEOClient $client) {}

    public function forDomain(string $domain, int $locationCode, string $languageCode, ?int $limit = null): OrganicCompetitorsResponse
    {
        $targetDomain = Str::lower(trim($domain));
        $response = $this->client->post('dataforseo_labs/google/competitors_domain/live', [
            'target' => $targetDomain,
            'location_code' => $locationCode,
            'language_code' => $languageCode,
            'item_types' => ['organic'],
            'exclude_top_domains' => true,
            'max_rank_group' => 100,
            'limit' => min($limit ?? (int) config('services.dataforseo.competitors_limit'), 1000),
            'order_by' => ['intersections,desc'],
        ]);

        $items = data_get($response->results, '0.items', []);
        $competitors = collect(is_array($items) ? $items : [])
            ->filter(fn (mixed $item): bool => is_array($item))
            ->map(fn (array $item): ?OrganicCompetitorData => OrganicCompetitorData::fromArray($item))
            ->filter(fn (?OrganicCompetitorData $competitor): bool => $competitor !== null && $competitor->domain !== $targetDomain)
            ->unique(fn (OrganicCompetitorData $competitor): string => $competitor->domain)
            ->values()
            ->all();

        return new OrganicCompetitorsResponse(
            competitors: $competitors,
            cost: $response->cost,
            resultCount: count($competitors),
            taskId: $response->taskId,
            endpoint: $response->endpoint,
        );
    }
}
