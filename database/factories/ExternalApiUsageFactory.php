<?php

namespace Database\Factories;

use App\Models\ExternalApiUsage;
use App\Models\SeoSnapshot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExternalApiUsage>
 */
class ExternalApiUsageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'seo_snapshot_id' => SeoSnapshot::factory(),
            'website_id' => fn (array $attributes): int => SeoSnapshot::query()->findOrFail($attributes['seo_snapshot_id'])->website_id,
            'provider' => 'dataforseo',
            'endpoint' => 'dataforseo_labs/google/ranked_keywords/live',
            'request_type' => 'ranked_keywords',
            'result_count' => fake()->numberBetween(0, 500),
            'cost' => fake()->randomFloat(6, 0.001, 1),
            'provider_task_id' => fake()->uuid(),
            'metadata' => [],
            'requested_at' => now(),
        ];
    }
}
