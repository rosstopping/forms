<?php

namespace App\Services\DataForSEO;

use App\Services\DataForSEO\Data\DataForSEOResponse;

class RankedKeywordsService
{
    public function __construct(private DataForSEOClient $client) {}

    public function forDomain(string $domain, int $locationCode, string $languageCode, ?int $limit = null): DataForSEOResponse
    {
        return $this->client->post('dataforseo_labs/google/ranked_keywords/live', [
            'target' => $domain,
            'location_code' => $locationCode,
            'language_code' => $languageCode,
            'item_types' => ['organic'],
            'limit' => min($limit ?? (int) config('services.dataforseo.ranked_keywords_limit'), 1000),
            'order_by' => ['ranked_serp_element.serp_item.rank_absolute,asc'],
        ]);
    }
}
