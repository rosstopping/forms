<?php

namespace App\Services;

use App\Models\SearchConsoleConnection;
use App\Models\SearchConsoleMetric;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class SearchConsoleHistoryStore
{
    protected const DISCOVERED_QUERY_LIMIT = 25;

    protected const EXISTING_QUERY_LIMIT = 25;

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

        $discoveredQueries = collect($this->searchConsole->queryPerformance($connection, self::DISCOVERED_QUERY_LIMIT))
            ->pluck('query');
        $existingQueries = $this->currentPropertyMetrics($connection)
            ->whereNotNull('query')
            ->select('query')
            ->selectRaw('MAX(updated_at) as last_updated_at')
            ->groupBy('query')
            ->orderByDesc('last_updated_at')
            ->limit(self::EXISTING_QUERY_LIMIT)
            ->pluck('query')
            ->values();

        $discoveredQueries
            ->merge($existingQueries)
            ->filter(fn (mixed $query): bool => is_string($query) && $query !== '')
            ->unique()
            ->each(fn (string $query) => $this->syncQuery($connection, $query));
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
