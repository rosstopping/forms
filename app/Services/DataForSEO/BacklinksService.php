<?php

namespace App\Services\DataForSEO;

use App\Services\DataForSEO\Data\DataForSEOResponse;

class BacklinksService
{
    public function __construct(private DataForSEOClient $client) {}

    public function overview(string $domain): DataForSEOResponse
    {
        return $this->client->post('backlinks/summary/live', [
            'target' => $domain,
            'include_subdomains' => true,
            'backlinks_status_type' => 'all',
            'rank_scale' => 'one_hundred',
        ]);
    }

    public function referringDomains(string $domain, ?int $limit = null): DataForSEOResponse
    {
        return $this->client->post('backlinks/referring_domains/live', [
            'target' => $domain,
            'include_subdomains' => true,
            'backlinks_status_type' => 'all',
            'rank_scale' => 'one_hundred',
            'limit' => min($limit ?? (int) config('services.dataforseo.referring_domains_limit'), 1000),
            'order_by' => ['rank,desc'],
        ]);
    }
}
