<?php

namespace App\Services\DataForSEO;

use App\Services\DataForSEO\Data\BacklinkOverviewData;
use App\Services\DataForSEO\Data\BacklinkOverviewResponse;
use App\Services\DataForSEO\Data\ReferringDomainData;
use App\Services\DataForSEO\Data\ReferringDomainsResponse;

class BacklinksService
{
    public function __construct(private DataForSEOClient $client) {}

    public function overview(string $domain): BacklinkOverviewResponse
    {
        $response = $this->client->post('backlinks/summary/live', [
            'target' => $domain,
            'include_subdomains' => true,
            'backlinks_status_type' => 'all',
            'rank_scale' => 'one_hundred',
        ]);

        return new BacklinkOverviewResponse(
            overview: BacklinkOverviewData::fromResults($response->results),
            cost: $response->cost,
            resultCount: $response->resultCount,
            taskId: $response->taskId,
            endpoint: $response->endpoint,
        );
    }

    public function referringDomains(string $domain, ?int $limit = null): ReferringDomainsResponse
    {
        $response = $this->client->post('backlinks/referring_domains/live', [
            'target' => $domain,
            'include_subdomains' => true,
            'backlinks_status_type' => 'all',
            'rank_scale' => 'one_hundred',
            'limit' => min($limit ?? (int) config('services.dataforseo.referring_domains_limit'), 1000),
            'order_by' => ['rank,desc'],
        ]);

        $items = data_get($response->results, '0.items', []);
        $domains = collect(is_array($items) ? $items : [])
            ->filter(fn (mixed $item): bool => is_array($item))
            ->map(fn (array $item): ?ReferringDomainData => ReferringDomainData::fromArray($item))
            ->filter()
            ->unique(fn (ReferringDomainData $domain): string => $domain->domain)
            ->values()
            ->all();

        return new ReferringDomainsResponse(
            domains: $domains,
            cost: $response->cost,
            resultCount: count($domains),
            taskId: $response->taskId,
            endpoint: $response->endpoint,
        );
    }
}
