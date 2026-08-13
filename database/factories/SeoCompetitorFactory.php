<?php

namespace Database\Factories;

use App\Models\SeoCompetitor;
use App\Models\SeoSnapshot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SeoCompetitor>
 */
class SeoCompetitorFactory extends Factory
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
            'domain' => fake()->unique()->domainName(),
            'common_keywords' => fake()->numberBetween(1, 500),
            'organic_keywords' => fake()->numberBetween(1, 5000),
            'estimated_traffic' => fake()->randomFloat(4, 0, 100000),
            'competition_level' => fake()->randomFloat(6, 0, 1),
        ];
    }
}
