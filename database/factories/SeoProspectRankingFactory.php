<?php

namespace Database\Factories;

use App\Models\SeoProspectCandidate;
use App\Models\SeoProspectRanking;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SeoProspectRanking>
 */
class SeoProspectRankingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'seo_prospect_candidate_id' => SeoProspectCandidate::factory(),
            'keyword' => fake()->words(3, true),
            'position' => fake()->numberBetween(1, 100),
            'ranking_url' => fake()->url(),
            'checked_at' => now(),
        ];
    }
}
