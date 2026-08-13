<?php

namespace App\Services\DataForSEO;

use App\Services\DataForSEO\Data\DataForSEOResponse;

class DomainOverviewService
{
    public function __construct(private DataForSEOClient $client) {}

    public function forDomain(string $domain, int $locationCode, string $languageCode): DataForSEOResponse
    {
        return $this->client->post('dataforseo_labs/google/domain_rank_overview/live', [
            'target' => $domain,
            'location_code' => $locationCode,
            'language_code' => $languageCode,
        ]);
    }
}
