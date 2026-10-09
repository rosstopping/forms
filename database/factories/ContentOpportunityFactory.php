<?php

namespace Database\Factories;

use App\Models\ContentOpportunity;
use App\Models\Website;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ContentOpportunity> */
class ContentOpportunityFactory extends Factory
{
    public function definition(): array
    {
        return ['website_id' => Website::factory(), 'fingerprint' => hash('sha256', fake()->unique()->sentence()),
            'title' => fake()->sentence(), 'brief' => [], 'collected_at' => now()];
    }
}
