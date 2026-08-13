<?php

namespace Database\Factories;

use App\Models\SeoOpportunity;
use App\Models\SeoSnapshot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SeoOpportunity>
 */
class SeoOpportunityFactory extends Factory
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
            'seo_keyword_id' => null,
            'fingerprint' => hash('sha256', fake()->unique()->uuid()),
            'type' => 'striking_distance',
            'status' => SeoOpportunity::STATUS_OPEN,
            'title' => 'Improve a striking-distance keyword',
            'summary' => fake()->sentence(),
            'recommendation' => fake()->sentence(),
            'metrics' => [],
            'priority_score' => fake()->randomFloat(4, 0, 10000),
        ];
    }
}
