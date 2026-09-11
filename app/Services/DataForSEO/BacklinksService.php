<?php

namespace App\Services\DataForSEO;

use App\Services\DataForSEO\Data\BacklinkOverviewData;
use App\Services\DataForSEO\Data\BacklinkOverviewResponse;
use App\Services\DataForSEO\Data\DataForSEOResponse;
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

    public function backlinks(string $domain, string $status, int $limit): DataForSEOResponse
    {
        $task = [
            'target' => $domain,
            'include_subdomains' => true,
            'exclude_internal_backlinks' => true,
            'backlinks_status_type' => $status,
            'rank_scale' => 'one_hundred',
            'limit' => min(max($limit, 1), 1000),
            'order_by' => ['rank,desc'],
        ];
        if ($status === 'lost') {
            $task['filters'] = [['last_seen', '>=', now()->subDays(90)->toDateString()]];
        }

        return $this->client->post('backlinks/backlinks/live', $task);
    }

    public function domainPages(string $domain, int $limit): DataForSEOResponse
    {
        return $this->client->post('backlinks/domain_pages/live', [
            'target' => $domain,
            'include_subdomains' => true,
            'backlinks_status_type' => 'live',
            'rank_scale' => 'one_hundred',
            'limit' => min(max($limit, 1), 1000),
            'order_by' => ['page_summary.backlinks,desc'],
        ]);
    }

    public function newLostTrend(string $domain, int $months): DataForSEOResponse
    {
        return $this->client->post('backlinks/timeseries_new_lost_summary/live', [
            'target' => $domain,
            'date_from' => now()->subMonths($months)->startOfMonth()->toDateString(),
            'date_to' => now()->toDateString(),
            'group_range' => 'month',
            'include_subdomains' => true,
        ]);
    }

    /** @param array<int, string> $competitorDomains */
    public function domainIntersection(string $ourDomain, array $competitorDomains, int $limit): DataForSEOResponse
    {
        return $this->client->post('backlinks/domain_intersection/live', [
            'targets' => collect($competitorDomains)->values()->mapWithKeys(fn (string $domain, int $index): array => [(string) ($index + 1) => $domain])->all(),
            'exclude_targets' => [$ourDomain],
            'intersection_mode' => count($competitorDomains) > 1 ? 'partial' : 'all',
            'include_subdomains' => true,
            'exclude_internal_backlinks' => true,
            'backlinks_status_type' => 'live',
            'rank_scale' => 'one_hundred',
            'limit' => min(max($limit, 1), 1000),
        ]);
    }
}
