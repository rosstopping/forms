<?php

namespace Database\Factories;

use App\Models\SeoProspectCandidate;
use App\Models\SeoProspectSearch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SeoProspectCandidate>
 */
class SeoProspectCandidateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'seo_prospect_search_id' => SeoProspectSearch::factory(),
            'domain' => fake()->unique()->domainName(),
            'website_url' => fake()->url(),
            'business_name' => fake()->company(),
            'location' => fake()->city(),
        ];
    }
}
