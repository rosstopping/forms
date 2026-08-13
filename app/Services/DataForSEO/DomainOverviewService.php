<?php

namespace App\Services\DataForSEO;

use App\Services\DataForSEO\Data\DomainOverviewData;
use App\Services\DataForSEO\Data\DomainOverviewResponse;

class DomainOverviewService
{
    public function __construct(private DataForSEOClient $client) {}

    public function forDomain(string $domain, int $locationCode, string $languageCode): DomainOverviewResponse
    {
        $response = $this->client->post('dataforseo_labs/google/domain_rank_overview/live', [
            'target' => $domain,
            'location_code' => $locationCode,
            'language_code' => $languageCode,
        ]);

        return new DomainOverviewResponse(
            overview: DomainOverviewData::fromResults($response->results),
            cost: $response->cost,
            resultCount: $response->resultCount,
            taskId: $response->taskId,
            endpoint: $response->endpoint,
        );
    }
}
