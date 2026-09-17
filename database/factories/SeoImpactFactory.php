<?php

namespace Database\Factories;

use App\Models\SeoImpact;
use App\Models\Website;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SeoImpact> */
class SeoImpactFactory extends Factory
{
    public function definition(): array
    {
        return ['website_id' => Website::factory(), 'source_key' => 'manual:'.fake()->uuid(), 'title' => 'Improve service page visibility', 'hypothesis' => 'Clarifying the offer and adding useful internal links should increase relevant search clicks.', 'target_urls' => ['https://example.com/services'], 'target_queries' => ['garden offices']];
    }
}
