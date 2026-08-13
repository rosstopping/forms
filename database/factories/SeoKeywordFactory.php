<?php

namespace Database\Factories;

use App\Models\SeoKeyword;
use App\Models\SeoSnapshot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SeoKeyword>
 */
class SeoKeywordFactory extends Factory
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
            'fingerprint' => hash('sha256', fake()->unique()->uuid()),
            'keyword' => fake()->words(3, true),
            'position' => fake()->numberBetween(1, 100),
            'previous_position' => fake()->optional()->numberBetween(1, 100),
            'ranking_url' => fake()->url(),
            'search_volume' => fake()->numberBetween(0, 10000),
            'cpc' => fake()->randomFloat(4, 0, 20),
            'competition' => fake()->randomFloat(5, 0, 1),
            'competition_level' => fake()->randomElement(['LOW', 'MEDIUM', 'HIGH']),
            'search_intent' => fake()->randomElement(['informational', 'commercial', 'transactional', 'navigational']),
            'estimated_traffic' => fake()->randomFloat(4, 0, 1000),
            'keyword_difficulty' => fake()->numberBetween(0, 100),
            'location_code' => 2826,
            'language_code' => 'en',
        ];
    }
}
