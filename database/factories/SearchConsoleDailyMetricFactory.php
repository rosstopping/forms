<?php

namespace Database\Factories;

use App\Models\SearchConsoleConnection;
use App\Models\SearchConsoleDailyMetric;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SearchConsoleDailyMetric> */
class SearchConsoleDailyMetricFactory extends Factory
{
    public function definition(): array
    {
        return ['search_console_connection_id' => SearchConsoleConnection::factory(),
            'website_id' => fn (array $attributes) => SearchConsoleConnection::findOrFail($attributes['search_console_connection_id'])->website_id,
            'property_url' => fn (array $attributes) => SearchConsoleConnection::findOrFail($attributes['search_console_connection_id'])->property_url,
            'property_hash' => fn (array $attributes) => hash('sha256', $attributes['property_url']),
            'date' => today()->subDays(3), 'data_status' => 'observed', 'clicks' => 10, 'impressions' => 100, 'position' => 8, 'fetched_at' => now()];
    }
}
