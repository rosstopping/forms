<?php

namespace App\Services\DataForSEO;

use App\Services\DataForSEO\Data\DataForSEOResponse;

class RelevantPagesService
{
    public function __construct(private DataForSEOClient $client) {}

    public function forDomain(string $domain, int $location, string $language, int $limit): DataForSEOResponse
    {
        return $this->client->post('dataforseo_labs/google/relevant_pages/live', [
            'target' => $domain,
            'location_code' => $location,
            'language_code' => $language,
            'limit' => min(1000, max(1, $limit)),
            'order_by' => ['metrics.organic.etv,desc'],
        ]);
    }
}
