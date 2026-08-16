<?php

namespace App\Services;

use App\Models\SearchConsoleConnection;
use App\Models\SearchConsoleMetric;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class SearchConsoleHistoryStore
{
    protected const MONTHLY_QUERY_LIMIT = 1000;

    public function __construct(protected SearchConsoleClient $searchConsole) {}

    /** @return array<int, array{month: string, clicks: float, impressions: float, ctr: float, position: float}> */
    public function syncSite(SearchConsoleConnection $connection): array
    {
        $this->store($connection, SearchConsoleMetric::SITE_DIMENSION_KEY, null, $this->searchConsole->monthlyPerformance($connection));

        return $this->history($connection, SearchConsoleMetric::SITE_DIMENSION_KEY);
    }

    /** @return array<int, array{month: string, clicks: float, impressions: float, ctr: float, position: float}> */
    public function syncQuery(SearchConsoleConnection $connection, string $query): array
    {
        $dimensionKey = $this->queryDimensionKey($query);
        $this->store($connection, $dimensionKey, $query, $this->searchConsole->monthlyPerformanceForQuery($connection, $query));

        return $this->history($connection, $dimensionKey);
    }

    public function syncTracked(SearchConsoleConnection $connection): void
    {
        $this->syncSite($connection);
        $this->syncMonthlyQuerySample($connection);
    }

    protected function syncMonthlyQuerySample(SearchConsoleConnection $connection): void
    {
        $month = now()->subMonthsNoOverflow(16)->startOfMonth();
        $availableThrough = now()->subDays(3)->endOfDay();

        while ($month->lte($availableThrough)) {
            $periodEnd = $month->copy()->endOfMonth()->min($availableThrough);
            $rows = $this->searchConsole->queryPerformanceForPeriod($connection, $month, $periodEnd, self::MONTHLY_QUERY_LIMIT);
            $this->storeQuerySample($connection, $month, $rows);
            $month = $month->copy()->addMonthNoOverflow()->startOfMonth();
        }
    }

    /** @param array<int, array{query: string, clicks: float, impressions: float, ctr: float, position: float}> $metrics */
    protected function storeQuerySample(SearchConsoleConnection $connection, Carbon $month, array $metrics): void
    {
        $now = now();
        $propertyUrl = (string) $connection->property_url;
        $propertyHash = hash('sha256', $propertyUrl);
        $rows = collect($metrics)
            ->filter(fn (array $metric): bool => filled($metric['query']))
            ->map(fn (array $metric): array => [
                'website_id' => $connection->website_id,
                'search_console_connection_id' => $connection->id,
                'property_url' => $propertyUrl,
                'property_hash' => $propertyHash,
                'month' => $month->toDateString(),
                'dimension_key' => $this->queryDimensionKey($metric['query']),
                'query' => $metric['query'],
                'clicks' => $metric['clicks'],
                'impressions' => $metric['impressions'],
                'ctr' => $metric['ctr'],
                'position' => $metric['position'],
                'created_at' => $now,
                'updated_at' => $now,
            ])->all();

        if ($rows !== []) {
            SearchConsoleMetric::query()->upsert(
                $rows,
                ['website_id', 'property_hash', 'month', 'dimension_key'],
                ['search_console_connection_id', 'property_url', 'query', 'clicks', 'impressions', 'ctr', 'position', 'updated_at'],
            );
        }
    }

    /**
     * @param  array<int, array{month: string, clicks: float, impressions: float, ctr: float, position: float}>  $history
     */
    protected function store(SearchConsoleConnection $connection, string $dimensionKey, ?string $query, array $history): void
    {
        $now = now();
        $propertyUrl = (string) $connection->property_url;
        $propertyHash = hash('sha256', $propertyUrl);
        $rows = collect($history)->map(fn (array $point): array => [
            'website_id' => $connection->website_id,
            'search_console_connection_id' => $connection->id,
            'property_url' => $propertyUrl,
            'property_hash' => $propertyHash,
            'month' => Carbon::createFromFormat('Y-m', $point['month'])->startOfMonth()->toDateString(),
            'dimension_key' => $dimensionKey,
            'query' => $query,
            'clicks' => $point['clicks'],
            'impressions' => $point['impressions'],
            'ctr' => $point['ctr'],
            'position' => $point['position'],
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        if ($rows === []) {
            return;
        }

        SearchConsoleMetric::query()->upsert(
            $rows,
            ['website_id', 'property_hash', 'month', 'dimension_key'],
            ['search_console_connection_id', 'property_url', 'query', 'clicks', 'impressions', 'ctr', 'position', 'updated_at'],
        );
    }

    /** @return array<int, array{month: string, clicks: float, impressions: float, ctr: float, position: float}> */
    protected function history(SearchConsoleConnection $connection, string $dimensionKey): array
    {
        return $this->currentPropertyMetrics($connection)
            ->where('dimension_key', $dimensionKey)
            ->oldest('month')
            ->get()
            ->map(fn (SearchConsoleMetric $metric): array => [
                'month' => $metric->month->format('Y-m'),
                'clicks' => $metric->clicks,
                'impressions' => $metric->impressions,
                'ctr' => $metric->ctr,
                'position' => $metric->position,
            ])->all();
    }

    /** @return Builder<SearchConsoleMetric> */
    protected function currentPropertyMetrics(SearchConsoleConnection $connection): Builder
    {
        return SearchConsoleMetric::query()
            ->where('website_id', $connection->website_id)
            ->where('property_hash', hash('sha256', (string) $connection->property_url));
    }

    protected function queryDimensionKey(string $query): string
    {
        return hash('sha256', 'query:'.$query);
    }
}
