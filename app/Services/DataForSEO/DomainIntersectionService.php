<?php

namespace App\Services\DataForSEO;

use App\Services\DataForSEO\Data\DataForSEOResponse;

class DomainIntersectionService
{
    public function __construct(private DataForSEOClient $client) {}

    public function compare(string $competitor, string $domain, int $location, string $language, bool $shared, int $limit): DataForSEOResponse
    {
        return $this->client->post('dataforseo_labs/google/domain_intersection/live', [
            'target1' => $competitor,
            'target2' => $domain,
            'location_code' => $location,
            'language_code' => $language,
            'intersections' => $shared,
            'item_types' => ['organic'],
            'limit' => min(1000, max(1, $limit)),
            'order_by' => ['keyword_data.keyword_info.search_volume,desc'],
        ]);
    }
}
