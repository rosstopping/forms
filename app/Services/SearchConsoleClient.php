<?php

namespace App\Services;

use App\Models\SearchConsoleConnection;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

class SearchConsoleClient
{
    public function __construct(protected GoogleOAuthClient $oauth) {}

    /** @return array<int, array<string, mixed>> */
    public function sites(SearchConsoleConnection $connection): array
    {
        return $this->request($connection)->get('sites')->throw()->json('siteEntry', []);
    }

    /** @return array<int, array<string, mixed>> */
    public function performance(SearchConsoleConnection $connection): array
    {
        return collect($this->performanceRows($connection, ['query', 'page'], 250))->map(fn (array $row): array => [
            'query' => data_get($row, 'keys.0'),
            'page' => data_get($row, 'keys.1'),
            'clicks' => (float) ($row['clicks'] ?? 0),
            'impressions' => (float) ($row['impressions'] ?? 0),
            'ctr' => (float) ($row['ctr'] ?? 0),
            'position' => (float) ($row['position'] ?? 0),
        ])->all();
    }

    /** @return array<int, array{query: string, clicks: float, impressions: float, ctr: float, position: float}> */
    public function queryPerformance(SearchConsoleConnection $connection, int $rowLimit, int $startRow = 0): array
    {
        return collect($this->performanceRows($connection, ['query'], $rowLimit, $startRow))
            ->map(fn (array $row): array => ['query' => (string) data_get($row, 'keys.0'), ...$this->formatRow($row)])
            ->all();
    }

    /** @return array<int, array{query: string, clicks: float, impressions: float, ctr: float, position: float}> */
    public function queryPerformanceForPeriod(SearchConsoleConnection $connection, Carbon $startDate, Carbon $endDate, int $rowLimit = 1000): array
    {
        return collect($this->performanceRows($connection, ['query'], $rowLimit, dates: ['start' => $startDate, 'end' => $endDate]))
            ->map(fn (array $row): array => ['query' => (string) data_get($row, 'keys.0'), ...$this->formatRow($row)])
            ->all();
    }

    /** @return array<int, array{query: string, page: string, clicks: float, impressions: float, ctr: float, position: float}> */
    public function queryPagePerformance(SearchConsoleConnection $connection, int $rowLimit, int $startRow = 0): array
    {
        return collect($this->performanceRows($connection, ['query', 'page'], $rowLimit, $startRow))
            ->map(fn (array $row): array => [
                'query' => (string) data_get($row, 'keys.0'),
                'page' => (string) data_get($row, 'keys.1'),
                ...$this->formatRow($row),
            ])->all();
    }

    /** @return array<int, array{query: string, page: string, clicks: float, impressions: float, ctr: float, position: float}> */
    public function queryPagePerformanceForPeriod(SearchConsoleConnection $connection, Carbon $startDate, Carbon $endDate, int $rowLimit = 25000): array
    {
        return collect($this->performanceRows($connection, ['query', 'page'], $rowLimit, dates: ['start' => $startDate, 'end' => $endDate]))
            ->map(fn (array $row): array => [
                'query' => (string) data_get($row, 'keys.0'),
                'page' => (string) data_get($row, 'keys.1'),
                ...$this->formatRow($row),
            ])->all();
    }

    /** @return array<int, array{page: string, clicks: float, impressions: float, ctr: float, position: float}> */
    public function pagePerformance(SearchConsoleConnection $connection, int $rowLimit, int $startRow = 0): array
    {
        return collect($this->performanceRows($connection, ['page'], $rowLimit, $startRow))
            ->map(fn (array $row): array => ['page' => (string) data_get($row, 'keys.0'), ...$this->formatRow($row)])
            ->all();
    }

