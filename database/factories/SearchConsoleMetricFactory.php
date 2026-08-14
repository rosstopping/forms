<?php

namespace Database\Factories;

use App\Models\SearchConsoleMetric;
use App\Models\Website;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SearchConsoleMetric>
 */
class SearchConsoleMetricFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'website_id' => Website::factory(),
            'search_console_connection_id' => null,
            'property_url' => 'sc-domain:example.com',
            'property_hash' => hash('sha256', 'sc-domain:example.com'),
            'month' => today()->startOfMonth(),
            'dimension_key' => SearchConsoleMetric::SITE_DIMENSION_KEY,
            'query' => null,
            'clicks' => fake()->randomFloat(4, 0, 10000),
            'impressions' => fake()->randomFloat(4, 0, 100000),
            'ctr' => fake()->randomFloat(8, 0, 1),
            'position' => fake()->randomFloat(4, 1, 100),
        ];
    }
}
