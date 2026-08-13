<?php

namespace Database\Factories;

use App\Models\SeoSnapshot;
use App\Models\Website;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SeoSnapshot>
 */
class SeoSnapshotFactory extends Factory
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
            'provider' => SeoSnapshot::PROVIDER_DATAFORSEO,
            'domain' => fake()->unique()->domainName(),
            'location_code' => 2826,
            'language_code' => 'en',
            'status' => SeoSnapshot::STATUS_COMPLETED,
            'organic_keywords' => fake()->numberBetween(0, 1000),
            'estimated_organic_traffic' => fake()->randomFloat(4, 0, 10000),
            'top_3_keywords' => fake()->numberBetween(0, 20),
            'top_10_keywords' => fake()->numberBetween(0, 50),
            'top_20_keywords' => fake()->numberBetween(0, 100),
            'top_100_keywords' => fake()->numberBetween(0, 500),
            'snapshot_date' => today(),
            'metadata' => [],
            'errors' => [],
            'started_at' => now()->subMinute(),
            'completed_at' => now(),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (): array => [
            'status' => SeoSnapshot::STATUS_PENDING,
            'started_at' => null,
            'completed_at' => null,
        ]);
    }
}
