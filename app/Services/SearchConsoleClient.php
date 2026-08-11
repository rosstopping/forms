<?php

namespace App\Services;

use App\Models\SearchConsoleConnection;
use Illuminate\Http\Client\PendingRequest;
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
        $response = $this->request($connection)
            ->post('sites/'.rawurlencode((string) $connection->property_url).'/searchAnalytics/query', [
                'startDate' => now()->subDays(29)->toDateString(),
                'endDate' => now()->subDay()->toDateString(),
                'dimensions' => ['query', 'page'],
                'type' => 'web',
                'rowLimit' => 250,
            ])->throw();

        return collect($response->json('rows', []))->map(fn (array $row): array => [
            'query' => data_get($row, 'keys.0'),
            'page' => data_get($row, 'keys.1'),
            'clicks' => (float) ($row['clicks'] ?? 0),
            'impressions' => (float) ($row['impressions'] ?? 0),
            'ctr' => (float) ($row['ctr'] ?? 0),
            'position' => (float) ($row['position'] ?? 0),
        ])->all();
    }

    protected function request(SearchConsoleConnection $connection): PendingRequest
    {
        return Http::baseUrl((string) config('services.google.search_console_url'))
            ->acceptJson()->withToken($this->oauth->accessToken($connection))
            ->connectTimeout(5)->timeout(30)->retry([500, 1500], throw: false);
    }
}
