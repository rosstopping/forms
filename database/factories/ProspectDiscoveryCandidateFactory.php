<?php

namespace Database\Factories;

use App\Models\ProspectDiscovery;
use App\Models\ProspectDiscoveryCandidate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProspectDiscoveryCandidate>
 */
class ProspectDiscoveryCandidateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'prospect_discovery_id' => ProspectDiscovery::factory(),
            'source_key' => 'node/'.fake()->unique()->numberBetween(1, 999999),
            'business_name' => fake()->company(),
            'website_url' => fake()->url(),
        ];
    }
}
