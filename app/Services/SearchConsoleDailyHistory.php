<?php

namespace App\Services;

use App\Models\SearchConsoleConnection;
use App\Models\SearchConsoleDailyMetric;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SearchConsoleDailyHistory
{
    public function __construct(private SearchConsoleClient $client) {}

    public function sync(SearchConsoleConnection $connection): void
    {
        $connection = $connection->fresh();
        if (! $connection?->property_url || ! $connection->website?->is_active || $connection->access_denied_at) {
            return;
        }
        $property = $connection->property_url;
        $hash = hash('sha256', $property);
        $end = ReportingPeriod::cutoff();
        $earliest = $end->subMonthsNoOverflow(16);
        $latest = SearchConsoleDailyMetric::where('website_id', $connection->website_id)->where('property_hash', $hash)->max('date');
        $start = $latest ? CarbonImmutable::parse($latest, 'America/Los_Angeles')->subDays(6)->max($earliest)->min($end) : $earliest;
        $result = $this->client->dailyPerformance($connection, Carbon::instance($start), Carbon::instance($end));
        $finalEnd = isset($result['incomplete_from']) ? CarbonImmutable::parse($result['incomplete_from'], 'America/Los_Angeles')->subDay()->min($end) : $end;
        $rows = collect($result['rows'])->keyBy('date');
        $values = [];
        for ($date = $start; $date->lessThanOrEqualTo($finalEnd); $date = $date->addDay()) {
            $row = $rows->get($date->toDateString());
            $values[] = [
                'website_id' => $connection->website_id, 'search_console_connection_id' => $connection->id,
                'property_url' => $property, 'property_hash' => $hash, 'date' => $date->toDateString(),
                'data_status' => $row ? 'observed' : 'no_data',
                'clicks' => $row['clicks'] ?? null, 'impressions' => $row['impressions'] ?? null, 'position' => $row['position'] ?? null,
                'fetched_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ];
        }
        DB::transaction(function () use ($connection, $property, $hash, $values, $finalEnd, $end): void {
            $current = SearchConsoleConnection::whereKey($connection->id)->lockForUpdate()->first();
            if ($current?->property_url !== $property) {
                return;
            }
            foreach (array_chunk($values, 100) as $chunk) {
                SearchConsoleDailyMetric::upsert($chunk, ['website_id', 'property_hash', 'date'], ['search_console_connection_id', 'data_status', 'clicks', 'impressions', 'position', 'fetched_at', 'updated_at']);
            }
            SearchConsoleDailyMetric::where('website_id', $connection->website_id)->where('property_hash', $hash)
                ->whereBetween('date', [$finalEnd->addDay()->toDateString(), $end->toDateString()])->delete();
        });
    }
}
