<?php

namespace Database\Factories;

use App\Models\Website;
use App\Models\WebsiteCompetitor;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<WebsiteCompetitor> */
class WebsiteCompetitorFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['website_id' => Website::factory(), 'domain' => fake()->unique()->domainName(), 'excluded' => false, 'source' => 'manual'];
    }
}