    /**
     * @return array{
     *     period: array{start: string, end: string},
     *     totals: array{clicks: float, impressions: float, ctr: float, position: float},
     *     queries: array<int, array{query: string, clicks: float, impressions: float, ctr: float, position: float}>,
     *     pages: array<int, array{page: string, clicks: float, impressions: float, ctr: float, position: float}>
     * }
     */
    public function report(SearchConsoleConnection $connection): array
    {
        $totals = $this->formatRow($this->performanceRows($connection, [], 1)[0] ?? []);
        $queries = $this->queryPerformance($connection, 10);
        $pages = $this->pagePerformance($connection, 10);

        return [
            'period' => [
                'start' => now()->subDays(29)->toDateString(),
                'end' => now()->subDay()->toDateString(),
            ],
            'totals' => $totals,
            'queries' => $queries,
            'pages' => $pages,
        ];
    }

    /** @return array<int, array{month: string, clicks: float, impressions: float, ctr: float, position: float}> */
    public function monthlyPerformance(SearchConsoleConnection $connection): array
    {
        $rows = $this->performanceRows($connection, ['date'], 25000, dates: ['start' => now()->subMonthsNoOverflow(16)->startOfMonth(), 'end' => now()->subDays(3)]);

        return $this->monthlyRows($rows);
    }

    /** @return array<int, array{month: string, clicks: float, impressions: float, ctr: float, position: float}> */
    public function monthlyPerformanceForQuery(SearchConsoleConnection $connection, string $query): array
    {
        $rows = $this->performanceRows(
            $connection,
            ['date'],
            25000,
            dates: ['start' => now()->subMonthsNoOverflow(16)->startOfMonth(), 'end' => now()->subDays(3)],
            filters: [['dimension' => 'query', 'operator' => 'equals', 'expression' => $query]],
        );

        return $this->monthlyRows($rows);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array{month: string, clicks: float, impressions: float, ctr: float, position: float}>
     */
    protected function monthlyRows(array $rows): array
    {
        return collect($rows)->groupBy(fn (array $row): string => substr((string) data_get($row, 'keys.0'), 0, 7))->map(function ($rows, string $month): array {
            $clicks = (float) $rows->sum(fn (array $row): float => (float) ($row['clicks'] ?? 0));
            $impressions = (float) $rows->sum(fn (array $row): float => (float) ($row['impressions'] ?? 0));
            $weightedPosition = (float) $rows->sum(fn (array $row): float => (float) ($row['position'] ?? 0) * (float) ($row['impressions'] ?? 0));

            return ['month' => $month, 'clicks' => $clicks, 'impressions' => $impressions, 'ctr' => $impressions > 0 ? $clicks / $impressions : 0, 'position' => $impressions > 0 ? $weightedPosition / $impressions : 0];
        })->sortKeys()->values()->all();
    }

    /**
     * @param  array<int, string>  $dimensions
     * @return array<int, array<string, mixed>>
     */
    protected function performanceRows(SearchConsoleConnection $connection, array $dimensions, int $rowLimit, int $startRow = 0, ?array $dates = null, array $filters = []): array
    {
        $dates ??= ['start' => now()->subDays(29), 'end' => now()->subDay()];
        $payload = [
            'startDate' => $dates['start']->toDateString(),
            'endDate' => $dates['end']->toDateString(),
            'dimensions' => $dimensions,
            'type' => 'web',
            'rowLimit' => $rowLimit,
            'startRow' => $startRow,
        ];

        if ($filters !== []) {
            $payload['dimensionFilterGroups'] = [['groupType' => 'and', 'filters' => $filters]];
        }

        return $this->request($connection)
            ->post('sites/'.rawurlencode((string) $connection->property_url).'/searchAnalytics/query', $payload)
            ->throw()
            ->json('rows', []);
    }

    /** @return array{clicks: float, impressions: float, ctr: float, position: float} */
    protected function formatRow(array $row): array
    {
        return [
            'clicks' => (float) ($row['clicks'] ?? 0),
            'impressions' => (float) ($row['impressions'] ?? 0),
            'ctr' => (float) ($row['ctr'] ?? 0),
            'position' => (float) ($row['position'] ?? 0),
        ];
    }

    protected function request(SearchConsoleConnection $connection): PendingRequest
    {
        return Http::baseUrl((string) config('services.google.search_console_url'))
            ->acceptJson()->withToken($this->oauth->accessToken($connection))
            ->connectTimeout(5)->timeout(30)->retry([500, 1500], throw: false);
    }
}
