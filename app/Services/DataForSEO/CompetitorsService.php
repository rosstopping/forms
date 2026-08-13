<?php

namespace App\Services\DataForSEO;

use App\Services\DataForSEO\Data\DataForSEOResponse;

class CompetitorsService
{
    public function __construct(private DataForSEOClient $client) {}

    public function forDomain(string $domain, int $locationCode, string $languageCode, ?int $limit = null): DataForSEOResponse
    {
        return $this->client->post('dataforseo_labs/google/competitors_domain/live', [
            'target' => $domain,
            'location_code' => $locationCode,
            'language_code' => $languageCode,
            'item_types' => ['organic'],
            'exclude_top_domains' => true,
            'limit' => min($limit ?? (int) config('services.dataforseo.competitors_limit'), 1000),
        ]);
    }
}
