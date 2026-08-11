<?php

namespace Database\Factories;

use App\Models\SearchOpportunity;
use App\Models\Website;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SearchOpportunity>
 */
class SearchOpportunityFactory extends Factory
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
            'fingerprint' => hash('sha256', fake()->unique()->uuid()),
            'type' => 'ranking_gap',
            'status' => SearchOpportunity::STATUS_OPEN,
            'query' => fake()->words(3, true),
            'page' => fake()->url(),
            'title' => fake()->sentence(5),
            'summary' => fake()->sentence(),
            'recommendation' => fake()->paragraph(),
            'metrics' => ['current' => ['clicks' => 10, 'impressions' => 500, 'ctr' => 0.02, 'position' => 8]],
            'priority_score' => 500,
            'first_detected_at' => now(),
            'last_detected_at' => now(),
        ];
    }
}
